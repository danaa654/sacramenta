<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationRequirement;
use App\Models\WeddingSeminar;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Central place for "does this date/time collide with something else"
 * logic, shared by StoreReservationRequest (on create/edit submit),
 * ReservationController::updateStatus (on draft -> confirmed), and the
 * availability endpoint (for live UI warnings).
 *
 * Conflicts are only checked against CONFIRMED reservations — drafts are
 * allowed to overlap each other (parishes often hold multiple tentative
 * requests for the same slot before one is finalized), but the moment
 * something is confirmed, it becomes authoritative and blocks the rest.
 */
class SchedulingConflictService
{
    public function durationFor(?string $type, array $details = []): int
    {
        return \App\Support\ReservationDuration::minutes($type, $details);
    }

    /**
     * The single, structured "priest already has a conflict" message,
     * shared by every call site that reports a priest double-booking
     * (StoreReservationRequest on create/edit, ReservationController on
     * confirm and on priest reassignment, MassScheduleController on Mass
     * creation and quick priest-assign). Keeping the wording in one place
     * means fixing/adjusting the message once fixes it everywhere, instead
     * of four subtly different one-line strings drifting apart.
     */
    public function formatPriestConflictMessage(string $priestName, Reservation $conflict): string
    {
        $label = config("church_schedule.labels.{$conflict->type}", ucwords(str_replace('_', ' ', $conflict->type)));
        $start = Carbon::parse($conflict->event_date->format('Y-m-d').' '.$conflict->event_time);
        $end = $start->copy()->addMinutes($this->durationFor($conflict->type, $conflict->details ?? []));

        return "⚠️ PRIEST SCHEDULE CONFLICT\n\n"
            ."{$priestName} is already assigned to:\n\n"
            ."{$label}\n"
            .$start->format('F j, Y')."\n"
            .$start->format('g:i A').'–'.$end->format('g:i A')."\n\n"
            .'The selected schedule overlaps with this event.'."\n\n"
            .'Please select another time or assign another available priest.';
    }

    /**
     * Find a confirmed reservation that would collide with the given
     * priest + date + time window.
     */
    public function findPriestConflict(
        int $priestId,
        string $date,
        string $time,
        string $type,
        ?int $excludeId = null,
        array $details = []
    ): ?Reservation {
        return $this->findConflict(
            Reservation::query()->where('priest_id', $priestId),
            $date,
            $time,
            $type,
            $excludeId,
            $details
        );
    }

    /**
     * Find a confirmed reservation of ANY type booked at the same
     * location_id that would collide with the given date + time window.
     * This is the generalized "any room, any sacrament" version of
     * findChapelConflict — a Wedding in the Parish of the Holy Sacraments now blocks a
     * Burial from being confirmed in the Parish of the Holy Sacraments at an overlapping
     * time, not just another chapel_mass.
     */
    public function findLocationConflict(
        int $locationId,
        string $date,
        string $time,
        string $type,
        ?int $excludeId = null,
        array $details = []
    ): ?Reservation {
        return $this->findConflict(
            Reservation::query()->where('location_id', $locationId),
            $date,
            $time,
            $type,
            $excludeId,
            $details
        );
    }

    /**
     * Find a confirmed Chapel Mass reservation at the same chapel that
     * would collide with the given date + time window.
     */
    public function findChapelConflict(
        string $chapel,
        string $date,
        string $time,
        string $type,
        ?int $excludeId = null,
        array $details = []
    ): ?Reservation {
        return $this->findConflict(
            Reservation::query()
                ->where('type', 'chapel_mass')
                ->where('details->chapel', $chapel),
            $date,
            $time,
            $type,
            $excludeId,
            $details
        );
    }

    /**
     * Find a scheduled/completed Pre-Cana seminar (for a different
     * wedding) already booked at the same venue during an overlapping
     * window. Only real venues collide — "Other" seminars are compared
     * by their free-text `venue_other` value.
     */
    public function findSeminarVenueConflict(
        string $venue,
        ?string $venueOther,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeSeminarId = null
    ): ?WeddingSeminar {
        $start = Carbon::parse("{$date} {$startTime}");
        $end = Carbon::parse("{$date} {$endTime}");

        return WeddingSeminar::query()
            ->with('reservation:id,contact_name')
            ->whereIn('status', [WeddingSeminar::STATUS_SCHEDULED, WeddingSeminar::STATUS_COMPLETED])
            ->whereDate('seminar_date', $date)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->where('venue', $venue)
            ->when($venue === 'Other', fn ($q) => $q->where('venue_other', $venueOther))
            ->when($excludeSeminarId, fn ($q) => $q->where('id', '!=', $excludeSeminarId))
            ->get()
            ->first(function (WeddingSeminar $existing) use ($start, $end, $date) {
                $existingStart = Carbon::parse("{$date} {$existing->start_time}");
                $existingEnd = Carbon::parse("{$date} {$existing->end_time}");

                return $start->lt($existingEnd) && $existingStart->lt($end);
            });
    }

