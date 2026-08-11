<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationRequirement;
use App\Models\WeddingSeminar;
use Carbon\Carbon;

/**
 * SMART AUTOMATIC SUGGESTION + FULL ADMIN CONTROL.
 *
 * Computes suggested dates for the four marriage-preparation activities
 * (Canonical Interview, Pre-Cana Seminar, Marriage Banns, Wedding
 * Rehearsal) from a wedding's Event Date, using the offsets in
 * config/marriage_preparation_rules.php, and writes them onto the
 * existing storage each activity already has:
 *
 *  - Canonical Interview & Wedding Rehearsal -> ReservationRequirement.meta
 *  - Marriage Banns                          -> ReservationRequirement.date_started / date_completed
 *  - Pre-Cana Seminar                        -> WeddingSeminar
 *
 * Nothing here is ever mandatory. generate() never overwrites an item an
 * admin has manually adjusted (schedule_source = 'manual') unless
 * $overwriteManual is explicitly true — that's the "Regenerate Suggested
 * Schedule" confirmation flow (see MarriagePreparationController::regenerate).
 */
class MarriagePreparationSchedulingService
{
    public function __construct(protected SchedulingConflictService $conflicts)
    {
    }

    /**
     * Pure calculation — no database writes. Returns the suggested date
     * (and, for banns, date range) for each activity, keyed the same way
     * config/marriage_preparation_rules.php is. Used both by generate()
     * below and by anything that just wants a preview.
     */
    public function suggest(Carbon $weddingDate): array
    {
        $rules = config('marriage_preparation_rules');

        return [
            'canonical_interview' => [
                'date' => $weddingDate->copy()->subDays($rules['canonical_interview']['offset_days']),
                'time' => $rules['canonical_interview']['default_time'],
                'venue' => $rules['canonical_interview']['default_venue'],
            ],
            'pre_cana_seminar' => [
                'date' => $weddingDate->copy()->subDays($rules['pre_cana_seminar']['offset_days']),
                'start_time' => $rules['pre_cana_seminar']['default_start_time'],
                'end_time' => $rules['pre_cana_seminar']['default_end_time'],
                'venue' => $rules['pre_cana_seminar']['default_venue'],
            ],
            'marriage_banns' => [
                'third' => $weddingDate->copy()->subDays($rules['marriage_banns']['third_offset_days']),
                'second' => $weddingDate->copy()->subDays($rules['marriage_banns']['third_offset_days'] + $rules['marriage_banns']['interval_days']),
                'first' => $weddingDate->copy()->subDays($rules['marriage_banns']['third_offset_days'] + (2 * $rules['marriage_banns']['interval_days'])),
                'venue' => $rules['marriage_banns']['default_venue'],
            ],
            // NOTE: this is only a naive preview (no conflict search) —
            // applyWeddingRehearsal() below runs the real
            // findAvailableRehearsalSlot() search and is what actually
            // gets saved.
            'wedding_rehearsal' => [
                'date' => $weddingDate->copy()->subDays($rules['wedding_rehearsal']['offset_days']),
                'time' => $rules['wedding_rehearsal']['default_time'],
                'venue' => $rules['wedding_rehearsal']['default_venue'],
            ],
        ];
    }

    /**
     * Generate (or regenerate) suggested schedules for a wedding
     * reservation and save them.
     *
     * - Only ever touches wedding reservations that have an Event Date.
     * - By default ($overwriteManual = false — the normal "Wedding Date
     *   was just set/changed" path) skips any activity an admin has
     *   already manually adjusted, so editing the wedding date later
     *   never silently reverts someone's manual change (requirement #4).
     * - With $overwriteManual = true (the explicit "Regenerate Suggested
     *   Schedule" button, after the admin confirms the warning dialog —
     *   requirement #5), every activity is recalculated, manual or not.
     *
     * Returns an array of soft conflict warnings (activity => message),
     * for display only — generation itself is never blocked by a
     * conflict; the admin resolves it by editing that activity, at which
     * point the same conflict check runs again and does block the save.
     */
    public function generate(Reservation $reservation, bool $overwriteManual = false): array
    {
        if ($reservation->type !== 'wedding' || ! $reservation->event_date) {
            return [];
        }

        $suggested = $this->suggest($reservation->event_date->copy());
        $warnings = [];

        $reservation->loadMissing('requirements', 'seminar');

        $this->applyCanonicalInterview($reservation, $suggested['canonical_interview'], $overwriteManual, $warnings);
        $this->applyMarriageBanns($reservation, $suggested['marriage_banns'], $overwriteManual);
        $this->applyPreCanaSeminar($reservation, $suggested['pre_cana_seminar'], $overwriteManual, $warnings);
        $this->applyWeddingRehearsal($reservation, $suggested['wedding_rehearsal'], $overwriteManual, $warnings);

        return $warnings;
    }

