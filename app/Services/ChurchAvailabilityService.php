<?php

namespace App\Services;

use App\Models\BlockedDate;
use App\Models\Location;
use App\Models\MassSchedule;
use App\Models\Reservation;
use App\Support\ReservationDuration;
use Carbon\Carbon;

/**
 * Church Availability & Conflict Detection Engine.
 *
 * IMPORTANT: not every reservation happens in the Main Sanctuary. Sacramenta
 * has multiple physical spaces a reservation can occupy — the Main
 * Sanctuary, a named Chapel, or (for a School Mass held on campus) nowhere
 * on parish property at all. The rule this engine enforces is:
 *
 *   SAME VENUE + OVERLAPPING TIME  = conflict
 *   DIFFERENT VENUE + OVERLAPPING TIME = no venue conflict
 *
 * resolveVenue() is the single place that decides which physical venue (if
 * any) a reservation occupies, given its type, its `details` payload, and
 * its `location_id`:
 *
 *   - Wedding / Baptism / Burial / First Communion / Confirmation / regular
 *     Masses always resolve to the Main Sanctuary (config
 *     `church_schedule.main_sanctuary_types`), auto-assigned by
 *     StoreReservationRequest so this also works before location_id is set.
 *   - Chapel Mass resolves to a venue keyed by its `details.chapel` name —
 *     two different chapels never conflict with each other or the Main
 *     Sanctuary.
 *   - School Mass resolves to the Main Sanctuary ONLY when
 *     `details.venue === 'church'`; "On Campus (gym/auditorium)" resolves
 *     to no venue at all, so it never blocks — or is blocked by — anything
 *     happening at the church.
 *   - An explicit `location_id` (any Location record) always wins.
 *   - House/Business/Vehicle Blessing, Anointing of the Sick, Spiritual
 *     Direction, Special Intention, "Others", and Pamisa sa Kalag resolve
 *     to no venue — they happen off-site or ride on an existing Mass slot,
 *     so they never occupy, and never conflict with, a church venue.
 *
 * This service powers the live "available time slots" panel, live conflict
 * warnings, and nearest-available-slot suggestions in ReservationForm.vue.
 */
class ChurchAvailabilityService
{
    /**
     * Statuses that actually hold a slot. Mirrors SchedulingConflictService:
     * drafts are tentative and may overlap each other; once something is
     * confirmed it becomes authoritative and blocks everything else.
     */
    protected const BLOCKING_STATUSES = ['confirmed'];

    /**
     * Request-lifetime cache of occupiedPeriods() results, keyed by
     * "{date}|{excludeReservationId}". Several call sites — most notably
     * suggestSlots() (up to 14 days) and MassScheduleController::store()
     * for a multi-day recurring series (one query per date, per candidate
     * day) — end up asking for the exact same date's occupied periods
     * multiple times within a single request. occupiedPeriods() re-runs
     * both a Reservation query and a MassSchedule::where('is_active', true)
     * ->get() every time it's called, so without this cache a 14-day
     * suggestion search alone issues up to 28 avoidable queries. Safe to
     * cache per-request only (not across requests) since a save elsewhere
     * in the SAME request could otherwise be missed — cleared automatically
     * when the request ends.
     */
    protected array $occupiedPeriodsCache = [];

    /**
     * Drop the request-lifetime occupiedPeriods() cache for a specific
     * date (or every cached date, when omitted). Call this after writing
     * a Reservation/MassSchedule row if the SAME request will go on to
     * ask this engine about that date again — every current call site
     * (ReservationController::store/update, MassScheduleController::store)
     * only reads availability *before* writing, so this isn't required
     * today, but it's here so a future call site that reads-after-write
     * in one request doesn't silently get stale cached results.
     */
    public function clearCache(?string $date = null): void
    {
        if ($date === null) {
            $this->occupiedPeriodsCache = [];

            return;
        }

        foreach (array_keys($this->occupiedPeriodsCache) as $key) {
            if (str_starts_with($key, "{$date}|")) {
                unset($this->occupiedPeriodsCache[$key]);
            }
        }
    }

