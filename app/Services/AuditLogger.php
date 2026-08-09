<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;

/**
 * Single call site for writing to the scheduling audit log, so every
 * action (Reservation Created/Updated/Cancelled, Conflict Prevented,
 * Conflict Overridden, Mass Schedule Updated) is recorded the same way.
 */
class AuditLogger
{
    public static function log(string $action, string $description, ?Reservation $reservation = null, array $meta = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'reservation_id' => $reservation?->id,
            'meta' => $meta,
        ]);
    }

    public static function reservationCreated(Reservation $reservation): AuditLog
    {
        return self::log(
            'reservation_created',
            "{$reservation->contact_name}'s ".str_replace('_', ' ', $reservation->type)." reservation created for {$reservation->event_date->format('M j, Y')}.",
            $reservation
        );
    }

    public static function reservationUpdated(Reservation $reservation): AuditLog
    {
        return self::log(
            'reservation_updated',
            "{$reservation->contact_name}'s ".str_replace('_', ' ', $reservation->type)." reservation was updated.",
            $reservation
        );
    }

    public static function reservationCancelled(Reservation $reservation): AuditLog
    {
        return self::log(
            'reservation_cancelled',
            "{$reservation->contact_name}'s ".str_replace('_', ' ', $reservation->type)." reservation was cancelled.",
            $reservation
        );
    }

    public static function conflictPrevented(string $description, array $meta = []): AuditLog
    {
        return self::log('conflict_prevented', $description, null, $meta);
    }

    public static function conflictOverridden(Reservation $reservation, string $reason, array $meta = []): AuditLog
    {
        return self::log(
            'conflict_overridden',
            "A scheduling conflict was overridden for {$reservation->contact_name}'s ".str_replace('_', ' ', $reservation->type).' reservation. Reason: '.$reason,
            $reservation,
            $meta
        );
    }

    public static function massScheduleUpdated(string $description, array $meta = []): AuditLog
    {
        return self::log('mass_schedule_updated', $description, null, $meta);
    }

    /**
     * A single-field change made through the Correct Record flow on a
     * completed/archived reservation — deliberately NOT the same audit
     * action as a normal reservationUpdated() edit, so the audit history
     * clearly distinguishes "an administrator corrected a locked
     * sacramental record" from routine editing. One entry per changed
     * field: the previous value is preserved here rather than being
     * silently overwritten, even though the reservation row itself now
     * holds the corrected value.
     */
    public static function fieldCorrected(
        Reservation $reservation,
        string $field,
        mixed $previousValue,
        mixed $newValue,
        string $reason
    ): AuditLog {
        return self::log(
            'reservation_corrected',
            "Correction on {$reservation->contact_name}'s ".str_replace('_', ' ', $reservation->type).
                " record — field \"{$field}\" changed. Reason: {$reason}",
            $reservation,
            [
                'field' => $field,
                'previous_value' => $previousValue,
                'new_value' => $newValue,
                'correction_reason' => $reason,
            ]
        );
    }
}