    /**
     * A marriage-preparation activity date must fall before the Wedding
     * Date (requirement #7 — "INVALID SCHEDULE"). Used both by the
     * controller when an admin manually edits a single activity, and
     * available for the frontend to call ahead of submit.
     *
     * Returns an error message, or null if the date is valid.
     */
    public function validateBeforeWedding(Reservation $reservation, string $activityLabel, ?string $date): ?string
    {
        if (! $date || ! $reservation->event_date) {
            return null;
        }

        if (! Carbon::parse($date)->lt($reservation->event_date)) {
            return "The {$activityLabel} date must occur before the Wedding Date (".
                $reservation->event_date->format('F j, Y').').';
        }

        return null;
    }

    protected function applyCanonicalInterview(Reservation $reservation, array $suggestion, bool $overwriteManual, array &$warnings): void
    {
        $requirement = $reservation->requirements->firstWhere('key', 'canonical_interview');

        if (! $requirement || (! $overwriteManual && $requirement->schedule_source === 'manual')) {
            return;
        }

        $requirement->update([
            'meta' => array_merge($requirement->meta ?? [], [
                'interview_date' => $suggestion['date']->toDateString(),
                'interview_time' => $suggestion['time'],
                'venue' => $requirement->meta['venue'] ?? $suggestion['venue'],
                'facilitator' => $requirement->meta['facilitator'] ?? ($reservation->priest?->name ?? ''),
                'status' => 'suggested',
            ]),
            'status' => $requirement->status === 'pending' ? 'pending' : $requirement->status,
            'schedule_source' => 'generated',
        ]);
    }

    /**
     * Marriage Banns are 3 separate announcement dates (not a start/end
     * range) — see requirement #3. Stored in `meta` alongside a
     * 'suggested'/'scheduled' status, the same pattern as the Wedding
     * Rehearsal, so the admin has to explicitly Accept or Adjust before
     * it counts as final.
     */
    protected function applyMarriageBanns(Reservation $reservation, array $suggestion, bool $overwriteManual): void
    {
        $requirement = $reservation->requirements->firstWhere('key', 'marriage_banns');

        if (! $requirement || (! $overwriteManual && $requirement->schedule_source === 'manual')) {
            return;
        }

        $requirement->update([
            'meta' => array_merge($requirement->meta ?? [], [
                'banns_date_1' => $suggestion['first']->toDateString(),
                'banns_date_2' => $suggestion['second']->toDateString(),
                'banns_date_3' => $suggestion['third']->toDateString(),
                'parish' => $requirement->meta['parish'] ?? $suggestion['venue'],
                'status' => 'suggested',
            ]),
            // Legacy range columns kept in sync (1st -> 3rd) for anything
            // still reading date_started/date_completed directly.
            'date_started' => $suggestion['first']->toDateString(),
            'date_completed' => $suggestion['third']->toDateString(),
            'schedule_source' => 'generated',
        ]);
    }

    /**
     * Requirement #2/#3/#4 — unlike the other three activities, the
     * rehearsal suggestion isn't just an offset from the Wedding Date: it
     * has to actually be free (Main Church AND assigned priest both
     * available) before it's offered to the admin. This runs the search
     * in findAvailableRehearsalSlot() and saves whatever it finds —
     * a free slot with status 'suggested', or, if the whole search window
     * is booked solid, status 'unavailable' with a warning (requirement
     * #10) so the admin knows to pick a schedule manually instead of the
     * system silently double-booking the church.
     */
    protected function applyWeddingRehearsal(Reservation $reservation, array $suggestion, bool $overwriteManual, array &$warnings): void
    {
        $requirement = $reservation->requirements->firstWhere('key', 'wedding_rehearsal');

        if (! $requirement || (! $overwriteManual && $requirement->schedule_source === 'manual')) {
            return;
        }

        $venue = $requirement->meta['venue'] ?? $suggestion['venue'] ?? $reservation->location?->name ?? 'Main Church';
        $facilitator = $requirement->meta['facilitator'] ?? ($reservation->priest?->name ?? '');
        $duration = (int) (config('marriage_preparation_rules.wedding_rehearsal.duration_minutes') ?? 60);

        $slot = $this->findAvailableRehearsalSlot($reservation, $venue, $requirement->id);

        if ($slot === null) {
            $requirement->update([
                'meta' => array_merge($requirement->meta ?? [], [
                    'rehearsal_date' => null,
                    'rehearsal_time' => null,
                    'rehearsal_end_time' => null,
                    'duration_minutes' => $duration,
                    'venue' => $venue,
                    'facilitator' => $facilitator,
                    // Displayed in the UI as "Pending" (see requirement's
                    // status vocabulary: Suggested/Pending/Scheduled/
                    // Completed) — internally distinguished so the panel
                    // can also surface the "no schedule found" warning.
                    'status' => 'unavailable',
                ]),
                'schedule_source' => 'generated',
            ]);

            $warnings['wedding_rehearsal'] = 'No available Main Church and priest schedule was found for the Wedding Rehearsal before the wedding date. Please manually select another schedule.';

            return;
        }

        $requirement->update([
            'meta' => array_merge($requirement->meta ?? [], [
                'rehearsal_date' => $slot['date'],
                'rehearsal_time' => $slot['time'],
                'rehearsal_end_time' => Carbon::parse($slot['time'])->addMinutes($duration)->format('H:i'),
                'duration_minutes' => $duration,
                'venue' => $venue,
                'facilitator' => $facilitator,
                'status' => 'suggested',
            ]),
            'schedule_source' => 'generated',
        ]);
    }