    public function durationMinutes(string $type, array $details = []): int
    {
        return ReservationDuration::minutes($type, $details);
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

    /**
     * Whether this reservation TYPE is even capable of occupying a church
     * venue. This is a coarse, type-only check (matches the frontend's
     * CHURCH_OCCUPYING_TYPES list) used to decide whether to bother asking
     * the engine at all. Whether a given reservation actually DOES occupy
     * a venue — and which one — is resolveVenue()'s job.
     */
    public function occupiesChurch(string $type): bool
    {
        return in_array($type, config('church_schedule.occupying_types', []), true);
    }

    /**
     * The single Main Sanctuary venue descriptor. Resolves the real
     * Location row when one exists (so it participates in location_id-keyed
     * conflict checks too), but degrades gracefully to a synthetic key if
     * the row is missing so the engine never crashes on an un-seeded DB.
     */
    public function mainSanctuaryVenue(): array
    {
        $name = config('church_schedule.main_sanctuary_name', 'Parish of the Holy Sacraments');
        $location = Location::where('name', $name)->first();

        return [
            'key' => $location ? "location:{$location->id}" : 'main_sanctuary',
            'label' => $name,
            'kind' => 'main_sanctuary',
        ];
    }

    /**
     * Which physical venue (if any) a reservation with this type/details/
     * location_id occupies. Returns null when the reservation doesn't
     * occupy any shared church venue at all (category 4: "No church venue
     * usage") — e.g. a House Blessing, or a School Mass held on campus.
     *
     * An explicit $locationId always wins (an admin who deliberately picks
     * a venue is always trusted over the type-based default).
     */
    public function resolveVenue(string $type, array $details = [], ?int $locationId = null): ?array
    {
        if ($locationId) {
            $location = Location::find($locationId);

            if (! $location) {
                return null;
            }

            $mainName = config('church_schedule.main_sanctuary_name', 'Parish of the Holy Sacraments');

            return [
                'key' => "location:{$location->id}",
                'label' => $location->name,
                'kind' => $location->name === $mainName
                    ? 'main_sanctuary'
                    : (str_contains(strtolower($location->name), 'chapel') ? 'chapel' : 'other'),
            ];
        }

        if (in_array($type, ['mass', 'special_mass'], true)) {
            return $this->mainSanctuaryVenue();
        }

        if (in_array($type, config('church_schedule.main_sanctuary_types', []), true)) {
            return $this->mainSanctuaryVenue();
        }

        if ($type === 'chapel_mass') {
            $chapel = trim((string) ($details['chapel'] ?? ''));

            return $chapel !== '' ? [
                'key' => 'chapel:'.strtolower($chapel),
                'label' => $chapel,
                'kind' => 'chapel',
            ] : null;
        }

        if ($type === 'school_mass' && ($details['venue'] ?? 'on_campus') === 'church') {
            return $this->mainSanctuaryVenue();
        }

        // house_blessing, business_blessing, vehicle_blessing,
        // anointing_of_the_sick, spiritual_direction, special_intention,
        // pamisa_sa_kalag, "others", and a School Mass held on campus all
        // occupy no shared church venue.
        return null;
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
     * Every occupied period on the parish calendar for a given date, across
     * EVERY venue, sorted by start time. Each entry: type, label, start
     * (Carbon), end (Carbon), reservation_id, priest name, status,
     * priority, venue_key, venue_label, venue_kind. Reservations that
     * resolve to no venue (resolveVenue() === null) are left out entirely —
     * they don't occupy anything, so they can't appear in an "occupied"
     * list. Callers that care about a SPECIFIC venue (freeSlots,
     * findConflict, etc.) filter this list down by venue_key themselves.
     */
    public function occupiedPeriods(string $date, ?int $excludeReservationId = null): array
    {
        $cacheKey = $date.'|'.($excludeReservationId ?? '');

        if (array_key_exists($cacheKey, $this->occupiedPeriodsCache)) {
            return $this->occupiedPeriodsCache[$cacheKey];
        }

        $reservations = Reservation::query()
            ->with('priest:id,name')
            ->whereDate('event_date', $date)
            ->whereNotNull('event_time')
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->whereIn('type', config('church_schedule.occupying_types', []))
            ->when($excludeReservationId, fn ($q) => $q->where('id', '!=', $excludeReservationId))
            ->get();

        $periods = $reservations
            ->map(function (Reservation $r) {
                $venue = $this->resolveVenue($r->type, $r->details ?? [], $r->location_id);

                if (! $venue) {
                    return null;
                }

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
                    'venue_key' => $venue['key'],
                    'venue_label' => $venue['label'],
                    'venue_kind' => $venue['kind'],
                ];
            })
            ->filter()
            ->values();

        // Standing weekly Mass template rows (MassSchedule) that haven't
        // materialized into an actual Reservation row yet (e.g. the daily
        // GenerateMassSchedule command hasn't reached this date), so the
        // engine still blocks against the parish's default Mass schedule
        // even for dates further out. Skips any weekday slot that already
        // has a matching reservation above, to avoid double-counting.
        // Regular Masses always happen in the Main Sanctuary.
        $weekday = Carbon::parse($date)->dayOfWeek; // Carbon: Sun=0..Sat=6, matches MassSchedule days_of_week
        $reservedTimes = $reservations->pluck('event_time')->map(fn ($t) => substr((string) $t, 0, 5))->all();
        $mainVenue = $this->mainSanctuaryVenue();

        $templateSlots = MassSchedule::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (MassSchedule $s) => in_array($weekday, $s->days_of_week ?? [], true))
            ->filter(fn (MassSchedule $s) => ! in_array(substr($s->start_time, 0, 5), $reservedTimes, true))
            ->map(function (MassSchedule $s) use ($date, $mainVenue) {
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
                    'venue_key' => $mainVenue['key'],
                    'venue_label' => $mainVenue['label'],
                    'venue_kind' => $mainVenue['kind'],
                ];
            });

