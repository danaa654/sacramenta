<?php

namespace App\Support;

/**
 * Single source of truth for "how long does this reservation occupy the
 * church/priest", given its `type` and (optionally) its `details` payload.
 *
 * This replaces the old flat `config('reservation_requirements.durations.{type}')`
 * lookup that both ChurchAvailabilityService and SchedulingConflictService
 * used to do independently — that lookup only knew the reservation TYPE, so
 * every Wedding was treated as 90 minutes whether it was a full Nuptial Mass
 * or a shorter Liturgy of the Word, and every Baptism was treated as 60
 * minutes whether it was one child or a group of twelve.
 *
 * Falls back to the old flat per-type duration (or the global default)
 * whenever `details` is empty or doesn't contain the relevant variant
 * field — e.g. for MassSchedule template slots, which have no `details` at
 * all, or reservations created before this variant logic existed.
 */
class ReservationDuration
{
    public static function minutes(?string $type, array $details = []): int
    {
        if (! empty($details['duration_minutes']) && is_numeric($details['duration_minutes'])) {
            return (int) $details['duration_minutes'];
        }

        $flat = (int) (config("reservation_requirements.durations.{$type}")
            ?? config('reservation_requirements.durations.default', 30));

        return match ($type) {
            'wedding' => self::wedding($details, $flat),
            'baptism' => self::baptism($details, $flat),
            'first_communion' => self::firstCommunion($details, $flat),
            default => $flat,
        };
    }

    protected static function wedding(array $details, int $fallback): int
    {
        $variants = config('reservation_requirements.durations_wedding', []);
        $ceremonyType = $details['ceremony_type'] ?? null;

        return (int) ($variants[$ceremonyType] ?? $fallback);
    }

    protected static function baptism(array $details, int $fallback): int
    {
        $variants = config('reservation_requirements.durations_baptism', []);
        $isGroup = ($details['baptism_type'] ?? 'individual') === 'group';

        if (! $isGroup) {
            return (int) ($variants['individual'] ?? $fallback);
        }

        $group = $variants['group'] ?? [];
        $count = max(1, is_array($details['children'] ?? null) ? count($details['children']) : 1);

        $minutes = (int) ($group['base'] ?? $fallback) + $count * (int) ($group['per_child'] ?? 0);

        return isset($group['max']) ? min($minutes, (int) $group['max']) : $minutes;
    }

    protected static function firstCommunion(array $details, int $fallback): int
    {
        $variants = config('reservation_requirements.durations_first_communion', []);
        $isBatch = ($details['booking_mode'] ?? 'individual') === 'school_batch';

        if (! $isBatch) {
            return (int) ($variants['individual'] ?? $fallback);
        }

        $batch = $variants['school_batch'] ?? [];
        $count = max(1, is_array($details['students'] ?? null) ? count($details['students']) : 1);

        $minutes = (int) ($batch['base'] ?? $fallback) + $count * (int) ($batch['per_student'] ?? 0);

        return isset($batch['max']) ? min($minutes, (int) $batch['max']) : $minutes;
    }
}