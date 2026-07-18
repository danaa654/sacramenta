<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Single, flexible notification covering every admin-facing event in the
 * reservation lifecycle. `kind` drives which icon/color the bell renders
 * client-side (see NotificationBell.vue's `icons` map) — keep these in
 * sync when adding a new kind:
 *
 *   new_reservation  — a draft was just created and needs review
 *   pending          — a draft has sat unconfirmed past the SLA window
 *   conflict         — two confirmed reservations collide on priest/venue/time
 *   reminder         — a confirmed reservation is happening today/soon
 *   cancelled        — a reservation was deleted/cancelled
 *   confirmed        — a reservation was confirmed (activity feed for other admins)
 *   payment          — a payment was recorded against a reservation
 */
class ReservationActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $kind,
        protected string $title,
        protected string $body,
        protected ?Reservation $reservation = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'reservation_id' => $this->reservation?->id,
            'url' => $this->reservation
                ? route('reservations.show', $this->reservation)
                : null,
        ];
    }
}