<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Notifications\ReservationActivityNotification;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Runs on a schedule (see routes/console.php) to surface things nobody
 * actively triggered by clicking a button:
 *
 *   - drafts sitting unconfirmed past the SLA window ("pending too long")
 *   - confirmed reservations happening today ("reminder")
 *   - two confirmed reservations that ended up colliding anyway (data
 *     fixes, manual DB edits, etc. — the normal confirm flow already
 *     blocks this, so this is a safety net, not the primary defense)
 *
 * Each check is deduplicated by looking for an existing unread
 * notification of the same kind for the same reservation within the
 * lookback window, so re-running the command hourly doesn't spam.
 */
class CheckReservationNotifications extends Command
{
    protected $signature = 'notifications:check-reservations';

    protected $description = 'Notify admins about stale drafts, today\'s events, and scheduling conflicts';

    protected int $pendingSlaHours = 48;

    public function handle(NotificationDispatcher $notifier): int
    {
        $this->notifyStaleDrafts($notifier);
        $this->notifyTodaysEvents($notifier);
        $this->notifyConflicts($notifier);

        return self::SUCCESS;
    }

    protected function notifyStaleDrafts(NotificationDispatcher $notifier): void
    {
        $stale = Reservation::query()
            ->where('status', 'draft')
            ->where('created_at', '<=', now()->subHours($this->pendingSlaHours))
            ->get();

        foreach ($stale as $reservation) {
            if ($this->alreadyNotifiedToday('pending', $reservation->id)) {
                continue;
            }

            $notifier->notifyAdmins(
                kind: 'pending',
                title: 'Reservation awaiting review',
                body: "{$reservation->contact_name}'s ".str_replace('_', ' ', $reservation->type).' has been pending for over 2 days.',
                reservation: $reservation,
            );
        }
    }

    protected function notifyTodaysEvents(NotificationDispatcher $notifier): void
    {
        $today = Reservation::query()
            ->where('status', 'confirmed')
            ->whereDate('event_date', now()->toDateString())
            ->get();

        foreach ($today as $reservation) {
            if ($this->alreadyNotifiedToday('reminder', $reservation->id)) {
                continue;
            }

            $time = $reservation->event_time
                ? Carbon::parse($reservation->event_time)->format('g:i A')
                : 'later today';

            $notifier->notifyAdmins(
                kind: 'reminder',
                title: 'Upcoming today',
                body: "{$reservation->contact_name}'s ".str_replace('_', ' ', $reservation->type)." is scheduled for {$time}.",
                reservation: $reservation,
            );
        }
    }

    protected function notifyConflicts(NotificationDispatcher $notifier): void
    {
        $confirmed = Reservation::query()
            ->where('status', 'confirmed')
            ->whereNotNull('event_time')
            ->whereNotNull('priest_id')
            ->where('event_date', '>=', now()->toDateString())
            ->get()
            ->groupBy(fn (Reservation $r) => $r->priest_id.'|'.$r->event_date->toDateString().'|'.substr((string) $r->event_time, 0, 5));

        foreach ($confirmed as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $reservation = $group->first();

            if ($this->alreadyNotifiedToday('conflict', $reservation->id)) {
                continue;
            }

            $priestName = $reservation->priest?->name ?? 'A priest';

            $notifier->notifyAdmins(
                kind: 'conflict',
                title: 'Scheduling conflict',
                body: "{$priestName} is double-booked on {$reservation->event_date->format('M j')} at ".Carbon::parse($reservation->event_time)->format('g:i A').'.',
                reservation: $reservation,
            );
        }
    }

    /**
     * Cheap dedup: has *any* admin already received this kind/reservation
     * pairing within the last 24h? Good enough for a single-command sweep
     * that runs at most a few times a day.
     */
    protected function alreadyNotifiedToday(string $kind, int $reservationId): bool
    {
        return DB::table('notifications')
            ->where('type', ReservationActivityNotification::class)
            ->where('data->kind', $kind)
            ->where('data->reservation_id', $reservationId)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();
    }
}