    /**
     * Find a conflict for a given facilitator during the seminar window —
     * either another seminar (any facilitator with the same name) or, for
     * a facilitator who is a priest already assigned in the system, any
     * confirmed Reservation that priest is booked for at an overlapping
     * time (weddings, other seminars indirectly via that seminar's own
     * check, Masses, etc). Returns the first conflicting item found, as
     * either a WeddingSeminar or a Reservation.
     */
    public function findSeminarFacilitatorConflict(
        array $facilitators,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeSeminarId = null
    ): WeddingSeminar|Reservation|null {
        $start = Carbon::parse("{$date} {$startTime}");
        $end = Carbon::parse("{$date} {$endTime}");

        $names = collect($facilitators)->pluck('name')->filter()->values();
        $priestIds = collect($facilitators)
            ->where('type', 'priest')
            ->pluck('priest_id')
            ->filter()
            ->values();

        if ($names->isNotEmpty()) {
            $seminarConflict = WeddingSeminar::query()
                ->with('reservation:id,contact_name')
                ->whereIn('status', [WeddingSeminar::STATUS_SCHEDULED, WeddingSeminar::STATUS_COMPLETED])
                ->whereDate('seminar_date', $date)
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->when($excludeSeminarId, fn ($q) => $q->where('id', '!=', $excludeSeminarId))
                ->get()
                ->first(function (WeddingSeminar $existing) use ($start, $end, $date, $names) {
                    $existingStart = Carbon::parse("{$date} {$existing->start_time}");
                    $existingEnd = Carbon::parse("{$date} {$existing->end_time}");
                    $overlaps = $start->lt($existingEnd) && $existingStart->lt($end);

                    if (! $overlaps) {
                        return false;
                    }

                    $existingNames = collect($existing->facilitators ?? [])->pluck('name')->filter();

                    return $names->intersect($existingNames)->isNotEmpty();
                });

            if ($seminarConflict) {
                return $seminarConflict;
            }
        }

        if ($priestIds->isNotEmpty()) {
            foreach ($priestIds as $priestId) {
                $reservationConflict = Reservation::query()
                    ->where('priest_id', $priestId)
                    ->where('status', 'confirmed')
                    ->whereDate('event_date', $date)
                    ->whereNotNull('event_time')
                    ->get()
                    ->first(function (Reservation $existing) use ($start, $end) {
                        $existingStart = Carbon::parse(
                            $existing->event_date->format('Y-m-d').' '.$existing->event_time
                        );
                        $existingEnd = $existingStart->copy()->addMinutes($this->durationFor($existing->type, $existing->details ?? []));

                        return $start->lt($existingEnd) && $existingStart->lt($end);
                    });

                if ($reservationConflict) {
                    return $reservationConflict;
                }
            }
        }

        return null;
    }

    /**
     * Same idea as findSeminarVenueConflict(), but for the Canonical
     * Interview and Wedding Rehearsal activities, whose date/time/venue
     * live in ReservationRequirement.meta rather than their own table
     * (see MarriagePreparationSchedulingService). Only checks other
     * wedding reservations' items of the SAME activity key, since those
     * are the only two that store a comparable date/time/venue shape.
     *
     * $dateField/$timeField name which meta keys hold the date and start
     * time for this activity ('interview_date'/'interview_time' or
     * 'rehearsal_date'/'rehearsal_time'). Duration comes from
     * config/marriage_preparation_rules.php.
     */
    public function findPrepActivityConflict(
        string $activityKey,
        string $dateField,
        string $timeField,
        int $durationMinutes,
        string $venue,
        string $date,
        string $time,
        ?int $excludeRequirementId = null
    ): ?ReservationRequirement {
        if (trim($venue) === '') {
            return null;
        }

        $start = Carbon::parse("{$date} {$time}");
        $end = $start->copy()->addMinutes($durationMinutes);

        return ReservationRequirement::query()
            ->with('reservation:id,contact_name')
            ->where('key', $activityKey)
            ->where("meta->{$dateField}", $date)
            ->where('meta->venue', $venue)
            ->when($excludeRequirementId, fn ($q) => $q->where('id', '!=', $excludeRequirementId))
            ->get()
            ->first(function (ReservationRequirement $existing) use ($start, $end, $date, $timeField, $durationMinutes) {
                $existingTime = $existing->meta[$timeField] ?? null;

                if (! $existingTime) {
                    return false;
                }

                $existingStart = Carbon::parse("{$date} {$existingTime}");
                $existingEnd = $existingStart->copy()->addMinutes($durationMinutes);

                return $start->lt($existingEnd) && $existingStart->lt($end);
            });
    }

