<?php

namespace App\Services;

use App\Models\BlockedDate;
use App\Models\MassSchedule;
use App\Models\Reservation;
use Carbon\Carbon;

/**
 * Church Availability & Conflict Detection Engine.
 *
 * Sacramenta manages a single church venue (the Parish of the Holy Sacraments today, but
 * every reservation already carries a location_id — see Reservation::location
 * — so a second venue only needs its occupancy timeline keyed by
 * location_id; the overlap math below doesn't change). This service is the
 * single source of truth for "is the church free at this date/time",
 * replacing ad-hoc per-priest/per-chapel checks with one whole-church
 * occupancy timeline that:
 *
 *   - Includes every occupying event (Masses, Weddings, Baptisms, Burials,
 *     First Communions, Confirmations, School Masses, Chapel Masses, and
 *     other approved church events) with its full prep + cleanup buffer.
 *   - Excludes Pamisa sa Kalag, which attaches to an existing Mass slot
 *     instead of reserving independent time (see config/church_schedule.php
 *     `occupying_types`).
 *   - Honors parish-wide BlockedDate periods (Christmas, Holy Week, etc.).
 *   - Powers the live "available time slots" panel, live conflict warnings,
 *     and nearest-available-slot suggestions in ReservationForm.vue.
 */
class ChurchAvailabilityService
{
    /**
     * Statuses that actually hold a slot. Mirrors SchedulingConflictService:
     * drafts are tentative and may overlap each other; once something is
     * confirmed it becomes authoritative and blocks everything else.
     */
    protected const BLOCKING_STATUSES = ['confirmed'];

    public function durationMinutes(string $type, array $details = []): int
    {
        return \App\Support\ReservationDuration::minutes($type, $details);
    }

    public function label(string $type): string
    {
        return config("church_schedule.labels.{$type}", ucwords(str_replace('_', ' ', $type)));
    }

    public function priority(string $type): int
    {
        return (int) (config("church_schedule.priority.{$type}")
            ?? config('church_schedule.default_priority', 9));
    }

    public function occupiesChurch(string $type): bool
    {
        return in_array($type, config('church_schedule.occupying_types', []), true);
    }