    /**
     * Requirement #4 — try 2 days before the wedding first, then 1, then
     * 3 (config: offset_days_priority), and for each day try every
     * configured time in order (config: time_candidates — 5:00 PM, 6:00
     * PM, 7:00 PM, then 4:00 PM as a last resort) until a slot is found
     * where BOTH the Main Church venue and the assigned priest are free.
     * Returns ['date' => 'Y-m-d', 'time' => 'H:i'] for the first free
     * slot found, or null if nothing in the whole search window is free.
     */
    protected function findAvailableRehearsalSlot(Reservation $reservation, string $venueName, ?int $excludeRequirementId): ?array
    {
        $rules = config('marriage_preparation_rules.wedding_rehearsal');
        $duration = (int) ($rules['duration_minutes'] ?? 60);
        $locationId = $reservation->location_id ?? \App\Support\ReservationVenue::mainSanctuaryId();
        $priestId = $reservation->priest_id;
        $weddingDate = $reservation->event_date->copy();

        foreach ($rules['offset_days_priority'] ?? [2, 1, 3] as $daysBefore) {
            $candidateDate = $weddingDate->copy()->subDays($daysBefore);

            // A rehearsal can never land on or after the wedding itself.
            if (! $candidateDate->lt($weddingDate)) {
                continue;
            }

            foreach ($rules['time_candidates'] ?? [$rules['default_time'] ?? '17:00'] as $time) {
                $conflict = $this->conflicts->findRehearsalSlotConflict(
                    $candidateDate->toDateString(),
                    $time,
                    $duration,
                    $venueName,
                    $locationId,
                    $priestId,
                    $excludeRequirementId,
                    $reservation->id
                );

                if (! $conflict) {
                    return ['date' => $candidateDate->toDateString(), 'time' => $time];
                }
            }
        }

        return null;
    }

    protected function applyPreCanaSeminar(Reservation $reservation, array $suggestion, bool $overwriteManual, array &$warnings): void
    {
        $existing = $reservation->seminar;

        if ($existing && ! $overwriteManual && $existing->schedule_source === 'manual') {
            return;
        }

        $venue = $existing->venue ?? $suggestion['venue'];
        $startTime = $existing->start_time ?? $suggestion['start_time'];
        $endTime = $existing->end_time ?? $suggestion['end_time'];
        $date = $suggestion['date']->toDateString();

        $conflict = $this->conflicts->findSeminarVenueConflict(
            $venue,
            null,
            $date,
            $startTime,
            $endTime,
            $existing?->id
        );

        if ($conflict) {
            $warnings['pre_cana_seminar'] = "{$venue} is already reserved for another Pre-Cana seminar during the suggested time on ".
                $suggestion['date']->format('F j, Y').'. Please review and adjust.';
        }

        $reservation->seminar()->updateOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'seminar_date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'venue' => $venue,
                'facilitators' => $existing->facilitators ?? [],
                'status' => $existing->status ?? WeddingSeminar::STATUS_PENDING,
                'schedule_source' => 'generated',
            ]
        );

        // Suggesting a schedule is not the same as scheduling it — leave
        // the linked checklist status alone (still Pending) until the
        // admin actually confirms/edits it via the normal Seminar flow.
    }
}