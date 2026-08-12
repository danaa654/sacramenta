<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

/**
 * Every Sacramenta route so far only required auth+verified — any signed-in
 * user could hard-delete a confirmed reservation, correct a locked/completed
 * sacramental record, or override a schedule conflict just by submitting
 * override_conflict=1, with no elevated permission required for any of it.
 *
 * This policy gates exactly those three destructive/override actions to
 * admins. Everything else (create, edit, confirm, cancel-and-restore,
 * checklist updates, etc.) stays available to any authenticated staff
 * member, since day-to-day parish office work needs to keep flowing without
 * an admin in the loop for every reservation.
 *
 * Laravel resolves this automatically for Reservation via naming
 * convention (App\Models\Reservation -> App\Policies\ReservationPolicy) —
 * no manual registration needed.
 */
class ReservationPolicy
{
    /**
     * Hard-delete a reservation (ReservationController::destroy). Note
     * that completed/archived reservations are already blocked from
     * deletion entirely, by the controller, regardless of role — this
     * only decides whether the *deletable* (draft/confirmed, not-yet-
     * happened) ones can be removed.
     */
    public function delete(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin();
    }

    /**
     * The audited "Correct Record" flow on an already-locked (completed/
     * archived) sacramental record (ReservationController::correct).
     */
    public function correct(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin();
    }

    /**
     * Submitting override_conflict=1 to push a reservation through despite
     * a detected schedule conflict (blocked date, venue overlap, or priest
     * double-booking) — see StoreReservationRequest::checkChurchAvailability()
     * / checkSchedulingConflict(). This check has no specific Reservation
     * instance yet at validation time (the conflict is against a candidate
     * date/time, not an existing row), so it takes no model argument.
     */
    public function overrideConflict(User $user): bool
    {
        return $user->isAdmin();
    }
}