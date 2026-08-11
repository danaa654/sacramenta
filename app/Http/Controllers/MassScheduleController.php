<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Priest;
use App\Models\Reservation;
use App\Services\NotificationDispatcher;
use App\Services\SchedulingConflictService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mass Schedule admin module.
 *
 * Covers BOTH kinds of Reservation rows with type = 'mass':
 *
 *  - Regular Masses: generated already-confirmed with no priest assigned
 *    by GenerateMassSchedule from a `mass_schedules` weekly template.
 *  - Special Masses: created directly here via store() — one-off events
 *    like a Fiesta Mass, or a "repeat daily" series like Simbang Gabi.
 *    These carry a `title` (shown instead of the generic Parish Office
 *    contact name) and, if created as a series, share a `series_id`.
 *
 * Regular Masses deliberately do NOT fire a notification per occurrence —
 * 30+/week individual alerts would be noise. Special Masses do, since
 * they're rarer and admin-initiated.
 */
class MassScheduleController extends Controller
{
    public function __construct(
        protected NotificationDispatcher $notifier,
        protected SchedulingConflictService $conflicts,
        protected \App\Services\ChurchAvailabilityService $availability,
    ) {
    }

    /**
     * GET /masses/unassigned[?weeks=2]
     *
     * Lists ALL confirmed Masses (type = 'mass', regular + special) from
     * today through the given window, grouped by date — the "Mass
     * Schedule" page. Each row carries `needs_priest` so the UI can still
     * highlight the ones still needing a celebrant.
     */
    public function unassigned(Request $request): Response
    {
        $weeks = max(1, (int) $request->integer('weeks', 2));
        $searching = $request->boolean('searching');

        $query = Reservation::query()
            ->where('type', 'mass')
            ->whereIn('status', ['confirmed', 'cancelled'])
            ->with(['location:id,name', 'priest:id,name']);

        if ($searching) {
            // While the admin is actively searching, load every upcoming
            // Mass (no upper date bound) instead of just the 1/2/4-week
            // window, so e.g. a December Simbang Gabi is still findable
            // from an August visit. Filtering by name/priest/date itself
            // happens client-side (see Unassigned.vue) against this
            // wider set.
            $query->where('event_date', '>=', now()->toDateString());
        } else {
            $start = now()->startOfDay();
            $end = now()->addWeeks($weeks)->endOfDay();
            $query->whereBetween('event_date', [$start->toDateString(), $end->toDateString()]);
        }

        $masses = $query
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
     * POST /masses
     *
     * Creates a Special Mass — either a single occurrence, or (when
     * `repeat_until` is given) a daily series sharing one `series_id`, one
     * per date from `event_date` through `repeat_until` inclusive.
     *
     * Every occurrence is checked for BOTH a Main Church conflict (the
     * church can only host one Mass at a time, regardless of priest) and
     * a priest conflict (a different priest is expected on different
     * nights of a novena, so this is checked per-occurrence rather than
     * once), and the whole submission is rejected if ANY occurrence would
     * collide on either — better to surface it up front than partially
     * create the series.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'event_date' => ['required', 'date'],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:event_date'],
            'event_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'priest_id' => ['nullable', Rule::exists('priests', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $churchId = Location::where('name', config('church_schedule.main_sanctuary_name', 'Parish of the Holy Sacraments'))->value('id');

        $dates = [];
        $cursor = \Carbon\Carbon::parse($validated['event_date']);
        $last = \Carbon\Carbon::parse($validated['repeat_until'] ?? $validated['event_date']);

        while ($cursor->lte($last)) {
            $dates[] = $cursor->toDateString();
            $cursor = $cursor->copy()->addDay();
        }

        $details = [
            'duration_minutes' => $validated['duration_minutes'],
            'notes' => $validated['notes'] ?? null,
            'is_special' => true,
        ];

        // The Main Church is a single shared resource — it can only ever
        // host one Mass at a time, independent of which priest (if any)
        // is assigned. Checked for every occurrence up front (same as the
        // priest check below), so a multi-day series is rejected as a
        // whole rather than partially created. Uses the same Church
        // Availability & Conflict Detection Engine that Reservations use
        // (ChurchAvailabilityService::findConflict), so a Mass Schedule
        // entry and e.g. a Wedding booked into the Main Church are
        // checked against each other, not just against other Masses.
        foreach ($dates as $date) {
            $conflict = $this->availability->findConflict(
                $date,
                $validated['event_time'],
                'mass',
                null,
                $churchId,
                $details
            );

            if ($conflict) {
                $conflictTime = $conflict['start']->format('g:i A').' – '.$conflict['end']->format('g:i A');

                return back()
                    ->withInput()
                    ->withErrors(['event_time' => "Schedule Conflict — The Main Church already has \"{$conflict['label']}\" scheduled from {$conflictTime} on ".\Carbon\Carbon::parse($date)->format('M j').'. Another Mass cannot be scheduled during this time.']);
            }
        }

        if (! empty($validated['priest_id'])) {
            foreach ($dates as $date) {
                $conflict = $this->conflicts->findPriestConflict(
                    (int) $validated['priest_id'],
                    $date,
                    $validated['event_time'],
                    'mass',
                    null,
                    $details
                );

                if ($conflict) {
                    $priestName = Priest::find($validated['priest_id'])?->name ?? 'This priest';
                    $conflictTime = \Carbon\Carbon::parse($conflict->event_time)->format('g:i A');

                    return back()
                        ->withInput()
                        ->withErrors(['priest_id' => "Schedule Conflict — {$priestName} is already assigned to \"{$conflict->display_name}\" on ".\Carbon\Carbon::parse($date)->format('M j')." at {$conflictTime}."]);
                }
            }
        }

        $seriesId = count($dates) > 1 ? (string) Str::uuid() : null;

        foreach ($dates as $date) {
            Reservation::create([
                'type' => 'mass',
                'title' => $validated['title'],
                'series_id' => $seriesId,
                'contact_name' => $validated['title'],
                'contact_mobile' => 'N/A',
                'event_date' => $date,
                'event_time' => $validated['event_time'],
                'priest_id' => $validated['priest_id'] ?? null,
                'location_id' => $churchId,
                'status' => 'confirmed',
                'details' => $details,
            ]);
        }

        $this->notifier->notifyAdmins(
            kind: 'new_reservation',
            title: 'Special Mass scheduled',
            body: count($dates) > 1
                ? "{$validated['title']} was scheduled for ".count($dates)." dates starting ".\Carbon\Carbon::parse($dates[0])->format('M j').'.'
                : "{$validated['title']} was scheduled for ".\Carbon\Carbon::parse($dates[0])->format('M j').'.',
            except: $request->user()
        );

        return back()->with('success', count($dates) > 1
            ? count($dates).' Mass occurrences scheduled.'
            : 'Mass scheduled.');
    }

