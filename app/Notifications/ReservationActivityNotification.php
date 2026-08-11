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
 *   new_reservation           — a draft was just created and needs review
 *   pending                   — a draft has sat unconfirmed past the SLA window
 *   conflict                  — two confirmed reservations collide on priest/venue/time
 *   reminder                  — a confirmed reservation is happening today/soon
 *   cancelled                 — a reservation was deleted/cancelled
 *   confirmed                 — a reservation was confirmed (activity feed for other admins)
 *   payment                   — a payment was recorded against a reservation
 *
 *   Wedding / Marriage Preparation kinds (see CheckMarriagePreparationNotifications):
 *   wedding_seminar_upcoming     — Pre-Cana / Marriage Prep Seminar is coming up
 *   wedding_seminar_pending      — seminar scheduled but activity still Pending
 *   wedding_seminar_overdue      — seminar date has passed, still not Completed
 *   wedding_activity_pending     — Canonical Interview / Marriage Banns sitting Pending too long
 *   wedding_activity_overdue     — Canonical Interview / Marriage Banns still open close to the wedding
 *   wedding_requirements         — Marriage Preparation checklist incomplete
 *   wedding_documents            — Wedding documents still unverified
 *   wedding_upcoming             — the wedding ceremony itself is approaching
 *
 * `dedupe_key` (optional) lets a check command scope its "already sent"
 * lookup to a specific offset/bucket (e.g. the "7 days before" reminder vs
 * the "1 day before" reminder for the very same reservation+kind), so two
 * different milestones for the same activity don't collide or suppress
 * each other. Not persisted as its own column — just another key inside
 * the existing `data` JSON blob, so no migration is needed to add it.
 */
class ReservationActivityNotification extends Notification
{
    use Queueable;

    public const PRIORITY_INFO = 'info';
    public const PRIORITY_WARNING = 'warning';
    public const PRIORITY_URGENT = 'urgent';

    public function __construct(
        protected string $kind,
        protected string $title,
        protected string $body,
        protected ?Reservation $reservation = null,
        protected string $priority = self::PRIORITY_INFO,
        protected ?string $actionLabel = null,
        protected ?string $dedupeKey = null,
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
            'priority' => $this->priority,
            'action_label' => $this->actionLabel ?? 'View Reservation',
            'category' => $this->reservation?->type_label,
            'reservation_id' => $this->reservation?->id,
            'reservation_subject' => $this->reservation?->display_name,
            'dedupe_key' => $this->dedupeKey,
            'url' => $this->reservation
                ? route('reservations.show', $this->reservation)
                : null,
        ];
    }
}