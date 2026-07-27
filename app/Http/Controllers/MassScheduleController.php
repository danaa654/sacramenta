<?php

namespace App\Http\Controllers;

use App\Models\Priest;
use App\Models\Reservation;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lightweight admin summary for the auto-generated regular Mass schedule.
 *
 * Regular Masses are generated already-confirmed with no priest assigned
 * (see GenerateMassSchedule) and deliberately do NOT fire a notification
 * per occurrence — 30+/week individual alerts would be noise. Instead,
 * admins come here to see everything still needing a celebrant assigned
 * and fill it in directly, one list instead of a flood of notifications.
 */
class MassScheduleController extends Controller
{
    public function __construct(protected NotificationDispatcher $notifier)
    {
    }

    /**
     * GET /masses/unassigned[?weeks=2]
     *
     * Lists confirmed, auto-generated Masses (type = 'mass') with no
     * priest yet, from today through the given window (default 2 weeks
     * — far enough to plan ahead, close enough to stay actionable).
     */
    public function unassigned(Request $request): Response
    {
        $weeks = max(1, (int) $request->integer('weeks', 2));

        $start = now()->startOfDay();
        $end = now()->addWeeks($weeks)->endOfDay();

        $masses = Reservation::query()
            ->where('type', 'mass')
            ->where('status', 'confirmed') // cancelled Masses no longer need a celebrant
            ->whereNull('priest_id')
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->with('location:id,name')
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get()
            ->groupBy(fn (Reservation $r) => $r->event_date->toDateString());

        return Inertia::render('Masses/Unassigned', [
            'masses' => $masses,
            'priests' => Priest::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'weeks' => $weeks,
        ]);
    }

    /**
     * PATCH /masses/{reservation}/assign-priest
     *
     * Quick single-field assignment from the summary list — deliberately
     * separate from ReservationController::update, which expects the
     * full staff-booking form payload that auto-generated Masses never
     * have (no contact/details fields to speak of).
     */
    public function assignPriest(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->type === 'mass', 404);

        $validated = $request->validate([
            'priest_id' => ['nullable', Rule::exists('priests', 'id')],
        ]);

        $reservation->update(['priest_id' => $validated['priest_id'] ?? null]);

        return back()->with('success', 'Priest assignment updated.');
    }

    /**
     * PATCH /masses/{reservation}/cancel
     *
     * Cancels a single occurrence of the regular Mass schedule (e.g. a
     * typhoon, a priest emergency) WITHOUT deleting the row. This matters:
     * GenerateMassSchedule's idempotency check (firstOrCreate keyed on
     * mass_schedule_id + event_date) only skips regenerating a date if a
     * row for it already exists — deleting it would let the nightly
     * regeneration silently recreate the very Mass that was just
     * cancelled. Marking it `cancelled` instead keeps the row in place
     * (and in the record) while blocking regeneration.
     *
     * Restricted to type = 'mass' for now — cancelling other reservation
     * types (weddings, baptisms, etc.) goes through the normal status
     * update flow instead.
     */
    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->type === 'mass', 404);

        $reservation->update(['status' => 'cancelled']);

        $this->notifier->notifyAdmins(
            kind: 'cancelled',
            title: 'Mass cancelled',
            body: 'The '.$reservation->event_date->format('M j').' '.
                \Carbon\Carbon::parse($reservation->event_time)->format('g:i A').' Mass was cancelled.',
            reservation: $reservation,
            except: $request->user()
        );

        return back()->with('success', 'Mass cancelled.');
    }

    /**
     * PATCH /masses/{reservation}/restore
     *
     * Reverses an accidental or no-longer-needed cancellation, putting the
     * occurrence back to confirmed (still unassigned, same as generation).
     */
    public function restore(Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->type === 'mass', 404);

        $reservation->update(['status' => 'confirmed']);

        return back()->with('success', 'Mass restored.');
    }
}