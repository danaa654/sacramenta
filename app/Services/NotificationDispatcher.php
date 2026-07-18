<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use App\Notifications\ReservationActivityNotification;
use Illuminate\Notifications\Notifiable;

/**
 * This system has no client-facing accounts — every reservation is entered
 * by office staff themselves, so "notifications" here means keeping OTHER
 * logged-in admins/staff in the loop, not alerting an outside requester.
 */
class NotificationDispatcher
{
    public function notifyAdmins(
        string $kind,
        string $title,
        string $body,
        ?Reservation $reservation = null,
        ?User $except = null
    ): void {
        User::query()
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->get()
            ->each->notify(new ReservationActivityNotification($kind, $title, $body, $reservation));
    }
}