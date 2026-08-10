<?php

namespace App\Support;

use App\Models\Location;

/**
 * Single source of truth for "which physical venue (location_id) does this
 * reservation actually use", given only its `type` and `details` — the same
 * two inputs ReservationDuration uses for duration.
 *
 * Before this existed, the Main-Sanctuary assignment lived only in
 * StoreReservationRequest::prepareForValidation() as a hardcoded
 * `['wedding', 'baptism', 'burial']` list. That list was incomplete —
 * First Communion and Confirmation are sacraments celebrated in the church
 * exactly like a Wedding or Baptism, but the admin form has no venue picker
 * for ANY of these five types, so a First Communion or Confirmation
 * reservation was silently saved with location_id = null. Because the
 * Church Availability engine keys its whole-church conflict detection off
 * location_id, that meant First Communion/Confirmation reservations were
 * invisible to Main Sanctuary conflict checks — a Wedding could be booked
 * directly on top of a confirmed First Communion with no warning at all.
 *
 * This class fixes that by being the one place both StoreReservationRequest
 * (on create/edit) and ReservationController::availability() (the legacy
 * per-field taken-slots endpoint) resolve a reservation's venue from, so
 * they can never drift apart again.
 */
class ReservationVenue
{
    /**
     * Reservation types that always happen at the parish's Main Sanctuary
     * and have no venue picker in the UI — the admin never chooses a venue
     * for these, so the system must assign one automatically.
     */
    public const MAIN_SANCTUARY_TYPES = [
        'wedding',
        'baptism',
        'burial',
        'first_communion',
        'confirmation',
    ];

    /**
     * Resolve the location_id a reservation of this type/details should be
     * stored with, given whatever the request already explicitly supplied
     * (e.g. a future venue picker, or an existing record's saved value).
     *
     * - An explicit, already-chosen location_id always wins.
     * - Wedding/Baptism/Burial/First Communion/Confirmation always resolve
     *   to the Main Sanctuary.
     * - School Mass resolves to the Main Sanctuary only when the admin
     *   picked details.venue === 'church'; 'on_campus' (the default)
     *   correctly resolves to null — no church venue is used, so it must
     *   never block the Main Sanctuary (see spec: "Do not block off-site
     *   events from the church").
     * - Everything else (Chapel Mass — which uses a free-text kapilya, not
     *   a Locations row — House/Business/Vehicle Blessing, Pamisa sa
     *   Kalag, etc.) resolves to null: it uses no on-site church venue.
     */
    public static function resolveLocationId(
        string $type,
        array $details = [],
        ?int $explicitLocationId = null
    ): ?int {
        if ($explicitLocationId) {
            return $explicitLocationId;
        }

        if (in_array($type, self::MAIN_SANCTUARY_TYPES, true)) {
            return self::mainSanctuaryId();
        }

        if ($type === 'school_mass' && ($details['venue'] ?? null) === 'church') {
            return self::mainSanctuaryId();
        }

        return null;
    }

    /**
     * The parish's single Main Sanctuary location id, cached for the
     * request lifecycle. Looked up by `kind` (see the add_kind_to_locations
     * migration) rather than by name, so a future rename of the venue
     * (like the 2026_08_07_000004 migration that renamed "Main Sanctuary"
     * to "Parish of the Holy Sacraments") never silently breaks this
     * resolution again.
     */
    public static function mainSanctuaryId(): ?int
    {
        static $cached = null;
        static $resolved = false;

        if (! $resolved) {
            $cached = Location::where('kind', 'main_sanctuary')->value('id');
            $resolved = true;
        }

        return $cached;
    }
}