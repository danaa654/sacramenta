<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\ReservationRequirement;
use App\Models\WeddingSeminar;
use App\Notifications\ReservationActivityNotification;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin-only reminders for Wedding Reservations and their Marriage
 * Preparation activities (Canonical Interview, Marriage Banns, Pre-Cana /
 * Marriage Preparation Seminar, and the wedding document checklist).
 *
 * Deliberately its own command rather than more branches inside
 * CheckReservationNotifications: that command already covers every
 * reservation type generically (stale drafts, today's events, priest
 * conflicts). This one is specific to the wedding / marriage-prep
 * workflow and reuses the same NotificationDispatcher + database
 * notifications table (see #18/#19 in the request — keep Mass Schedule,
 * generic reservation activity, and Marriage Preparation notifications
 * separate, but never duplicate the underlying notification system).
 *
 * Every notification created here carries a `dedupe_key` (see
 * ReservationActivityNotification) so a specific milestone — e.g. the
 * "7 days before" seminar reminder — doesn't collide with, or get
 * suppressed by, a different milestone for the same reservation/kind
 * (e.g. "1 day before"). Before creating anything new, each check also
 * clears out any still-unread notification that's no longer accurate
 * (item completed, date rescheduled, reservation cancelled) so the bell
 * never nags about something the admin already resolved — see
 * resolveStale().
 *
 * NOTE — "Wedding Rehearsal" from the request has no scheduled-date field
 * anywhere in the current data model (no rehearsal requirement item, no
 * rehearsal table). Adding one would mean new schema/UI beyond a
 * notifications feature, so it's intentionally left out here; everything
 * else in the request (seminar, canonical interview, marriage banns,
 * documents, the wedding itself) is fully wired up. Once a rehearsal
 * date field exists, it can reuse the same upcoming/overdue pattern as
 * the seminar below.
 */
class CheckMarriagePreparationNotifications extends Command
{
    protected $signature = 'notifications:check-marriage-preparation';

    protected $description = 'Notify admins about upcoming, pending, and overdue Wedding / Marriage Preparation activities';

    /** 7/3/1/0 days before, matching the request's suggested reminder timing. */
    protected array $upcomingOffsets = [7, 3, 1, 0];

    /** Requirement keys treated as "activities" here — pre_cana_seminar is handled via WeddingSeminar instead, since it has a real date. */
    protected array $activityKeys = ['canonical_interview', 'marriage_banns'];

    protected int $pendingTooLongDays = 7;

    protected int $overdueWithinDaysOfWedding = 14;

    public function handle(NotificationDispatcher $notifier): int
    {
        $weddings = Reservation::query()
            ->where('type', 'wedding')
            ->whereIn('status', ['draft', 'confirmed'])
            ->with(['requirements', 'seminar'])
            ->get();

        foreach ($weddings as $wedding) {
            $this->checkSeminar($notifier, $wedding);
            $this->checkPreMarriageActivities($notifier, $wedding);
            $this->checkRequirementsSummary($notifier, $wedding);
            $this->checkDocuments($notifier, $wedding);
            $this->checkUpcomingWedding($notifier, $wedding);
        }

        return self::SUCCESS;
    }

