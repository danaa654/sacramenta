<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\WeddingSeminar;
use App\Services\SchedulingConflictService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Schedules/edits/completes the Pre-Cana / Marriage Preparation Seminar
 * for a wedding reservation. Deliberately separate from the wedding's own
 * Event Date/Event Time (ReservationController::update / updateStatus) —
 * see App\Models\WeddingSeminar.
 */
class SeminarController extends Controller
{
    public function __construct(protected SchedulingConflictService $conflicts)
    {
    }

    protected function rules(): array
    {
        return [
            'seminar_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'venue' => ['required', 'string', Rule::in(WeddingSeminar::VENUE_OPTIONS)],
            'venue_other' => ['required_if:venue,Other', 'nullable', 'string', 'max:255'],
            'facilitators' => ['nullable', 'array'],
            'facilitators.*.type' => ['required_with:facilitators', 'string', Rule::in(array_keys(WeddingSeminar::FACILITATOR_TYPES))],
            'facilitators.*.name' => ['required_with:facilitators', 'string', 'max:255'],
            'facilitators.*.priest_id' => ['nullable', 'integer', 'exists:priests,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Create or replace the seminar schedule for this wedding. A wedding
     * only ever has one active seminar row (see the unique constraint on
     * `reservation_id`), so scheduling for the first time and rescheduling
     * both come through here via updateOrCreate.
     */
    public function store(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->type === 'wedding', 404);

        $validated = $request->validate($this->rules());

        if ($conflict = $this->findConflict($validated, $reservation->seminar?->id)) {
            return back()->withErrors(['schedule' => $conflict])->withInput();
        }

        $reservation->seminar()->updateOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'seminar_date' => $validated['seminar_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'venue' => $validated['venue'],
                'venue_other' => $validated['venue'] === 'Other' ? ($validated['venue_other'] ?? null) : null,
                'facilitators' => $validated['facilitators'] ?? [],
                'notes' => $validated['notes'] ?? null,
                'status' => WeddingSeminar::STATUS_SCHEDULED,
                'completed_at' => null,
            ]
        );

        $this->syncChecklistStatus($reservation, 'scheduled');

        return back()->with('success', 'Pre-Cana seminar scheduled.');
    }

    /**
     * Same validation/conflict-checking as store(), for editing an
     * already-scheduled seminar (excludes itself from the conflict check).
     */
    public function update(Request $request, Reservation $reservation, WeddingSeminar $seminar): RedirectResponse
    {
        abort_unless($seminar->reservation_id === $reservation->id, 404);

        $validated = $request->validate($this->rules());

        if ($conflict = $this->findConflict($validated, $seminar->id)) {
            return back()->withErrors(['schedule' => $conflict])->withInput();
        }

        $seminar->update([
            'seminar_date' => $validated['seminar_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'venue' => $validated['venue'],
            'venue_other' => $validated['venue'] === 'Other' ? ($validated['venue_other'] ?? null) : null,
            'facilitators' => $validated['facilitators'] ?? [],
            'notes' => $validated['notes'] ?? null,
            'status' => WeddingSeminar::STATUS_SCHEDULED,
        ]);

        $this->syncChecklistStatus($reservation, 'scheduled');

        return back()->with('success', 'Pre-Cana seminar updated.');
    }

    /**
     * Explicitly mark the seminar Completed. Never automatic — scheduling
     * a seminar only ever sets it to Scheduled; an admin has to come back
     * and confirm it actually happened (see the "Requirement completion
     * counter" note in the request: a scheduled seminar must not count as
     * completed on its own).
     */
    public function complete(Reservation $reservation, WeddingSeminar $seminar): RedirectResponse
    {
        abort_unless($seminar->reservation_id === $reservation->id, 404);

        $seminar->update([
            'status' => WeddingSeminar::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $this->syncChecklistStatus($reservation, 'completed');

        return back()->with('success', 'Pre-Cana seminar marked completed.');
    }

    public function destroy(Reservation $reservation, WeddingSeminar $seminar): RedirectResponse
    {
        abort_unless($seminar->reservation_id === $reservation->id, 404);

        $seminar->delete();

        $this->syncChecklistStatus($reservation, 'pending');

        return back()->with('success', 'Pre-Cana seminar schedule removed.');
    }

    /**
     * Checks both venue and facilitator conflicts, returning a
     * human-readable message for the first one found (or null if clear).
     */
    protected function findConflict(array $validated, ?int $excludeSeminarId): ?string
    {
        $venueConflict = $this->conflicts->findSeminarVenueConflict(
            $validated['venue'],
            $validated['venue_other'] ?? null,
            $validated['seminar_date'],
            $validated['start_time'],
            $validated['end_time'],
            $excludeSeminarId
        );

        if ($venueConflict) {
            $venueLabel = $venueConflict->display_venue;
            $couple = $venueConflict->reservation?->contact_name ?? 'another wedding';

            return "{$venueLabel} is already reserved for a Pre-Cana seminar ({$couple}) from ".
                \Carbon\Carbon::parse($venueConflict->start_time)->format('g:i A').' to '.
                \Carbon\Carbon::parse($venueConflict->end_time)->format('g:i A').' on '.
                $venueConflict->seminar_date->format('F j, Y').'.';
        }

        $facilitatorConflict = $this->conflicts->findSeminarFacilitatorConflict(
            $validated['facilitators'] ?? [],
            $validated['seminar_date'],
            $validated['start_time'],
            $validated['end_time'],
            $excludeSeminarId
        );

        if ($facilitatorConflict instanceof WeddingSeminar) {
            $couple = $facilitatorConflict->reservation?->contact_name ?? 'another wedding';

            return "This facilitator is already assigned to another Pre-Cana seminar ({$couple}) from ".
                \Carbon\Carbon::parse($facilitatorConflict->start_time)->format('g:i A').' to '.
                \Carbon\Carbon::parse($facilitatorConflict->end_time)->format('g:i A').'.';
        }

        if ($facilitatorConflict instanceof \App\Models\Reservation) {
            $label = ucfirst(str_replace('_', ' ', $facilitatorConflict->type));

            return "This facilitator is already assigned to another event during this time — {$label} — {$facilitatorConflict->event_time}.";
        }

        return null;
    }

    /**
     * Keep the `pre_cana_seminar` checklist row (the thing that actually
     * gates confirming the wedding) in step with the seminar's own
     * status, without letting "Scheduled" read as "Completed" on the
     * checklist.
     */
    protected function syncChecklistStatus(Reservation $reservation, string $seminarState): void
    {
        $requirement = $reservation->requirements()->where('key', 'pre_cana_seminar')->first();

        if (! $requirement) {
            return;
        }

        $status = match ($seminarState) {
            'scheduled' => 'scheduled',
            'completed' => 'completed',
            default => 'pending',
        };

        // The checklist column only distinguishes pending/in_progress/
        // completed/not_required — "scheduled" reads as in_progress there
        // (something is underway but not done), while the seminar's own
        // record keeps the more specific "Scheduled" label shown on the
        // Wedding Requirements page.
        $requirement->update([
            'status' => $status === 'scheduled' ? 'in_progress' : $status,
        ]);
    }
}