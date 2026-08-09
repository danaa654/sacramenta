<?php

namespace App\Services;

use App\Models\Reservation;
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

        return $query
            ->where('status', 'confirmed')
            ->whereDate('event_date', $date)
            ->whereNotNull('event_time')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->first(function (Reservation $existing) use ($start, $end) {
                $existingStart = Carbon::parse(
                    $existing->event_date->format('Y-m-d').' '.$existing->event_time
                );
                $existingEnd = $existingStart->copy()->addMinutes($this->durationFor($existing->type, $existing->details ?? []));

                return $start->lt($existingEnd) && $existingStart->lt($end);
            });
    }
}