    /**
     * Pre-Cana / Marriage Preparation Seminar — the one activity with a
     * real scheduled date+time (WeddingSeminar::seminar_date/start_time),
     * so it gets the full upcoming (7/3/1/day-of) + overdue treatment.
     */
    protected function checkSeminar(NotificationDispatcher $notifier, Reservation $wedding): void
    {
        $seminar = $wedding->seminar;

        if (! $seminar || ! $seminar->seminar_date) {
            return;
        }

        $isDone = in_array($seminar->status, [WeddingSeminar::STATUS_COMPLETED, WeddingSeminar::STATUS_NOT_REQUIRED], true);

        if ($isDone) {
            $this->resolveStale($wedding->id, ['wedding_seminar_upcoming', 'wedding_seminar_overdue', 'wedding_seminar_pending']);

            return;
        }

        $seminarDate = $seminar->seminar_date->copy()->startOfDay();
        $today = now()->startOfDay();
        $time = $seminar->start_time ? Carbon::parse($seminar->start_time)->format('g:i A') : null;

        if ($seminarDate->lt($today)) {
            // Overdue: the date has passed but it's still not marked Completed.
            $this->resolveStale($wedding->id, ['wedding_seminar_upcoming']);

            if ($this->alreadySentToday('wedding_seminar_overdue', $wedding->id)) {
                return;
            }

            $notifier->notifyAdmins(
                kind: 'wedding_seminar_overdue',
                title: 'Overdue: Pre-Cana / Marriage Preparation Seminar',
                body: "{$wedding->display_name}'s Pre-Cana / Marriage Preparation Seminar was scheduled for {$seminarDate->format('M j, Y')} but is still marked {$this->statusLabel($seminar->status)}.",
                reservation: $wedding,
                priority: ReservationActivityNotification::PRIORITY_URGENT,
                actionLabel: 'Update Seminar',
                dedupeKey: 'overdue',
            );

            return;
        }

        // Still marked Pending (not yet actually scheduled a date change) — shouldn't
        // happen once seminar_date is set, but keep the pending reminder honest.
        if ($seminar->status === WeddingSeminar::STATUS_PENDING) {
            if (! $this->alreadySentToday('wedding_seminar_pending', $wedding->id)) {
                $notifier->notifyAdmins(
                    kind: 'wedding_seminar_pending',
                    title: 'Pre-Cana Seminar still Pending',
                    body: "{$wedding->display_name}'s Pre-Cana / Marriage Preparation Seminar is still marked Pending.",
                    reservation: $wedding,
                    priority: ReservationActivityNotification::PRIORITY_WARNING,
                    actionLabel: 'Update Seminar',
                    dedupeKey: 'pending',
                );
            }

            return;
        }

        // Upcoming (status = scheduled, date is today or ahead).
        $daysUntil = $today->diffInDays($seminarDate, false);

        if (! in_array($daysUntil, $this->upcomingOffsets, true)) {
            return;
        }

        $dedupeKey = "upcoming:{$daysUntil}";

        if ($this->alreadySent('wedding_seminar_upcoming', $wedding->id, $dedupeKey)) {
            return;
        }

        $when = $daysUntil === 0 ? 'today' : ($daysUntil === 1 ? 'tomorrow' : "in {$daysUntil} days");
        $priority = $daysUntil <= 1
            ? ReservationActivityNotification::PRIORITY_WARNING
            : ReservationActivityNotification::PRIORITY_INFO;

        $notifier->notifyAdmins(
            kind: 'wedding_seminar_upcoming',
            title: $daysUntil === 0 ? 'Pre-Cana Seminar today' : "Pre-Cana Seminar {$when}",
            body: "{$wedding->display_name}'s Pre-Cana / Marriage Preparation Seminar is {$when} — {$seminarDate->format('M j, Y')}".($time ? " at {$time}" : '').'.',
            reservation: $wedding,
            priority: $priority,
            actionLabel: 'View Reservation',
            dedupeKey: $dedupeKey,
        );
    }

    /**
     * Canonical Interview and Marriage Banns — no scheduled-date field in
     * the current data model (see class docblock), only a status. So
     * these get a "sitting Pending too long" reminder plus an "overdue
     * relative to the wedding date" escalation as the ceremony approaches,
     * rather than the same 7/3/1/day-of pattern as the seminar.
     */
    protected function checkPreMarriageActivities(NotificationDispatcher $notifier, Reservation $wedding): void
    {
        foreach ($this->activityKeys as $key) {
            $requirement = $wedding->requirements->firstWhere('key', $key);

            if (! $requirement || ! $requirement->is_required) {
                continue;
            }

            $isDone = in_array($requirement->status, [
                ReservationRequirement::STATUS_COMPLETED,
                ReservationRequirement::STATUS_NOT_REQUIRED,
            ], true);

            if ($isDone) {
                $this->resolveStale($wedding->id, ['wedding_activity_pending', 'wedding_activity_overdue'], $key);

                continue;
            }

            $label = $requirement->label;
            $sittingSince = $requirement->date_started ?? $requirement->created_at;
            $daysSitting = $sittingSince ? Carbon::parse($sittingSince)->diffInDays(now()) : 0;

            $weddingSoon = $wedding->event_date
                && now()->startOfDay()->diffInDays($wedding->event_date->copy()->startOfDay(), false) <= $this->overdueWithinDaysOfWedding;

            if ($weddingSoon) {
                if ($this->alreadySentToday('wedding_activity_overdue', $wedding->id, $key)) {
                    continue;
                }

                $whenText = $wedding->event_date->isPast()
                    ? "was scheduled for {$wedding->event_date->format('M j, Y')} but"
                    : "is on {$wedding->event_date->format('M j, Y')} and";

                $notifier->notifyAdmins(
                    kind: 'wedding_activity_overdue',
                    title: "Overdue: {$label}",
                    body: "{$wedding->display_name}'s wedding {$whenText} the {$label} is still marked {$this->statusLabel($requirement->status)}.",
                    reservation: $wedding,
                    priority: ReservationActivityNotification::PRIORITY_URGENT,
                    actionLabel: 'Update Reservation',
                    dedupeKey: $key,
                );

                continue;
            }

            if ($daysSitting >= $this->pendingTooLongDays) {
                if ($this->alreadySentToday('wedding_activity_pending', $wedding->id, $key)) {
                    continue;
                }

                $notifier->notifyAdmins(
                    kind: 'wedding_activity_pending',
                    title: "Action required: {$label}",
                    body: "{$wedding->display_name}'s {$label} is still marked {$this->statusLabel($requirement->status)}.",
                    reservation: $wedding,
                    priority: ReservationActivityNotification::PRIORITY_WARNING,
                    actionLabel: 'Update Reservation',
                    dedupeKey: $key,
                );
            }
        }
    }