    /**
     * Combined venue + priest availability check for a single candidate
     * Wedding Rehearsal slot, used by both the automatic suggestion
     * search (MarriagePreparationSchedulingService::applyWeddingRehearsal)
     * and the admin's manual "Adjust Schedule" save.
     *
     * Requirement #3: a rehearsal slot is only free when BOTH the Main
     * Church venue AND the assigned priest are free — either one being
     * busy makes the slot unavailable. Checks, in order:
     *
     *  A. Main Church — every OTHER reservation type that uses the same
     *     shared location_id (Mass, Wedding, Baptism, Burial, First
     *     Communion, Pamisa sa Kalag, another Wedding Rehearsal, etc.),
     *     via findLocationConflict().
     *  B. Other Wedding Rehearsals at the same venue text, via
     *     findPrepActivityConflict() — covers parishes where the
     *     rehearsal venue isn't backed by a Locations row.
     *  C. The assigned priest's own schedule across all reservation
     *     types, via findPriestConflict().
     *
     * Returns a short human-readable reason for the FIRST conflict found,
     * or null when the slot is free.
     */
    public function findRehearsalSlotConflict(
        string $date,
        string $time,
        int $durationMinutes,
        string $venue,
        ?int $locationId,
        ?int $priestId,
        ?int $excludeRequirementId = null,
        ?int $excludeReservationId = null
    ): ?string {
        if ($locationId) {
            $conflict = $this->findLocationConflict($locationId, $date, $time, 'wedding_rehearsal', $excludeReservationId, ['duration_minutes' => $durationMinutes]);
            if ($conflict) {
                return "{$venue} is already occupied by another scheduled event ({$conflict->contact_name}'s ".str_replace('_', ' ', $conflict->type).') at that time.';
            }
        }

        $prepConflict = $this->findPrepActivityConflict(
            'wedding_rehearsal', 'rehearsal_date', 'rehearsal_time',
            $durationMinutes, $venue, $date, $time, $excludeRequirementId
        );
        if ($prepConflict) {
            $who = $prepConflict->reservation?->contact_name ?? 'another couple';
            return "{$venue} is already booked for {$who}'s Wedding Rehearsal at that time.";
        }

        if ($priestId) {
            $priestConflict = $this->findPriestConflict($priestId, $date, $time, 'wedding_rehearsal', $excludeReservationId, ['duration_minutes' => $durationMinutes]);
            if ($priestConflict) {
                return "The assigned priest is already scheduled for {$priestConflict->contact_name}'s ".str_replace('_', ' ', $priestConflict->type)." at that time.";
            }
        }

        return null;
    }

    protected function findConflict(
        Builder $query,
        string $date,
        string $time,
        string $type,
        ?int $excludeId,
        array $details = []
    ): ?Reservation {
        $duration = $this->durationFor($type, $details);
        $start = Carbon::parse("{$date} {$time}");
        $end = $start->copy()->addMinutes($duration);
        $massId = $details['linked_mass_reservation_id'] ?? null;

        return $query
            ->where('status', 'confirmed')
            ->whereDate('event_date', $date)
            ->whereNotNull('event_time')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->first(function (Reservation $existing) use ($start, $end, $type, $massId, $excludeId) {
                if ($this->sharesMassSlot($type, $massId, $excludeId, $existing)) {
                    return false;
                }

                $existingStart = Carbon::parse(
                    $existing->event_date->format('Y-m-d').' '.$existing->event_time
                );
                $existingEnd = $existingStart->copy()->addMinutes($this->durationFor($existing->type, $existing->details ?? []));

                return $start->lt($existingEnd) && $existingStart->lt($end);
            });
    }

    /**
     * Pamisa sa Kalag requests deliberately "piggyback" on an existing
     * regular Mass — many separate Pamisa sa Kalag reservations (and the
     * underlying Mass reservation itself) are all legitimately confirmed
     * for the exact same location + date + time. Without this check, the
     * generic findLocationConflict()/findConflict() logic would treat every
     * one of those as colliding with each other and refuse to let more than
     * one be confirmed — which is wrong, since sharing the slot is the
     * whole point of the feature.
     *
     * Two reservations are considered part of the same Mass slot (and
     * therefore NOT a conflict with each other) when either:
     *  - the reservation being checked is a Pamisa sa Kalag linked to a
     *    Mass, and $existing IS that linked Mass reservation, or
     *  - both are Pamisa sa Kalag requests linked to the same Mass, or
     *  - the reservation being checked is the Mass itself (id === $excludeId
     *    when confirming a Mass), and $existing is a Pamisa sa Kalag linked
     *    to it.
     */
    protected function sharesMassSlot(string $type, ?int $massId, ?int $excludeId, Reservation $existing): bool
    {
        if ($type === 'pamisa_sa_kalag' && $massId) {
            if ($existing->id === $massId) {
                return true;
            }

            if ($existing->type === 'pamisa_sa_kalag' && $existing->linked_mass_reservation_id === $massId) {
                return true;
            }
        }

        if ($type === 'mass' && $excludeId && $existing->type === 'pamisa_sa_kalag' && $existing->linked_mass_reservation_id === $excludeId) {
            return true;
        }

        return false;
    }
}