    /**
     * Whether the given date falls inside an active BlockedDate period for
     * the given venue (null = the parish's single/default venue).
     */
    public function isBlocked(string $date, ?int $locationId = null): ?BlockedDate
    {
        return BlockedDate::query()
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($q) use ($locationId) {
                $q->whereNull('location_id')
                    ->when($locationId, fn ($q2) => $q2->orWhere('location_id', $locationId));
            })
            ->first();
    }

    /**
     * Every occupied period on the church calendar for a given date, sorted
     * by start time. Each entry: type, label, start (Carbon), end (Carbon),
     * reservation_id, priest name, status, priority.
     */
    public function occupiedPeriods(string $date, ?int $excludeReservationId = null, ?int $locationId = null): array
    {
        $reservations = Reservation::query()
            ->with('priest:id,name')
            ->whereDate('event_date', $date)
            ->whereNotNull('event_time')
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->whereIn('type', config('church_schedule.occupying_types', []))
            ->when($excludeReservationId, fn ($q) => $q->where('id', '!=', $excludeReservationId))
            ->when($locationId, fn ($q) => $q->where(function ($q2) use ($locationId) {
                $q2->whereNull('location_id')->orWhere('location_id', $locationId);
            }))
            ->get();

        $periods = $reservations->map(function (Reservation $r) {
            $start = Carbon::parse($r->event_date->format('Y-m-d').' '.$r->event_time);

            return [
                'type' => $r->type,
                'label' => $this->label($r->type),
                'reservation_id' => $r->id,
                'reservation_number' => $r->id ? 'RES-'.str_pad((string) $r->id, 5, '0', STR_PAD_LEFT) : null,
                'start' => $start,
                'end' => $start->copy()->addMinutes($this->durationMinutes($r->type, $r->details ?? [])),
                'priest' => $r->priest?->name,
                'status' => $r->status,
                'priority' => $this->priority($r->type),
            ];
        });

        // Standing weekly Mass template rows (MassSchedule) that haven't
        // materialized into an actual Reservation row yet (e.g. the daily
        // GenerateMassSchedule command hasn't reached this date), so the
        // engine still blocks against the parish's default Mass schedule
        // even for dates further out. Skips any weekday slot that already
        // has a matching reservation above, to avoid double-counting.
        $weekday = Carbon::parse($date)->dayOfWeek; // Carbon: Sun=0..Sat=6, matches MassSchedule days_of_week
        $reservedTimes = $reservations->pluck('event_time')->map(fn ($t) => substr((string) $t, 0, 5))->all();

        $templateSlots = MassSchedule::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (MassSchedule $s) => in_array($weekday, $s->days_of_week ?? [], true))
            ->filter(fn (MassSchedule $s) => ! in_array(substr($s->start_time, 0, 5), $reservedTimes, true))
            ->map(function (MassSchedule $s) use ($date) {
                $start = Carbon::parse("{$date} {$s->start_time}");

                return [
                    'type' => 'mass',
                    'label' => $s->label ?: $this->label('mass'),
                    'reservation_id' => null,
                    'reservation_number' => null,
                    'start' => $start,
                    'end' => $start->copy()->addMinutes($this->durationMinutes('mass')),
                    'priest' => null,
                    'status' => 'scheduled',
                    'priority' => $this->priority('mass'),
                ];
            });

        return $periods->concat($templateSlots)
            ->sortBy(fn ($p) => $p['start']->timestamp)
            ->values()
            ->all();
    }

    /**
     * The free/available gaps between occupied periods for a date, within
     * the configured day window. Each entry: start (Carbon), end (Carbon),
     * duration in minutes.
     */
    public function freeSlots(string $date, ?int $excludeReservationId = null, ?int $locationId = null): array
    {
        $window = config('church_schedule.day_window');
        $dayStart = Carbon::parse("{$date} {$window['start']}");
        $dayEnd = Carbon::parse("{$date} {$window['end']}");

        $occupied = $this->occupiedPeriods($date, $excludeReservationId, $locationId);

        $slots = [];
        $cursor = $dayStart->copy();

        foreach ($occupied as $period) {
            if ($period['start']->gt($cursor)) {
                $slots[] = [
                    'start' => $cursor->copy(),
                    'end' => $period['start']->copy(),
                    'duration_minutes' => $cursor->diffInMinutes($period['start']),
                ];
            }

            if ($period['end']->gt($cursor)) {
                $cursor = $period['end']->copy();
            }
        }

        if ($cursor->lt($dayEnd)) {
            $slots[] = [
                'start' => $cursor->copy(),
                'end' => $dayEnd->copy(),
                'duration_minutes' => $cursor->diffInMinutes($dayEnd),
            ];
        }

        return array_values(array_filter($slots, fn ($s) => $s['duration_minutes'] > 0));
    }

    /**
     * The discrete list of Event Times an administrator is allowed to pick
     * for a given Event Date + reservation type — the backbone of the
     * Event Time dropdown in ReservationForm.vue, which must never accept
     * arbitrary typed input. Built from freeSlots() (which already folds
     * in Regular + Special Mass schedules, existing confirmed reservations,
     * and blocked dates/times via occupiedPeriods()/isBlocked()), sliced
     * into $stepMinutes-spaced start times that leave enough room for the
     * type's full duration before the free window ends. Returns [] when
     * the date falls inside a BlockedDate period.
     */
    public function availableSlots(
        string $date,
        string $type,
        ?int $excludeReservationId = null,
        ?int $locationId = null,
        int $stepMinutes = 15,
        array $details = []
    ): array {
        if ($this->isBlocked($date, $locationId)) {
            return [];
        }

        $needed = $this->durationMinutes($type, $details);
        $slots = [];

        foreach ($this->freeSlots($date, $excludeReservationId, $locationId) as $slot) {
            $cursor = $slot['start']->copy();

            while ($cursor->copy()->addMinutes($needed)->lte($slot['end'])) {
                $slots[] = $cursor->format('H:i');
                $cursor->addMinutes($stepMinutes);
            }
        }

        return $slots;
    }

    /**
     * A combined, chronological "what's the church doing all day" timeline
     * for the availability panel: occupied periods and free gaps
     * interleaved, exactly like the spec's example display.
     */
    public function dayTimeline(string $date, ?int $excludeReservationId = null, ?int $locationId = null): array
    {
        $occupied = collect($this->occupiedPeriods($date, $excludeReservationId, $locationId))
            ->map(fn ($p) => array_merge($p, ['kind' => 'occupied']));

        $free = collect($this->freeSlots($date, $excludeReservationId, $locationId))
            ->map(fn ($s) => array_merge($s, ['kind' => 'available']));

        return $occupied->concat($free)
            ->sortBy(fn ($p) => $p['start']->timestamp)
            ->values()
            ->map(fn ($p) => [
                'kind' => $p['kind'],
                'type' => $p['type'] ?? null,
                'label' => $p['label'] ?? 'Available',
                'start' => $p['start']->format('H:i'),
                'end' => $p['end']->format('H:i'),
                'start_label' => $p['start']->format('g:i A'),
                'end_label' => $p['end']->format('g:i A'),
                'reservation_id' => $p['reservation_id'] ?? null,
                'reservation_number' => $p['reservation_number'] ?? null,
                'priest' => $p['priest'] ?? null,
                'status' => $p['status'] ?? null,
            ])
            ->all();
    }

    /**
     * Does the requested [date, time, type] window collide with anything
     * already occupying the church? Pamisa sa Kalag never conflicts (it
     * doesn't occupy independent time). Returns the colliding period, or
     * null when the slot is clear.
     */
    public function findConflict(
        string $date,
        string $time,
        string $type,
        ?int $excludeReservationId = null,
        ?int $locationId = null,
        array $details = []
    ): ?array {
        if (! $this->occupiesChurch($type)) {
            return null;
        }

        $start = Carbon::parse("{$date} {$time}");
        $end = $start->copy()->addMinutes($this->durationMinutes($type, $details));

        foreach ($this->occupiedPeriods($date, $excludeReservationId, $locationId) as $period) {
            if ($start->lt($period['end']) && $period['start']->lt($end)) {
                return $period;
            }
        }

        return null;
    }

    /**
     * Nearest available slots long enough for this reservation type,
     * starting from the requested date/time and looking forward — first
     * within the same day, then into the following days (up to $searchDays)
     * if nothing fits. Returns up to $limit suggestions, each with a
     * human-readable label like "11:30 AM – 1:30 PM" or
     * "Tomorrow, 9:00 AM – 11:00 AM".
     */
    public function suggestSlots(
        string $date,
        string $type,
        ?int $excludeReservationId = null,
        ?int $locationId = null,
        int $limit = 3,
        int $searchDays = 14,
        array $details = []
    ): array {
        $needed = $this->durationMinutes($type, $details);
        $suggestions = [];
        $anchor = Carbon::parse($date);

        for ($dayOffset = 0; $dayOffset <= $searchDays && count($suggestions) < $limit; $dayOffset++) {
            $checkDate = $anchor->copy()->addDays($dayOffset)->toDateString();

            if ($this->isBlocked($checkDate, $locationId)) {
                continue;
            }

            foreach ($this->freeSlots($checkDate, $excludeReservationId, $locationId) as $slot) {
                if ($slot['duration_minutes'] < $needed) {
                    continue;
                }

                $suggestedStart = $slot['start']->copy();
                $suggestedEnd = $suggestedStart->copy()->addMinutes($needed);

                $suggestions[] = [
                    'date' => $checkDate,
                    'time' => $suggestedStart->format('H:i'),
                    'label' => ($dayOffset === 0 ? '' : ($dayOffset === 1 ? 'Tomorrow, ' : $suggestedStart->format('D, M j — ')))
                        .$suggestedStart->format('g:i A').' – '.$suggestedEnd->format('g:i A'),
                ];

                if (count($suggestions) >= $limit) {
                    break;
                }
            }
        }

        return $suggestions;
    }
}