    /**
     * Overall Marriage Preparation checklist summary (see
     * Reservation::marriage_preparation_status). Sent at most every 3 days
     * per reservation while incomplete, not on every command run — see #5
     * in the request ("do not create this notification repeatedly").
     */
    protected function checkRequirementsSummary(NotificationDispatcher $notifier, Reservation $wedding): void
    {
        $wedding->setRelation('requirements', $wedding->requirements);
        $status = $wedding->marriage_preparation_status;

        if ($status !== 'requirements_pending') {
            $this->resolveStale($wedding->id, ['wedding_requirements']);

            return;
        }

        if ($this->alreadySentWithinHours('wedding_requirements', $wedding->id, 72)) {
            return;
        }

        $required = $wedding->requirements->filter(fn (ReservationRequirement $r) => $r->is_required);
        $remaining = $required->filter(fn (ReservationRequirement $r) => $r->isBlocking())->count();
        $total = $required->count();
        $completed = $total - $remaining;

        $notifier->notifyAdmins(
            kind: 'wedding_requirements',
            title: 'Marriage Preparation incomplete',
            body: "{$wedding->display_name} — Marriage Preparation: {$completed} / {$total} completed. {$remaining} requirement".($remaining === 1 ? '' : 's').' remaining.',
            reservation: $wedding,
            priority: ReservationActivityNotification::PRIORITY_WARNING,
            actionLabel: 'View Reservation',
            dedupeKey: 'summary',
        );
    }

    /**
     * Wedding document checklist (group_key = 'documents' — baptismal
     * certificates, CENOMAR, etc.). These never block confirming the
     * reservation, but the request still wants a reminder while any are
     * missing/unverified, especially as the wedding date nears.
     */
    protected function checkDocuments(NotificationDispatcher $notifier, Reservation $wedding): void
    {
        $documents = $wedding->requirements->where('group_key', 'documents');

        if ($documents->isEmpty()) {
            return;
        }

        $total = $documents->count();
        $verified = $documents->where('status', ReservationRequirement::STATUS_COMPLETED)->count();

        if ($verified >= $total) {
            $this->resolveStale($wedding->id, ['wedding_documents']);

            return;
        }

        if ($this->alreadySentWithinHours('wedding_documents', $wedding->id, 72)) {
            return;
        }

        $remaining = $total - $verified;

        $notifier->notifyAdmins(
            kind: 'wedding_documents',
            title: 'Documents incomplete',
            body: "{$wedding->display_name} — {$verified} / {$total} documents verified. {$remaining} document".($remaining === 1 ? '' : 's').' still require verification.',
            reservation: $wedding,
            priority: ReservationActivityNotification::PRIORITY_INFO,
            actionLabel: 'Verify Documents',
            dedupeKey: 'summary',
        );
    }