    /**
     * PATCH /masses/{reservation}/assign-priest
     *
     * Quick single-field assignment from the summary list — deliberately
     * separate from ReservationController::update, which expects the
     * full staff-booking form payload that auto-generated Masses never
     * have (no contact/details fields to speak of).
     *
     * Checks for a priest conflict the same way normal reservation
     * confirmation does, so a priest can't be double-booked between a
     * Mass and a Wedding/Baptism/etc. at an overlapping time.
     */
    public function assignPriest(Request $request, Reservation $reservation): RedirectResponse
    {
        abort_unless($reservation->type === 'mass', 404);

        $validated = $request->validate([
            'priest_id' => ['nullable', Rule::exists('priests', 'id')],
        ]);

        $priestId = $validated['priest_id'] ?? null;

        if ($priestId && $reservation->event_time) {
            $conflict = $this->conflicts->findPriestConflict(
                (int) $priestId,
                $reservation->event_date->format('Y-m-d'),
                substr((string) $reservation->event_time, 0, 5),
                $reservation->type,
                $reservation->id,
                $reservation->details ?? []
            );

            if ($conflict) {
                $priestName = Priest::find($priestId)?->name ?? 'This priest';
                $conflictTime = \Carbon\Carbon::parse($conflict->event_time)->format('g:i A');

                return back()->withErrors([
                    'priest_id' => "Schedule Conflict — {$priestName} is already assigned to \"{$conflict->display_name}\" at {$conflictTime} on the same date.",
                ]);
            }
        }

        $reservation->update(['priest_id' => $priestId]);

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