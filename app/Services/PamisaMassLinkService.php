<?php

namespace App\Services;

use App\Models\Reservation;
use App\Notifications\ReservationActivityNotification;

/**
 * Keeps Pamisa sa Kalag reservations honest about the Mass occurrence
 * they're attached to. Pamisa sa Kalag never books its own church time —
 * it rides on an existing Mass Schedule slot (see
 * Reservation::linkedMass()) — so if that Mass is later cancelled or its
 * date/time changes, the Pamisa sa Kalag reservation must not silently
 * keep showing the old schedule. Instead it's marked "Needs Review" and
 * every admin is notified, so a person actually decides whether to move
 * the intention to another Mass.
 */
class PamisaMassLinkService
{
    public function __construct(protected NotificationDispatcher $notifier)
    {
    }

    /**
     * Flags every non-cancelled Pamisa sa Kalag reservation linked to the
     * given Mass occurrence as needing review, and notifies admins once
     * per affected reservation.
     */
    public function flagForReview(Reservation $massReservation, string $reason): void
    {
        $affected = $massReservation->pamisaIntentions()
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($affected as $pamisa) {
            $pamisa->update([
                'mass_link_needs_review' => true,
                'mass_link_review_reason' => $reason,
            ]);

            $this->notifier->notifyAdmins(
                kind: 'mass_link_needs_review',
                title: 'Pamisa sa Kalag needs review',
                body: "{$pamisa->display_name}'s Pamisa sa Kalag Mass schedule needs review — {$reason}",
                reservation: $pamisa,
                priority: ReservationActivityNotification::PRIORITY_WARNING,
                actionLabel: 'Review Schedule',
            );
        }
    }

    /**
     * Clears the Needs Review flag once an admin has re-attached the
     * reservation to a valid Mass occurrence (see
     * ReservationController::update()/store() re-validating the link).
     */
    public function clearReview(Reservation $pamisa): void
    {
        if ($pamisa->mass_link_needs_review) {
            $pamisa->update(['mass_link_needs_review' => false, 'mass_link_review_reason' => null]);
        }
    }
}