    /**
     * The wedding ceremony itself approaching (7/3/1/day-of), richer than
     * the generic 'reminder' kind CheckReservationNotifications already
     * sends for every confirmed reservation type — this one is
     * wedding-specific and includes the Marriage Preparation status so
     * the admin can see at a glance whether everything's ready.
     */
    protected function checkUpcomingWedding(NotificationDispatcher $notifier, Reservation $wedding): void
    {
        if ($wedding->status !== 'confirmed' || ! $wedding->event_date) {
            return;
        }

        $today = now()->startOfDay();
        $eventDate = $wedding->event_date->copy()->startOfDay();
        $daysUntil = $today->diffInDays($eventDate, false);

        if (! in_array($daysUntil, $this->upcomingOffsets, true)) {
            return;
        }

        $dedupeKey = "upcoming:{$daysUntil}";

        if ($this->alreadySent('wedding_upcoming', $wedding->id, $dedupeKey)) {
            return;
        }

        $wedding->setRelation('requirements', $wedding->requirements);
        $required = $wedding->requirements->filter(fn (ReservationRequirement $r) => $r->is_required);
        $completed = $required->filter(fn (ReservationRequirement $r) => ! $r->isBlocking())->count();
        $total = $required->count();

        $when = $daysUntil === 0 ? 'today' : ($daysUntil === 1 ? 'tomorrow' : "in {$daysUntil} days");
        $time = $wedding->event_time ? Carbon::parse($wedding->event_time)->format('g:i A') : null;
        $priority = $daysUntil <= 1
            ? ReservationActivityNotification::PRIORITY_WARNING
            : ReservationActivityNotification::PRIORITY_INFO;

        $notifier->notifyAdmins(
            kind: 'wedding_upcoming',
            title: $daysUntil === 0 ? 'Wedding today' : "Wedding {$when}",
            body: "{$wedding->display_name} is {$when} — {$eventDate->format('M j, Y')}".($time ? " at {$time}" : '').". Marriage Preparation: {$completed} / {$total} completed.",
            reservation: $wedding,
            priority: $priority,
            actionLabel: 'View Wedding',
            dedupeKey: $dedupeKey,
        );
    }

    protected function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    /**
     * Has this exact kind+reservation+dedupe_key combination already been
     * sent, ever? Used for one-shot milestones (a specific "N days
     * before" bucket only ever needs to fire once).
     */
    protected function alreadySent(string $kind, int $reservationId, ?string $dedupeKey = null): bool
    {
        return $this->dedupeQuery($kind, $reservationId, $dedupeKey)->exists();
    }

    /** Same kind+reservation+key, but only suppresses re-sends within the last 24h (for recurring reminders like "still overdue"). */
    protected function alreadySentToday(string $kind, int $reservationId, ?string $dedupeKey = null): bool
    {
        return $this->alreadySentWithinHours($kind, $reservationId, 24, $dedupeKey);
    }

    protected function alreadySentWithinHours(string $kind, int $reservationId, int $hours, ?string $dedupeKey = null): bool
    {
        return $this->dedupeQuery($kind, $reservationId, $dedupeKey)
            ->where('created_at', '>=', now()->subHours($hours))
            ->exists();
    }

    protected function dedupeQuery(string $kind, int $reservationId, ?string $dedupeKey)
    {
        return DB::table('notifications')
            ->where('type', ReservationActivityNotification::class)
            ->where('data->kind', $kind)
            ->where('data->reservation_id', $reservationId)
            ->when($dedupeKey !== null, fn ($q) => $q->where('data->dedupe_key', $dedupeKey));
    }

    /**
     * Clears any still-UNREAD notification of the given kind(s) for this
     * reservation (optionally scoped to one dedupe_key, e.g. a specific
     * requirement). Called whenever the underlying condition is no longer
     * true — item completed, seminar rescheduled/finished, wedding
     * cancelled — so the bell stops nagging about something the admin
     * already handled (request #13–#16). Read notifications are left
     * alone: they're history, not an active reminder.
     */
    protected function resolveStale(int $reservationId, array $kinds, ?string $dedupeKey = null): void
    {
        // whereIn() against a JSON path column doesn't reliably translate
        // across DB drivers the way plain where() does — build the OR
        // explicitly instead of relying on whereIn('data->kind', ...).
        DB::table('notifications')
            ->where('type', ReservationActivityNotification::class)
            ->where('data->reservation_id', $reservationId)
            ->when($dedupeKey !== null, fn ($q) => $q->where('data->dedupe_key', $dedupeKey))
            ->whereNull('read_at')
            ->where(function ($q) use ($kinds) {
                foreach ($kinds as $kind) {
                    $q->orWhere('data->kind', $kind);
                }
            })
            ->delete();
    }
}