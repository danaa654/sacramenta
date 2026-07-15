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
    public function durationFor(?string $type): int
    {
        return config("reservation_requirements.durations.{$type}")
            ?? config('reservation_requirements.durations.default', 30);
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
        ?int $excludeId = null
    ): ?Reservation {
        return $this->findConflict(
            Reservation::query()->where('priest_id', $priestId),
            $date,
            $time,
            $type,
            $excludeId
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
        ?int $excludeId = null
    ): ?Reservation {
        return $this->findConflict(
            Reservation::query()
                ->where('type', 'chapel_mass')
                ->where('details->chapel', $chapel),
            $date,
            $time,
            $type,
            $excludeId
        );
    }

    protected function findConflict(
        Builder $query,
        string $date,
        string $time,
        string $type,
        ?int $excludeId
    ): ?Reservation {
        $duration = $this->durationFor($type);
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
                $existingEnd = $existingStart->copy()->addMinutes($this->durationFor($existing->type));

                return $start->lt($existingEnd) && $existingStart->lt($end);
            });
    }
}