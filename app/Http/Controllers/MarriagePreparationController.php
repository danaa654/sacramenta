<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\MarriagePreparationSchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The explicit "Regenerate Suggested Schedule" action (requirement #5).
 * Deliberately its own tiny controller, separate from the normal
 * requirements/seminar update endpoints — this is the ONE place that's
 * allowed to overwrite a manually-adjusted date, and only after the admin
 * has confirmed the warning dialog client-side.
 */
class MarriagePreparationController extends Controller
{
    public function __construct(protected MarriagePreparationSchedulingService $scheduler)
    {
    }

    /**
     * "Accept Suggestion" (requirement #5/#9) — the admin agrees with the
     * automatically-found rehearsal slot as-is. Just flips the checklist
     * item's meta.status from 'suggested' to 'scheduled'; the date/time/
     * venue/facilitator the search already found are left untouched, and
     * schedule_source stays 'generated' (this isn't a manual override, so
     * a later Wedding Date change is still allowed to re-run the search
     * and refresh it — see MarriagePreparationSchedulingService::generate()).
     */
    public function acceptRehearsal(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->type === 'wedding', 404);

        $requirement = $reservation->requirements()->where('key', 'wedding_rehearsal')->first();

        if (! $requirement) {
            return back()->with('error', 'No Wedding Rehearsal checklist item found for this reservation.');
        }

        if (empty($requirement->meta['rehearsal_date']) || empty($requirement->meta['rehearsal_time'])) {
            return back()->with('error', 'There is no suggested rehearsal schedule to accept yet.');
        }

        $requirement->update([
            'meta' => array_merge($requirement->meta ?? [], ['status' => 'scheduled']),
        ]);

        return back()->with('success', 'Wedding Rehearsal schedule accepted.');
    }

    /**
     * "Accept Suggestion" for the Canonical Interview — same idea as
     * acceptRehearsal(): just flips meta.status from 'suggested' to
     * 'scheduled', leaving the auto-suggested date/time/venue/facilitator
     * untouched and schedule_source as 'generated' (still open to being
     * refreshed by a later Wedding Date change).
     */
    public function acceptInterview(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->type === 'wedding', 404);

        $requirement = $reservation->requirements()->where('key', 'canonical_interview')->first();

        if (! $requirement || empty($requirement->meta['interview_date'])) {
            return back()->with('error', 'There is no suggested Canonical Interview schedule to accept yet.');
        }

        $requirement->update([
            'meta' => array_merge($requirement->meta ?? [], ['status' => 'scheduled']),
        ]);

        return back()->with('success', 'Canonical Interview schedule accepted.');
    }

    /**
     * "Accept Suggestion" for Marriage Banns — flips meta.status to
     * 'scheduled', keeping the three auto-suggested announcement dates.
     */
    public function acceptBanns(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->type === 'wedding', 404);

        $requirement = $reservation->requirements()->where('key', 'marriage_banns')->first();

        if (! $requirement || empty($requirement->meta['banns_date_1'])) {
            return back()->with('error', 'There is no suggested Marriage Banns schedule to accept yet.');
        }

        $requirement->update([
            'meta' => array_merge($requirement->meta ?? [], ['status' => 'scheduled']),
        ]);

        return back()->with('success', 'Marriage Banns schedule accepted.');
    }

    /**
     * "Accept Suggestion" for the Pre-Cana Seminar — accepts the
     * auto-suggested seminar as-is (date/time/venue already saved by
     * MarriagePreparationSchedulingService::applyPreCanaSeminar()); this
     * just confirms it, mirroring SeminarController::store() but without
     * requiring the admin to reopen the full schedule form.
     */
    public function acceptPreCana(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->type === 'wedding', 404);

        $seminar = $reservation->seminar;

        if (! $seminar || ! $seminar->seminar_date) {
            return back()->with('error', 'There is no suggested Pre-Cana schedule to accept yet.');
        }

        $seminar->update(['status' => \App\Models\WeddingSeminar::STATUS_SCHEDULED]);

        $requirement = $reservation->requirements()->where('key', 'pre_cana_seminar')->first();
        $requirement?->update(['status' => 'in_progress']);

        return back()->with('success', 'Pre-Cana seminar schedule accepted.');
    }

    public function regenerate(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->type === 'wedding', 404);

        if (! $reservation->event_date) {
            return back()->with('error', 'Set the Wedding Date first — suggested schedules are calculated from it.');
        }

        $warnings = $this->scheduler->generate($reservation, overwriteManual: true);

        if (! empty($warnings)) {
            return back()->with('warning', 'Suggested schedule regenerated, but please review: '.implode(' ', $warnings));
        }

        return back()->with('success', 'Suggested marriage preparation schedule regenerated. Review and adjust as needed before confirming the reservation.');
    }
}