        return $this->occupiedPeriodsCache[$cacheKey] = $periods->concat($templateSlots)
            ->sortBy(fn ($p) => $p['start']->timestamp)
            ->values()
            ->all();
    }

    /**
     * occupiedPeriods() filtered down to a single venue (matched by
     * venue_key) — the actual list a same-venue overlap check should run
     * against. A null $venue means "this reservation doesn't occupy any
     * venue", so there is nothing to filter against.
     */
    protected function occupiedPeriodsForVenue(string $date, ?int $excludeReservationId, ?array $venue): array
    {
        if (! $venue) {
            return [];
        }

        return array_values(array_filter(
            $this->occupiedPeriods($date, $excludeReservationId),
            fn ($p) => $p['venue_key'] === $venue['key']
        ));
    }

    /**
     * The free/available gaps for a date WITHIN A SPECIFIC VENUE, within
     * the configured day window. Each entry: start (Carbon), end (Carbon),
     * duration in minutes. A null $venue (the reservation type/details
     * don't resolve to any shared church venue) means nothing can conflict
     * with it, so the entire day window counts as free.
     */
    public function freeSlots(string $date, ?int $excludeReservationId = null, ?array $venue = null): array
    {
        $window = config('church_schedule.day_window');
        $dayStart = Carbon::parse("{$date} {$window['start']}");
        $dayEnd = Carbon::parse("{$date} {$window['end']}");

        if (! $venue) {
            return [[
                'start' => $dayStart->copy(),
                'end' => $dayEnd->copy(),
                'duration_minutes' => $dayStart->diffInMinutes($dayEnd),
            ]];
        }

        $occupied = $this->occupiedPeriodsForVenue($date, $excludeReservationId, $venue);

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
     * for a given Event Date + reservation type (+ details, + location) —
     * the backbone of the Event Time dropdown in ReservationForm.vue, which
     * must never accept arbitrary typed input. Built from freeSlots() for
     * whichever venue this [type, details, location_id] combination
     * resolves to, sliced into $stepMinutes-spaced start times that leave
     * enough room for the type's full duration before the free window
     * ends. Returns [] when the date falls inside a BlockedDate period.
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

        $venue = $this->resolveVenue($type, $details, $locationId);
        $needed = $this->durationMinutes($type, $details);
        $slots = [];

        foreach ($this->freeSlots($date, $excludeReservationId, $venue) as $slot) {
            $cursor = $slot['start']->copy();

            while ($cursor->copy()->addMinutes($needed)->lte($slot['end'])) {
                $slots[] = $cursor->format('H:i');
                $cursor->addMinutes($stepMinutes);
            }
        }

        return $slots;
    }

    /**
     * A combined, chronological "what's this venue doing all day" timeline
     * for the availability panel: occupied periods and free gaps
     * interleaved, exactly like the spec's example display — scoped to
     * whichever venue this [type, details, location_id] resolves to. When
     * it resolves to no venue at all, the timeline is simply "available"
     * for the whole day window, since nothing shared is being checked.
     */
    public function dayTimeline(
        string $date,
        ?int $excludeReservationId = null,
        ?int $locationId = null,
        ?string $type = null,
        array $details = []
    ): array {
        $venue = $type ? $this->resolveVenue($type, $details, $locationId) : null;

        $occupied = collect($this->occupiedPeriodsForVenue($date, $excludeReservationId, $venue))
            ->map(fn ($p) => array_merge($p, ['kind' => 'occupied']));

        $free = collect($this->freeSlots($date, $excludeReservationId, $venue))
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
                'venue_label' => $p['venue_label'] ?? null,
                'venue_kind' => $p['venue_kind'] ?? null,
            ])
            ->all();
    }

    /**
     * Does the requested [date, time, type, details, location] window
     * collide with anything ALREADY OCCUPYING THE SAME VENUE? Two events
     * at overlapping times in DIFFERENT venues are not a conflict. Returns
     * the colliding period (tagged with venue_label/venue_kind so the
     * caller can say "Main Sanctuary conflict" vs "Chapel conflict" vs
     * "Other venue conflict"), or null when the slot is clear — including
     * when this reservation doesn't occupy any venue at all.
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

        $venue = $this->resolveVenue($type, $details, $locationId);

        if (! $venue) {
            return null;
        }

        $start = Carbon::parse("{$date} {$time}");
        $end = $start->copy()->addMinutes($this->durationMinutes($type, $details));

        foreach ($this->occupiedPeriodsForVenue($date, $excludeReservationId, $venue) as $period) {
            if ($start->lt($period['end']) && $period['start']->lt($end)) {
                return $period;
            }
        }

        return null;
    }

    /**
     * Nearest available slots long enough for this reservation type WITHIN
     * ITS RESOLVED VENUE, starting from the requested date/time and looking
     * forward — first within the same day, then into the following days
     * (up to $searchDays) if nothing fits. Returns up to $limit
     * suggestions, each with a human-readable label like
     * "11:30 AM – 1:30 PM" or "Tomorrow, 9:00 AM – 11:00 AM".
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
        $venue = $this->resolveVenue($type, $details, $locationId);
        $needed = $this->durationMinutes($type, $details);
        $suggestions = [];
        $anchor = Carbon::parse($date);

        for ($dayOffset = 0; $dayOffset <= $searchDays && count($suggestions) < $limit; $dayOffset++) {
            $checkDate = $anchor->copy()->addDays($dayOffset)->toDateString();

            if ($this->isBlocked($checkDate, $locationId)) {
                continue;
            }

            foreach ($this->freeSlots($checkDate, $excludeReservationId, $venue) as $slot) {
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