<?php

namespace App\Http\Controllers;

use App\Models\MassSchedule;
use App\Models\Priest;
use App\Models\Reservation;
use App\Models\WeddingSeminar;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Renders the month-grid calendar. `month`/`year` are plain query params
     * (not a JSON API) so the page is bookmarkable/shareable, e.g.
     * /calendar?month=8&year=2026.
     *
     * Data-loading choice: month navigation uses Inertia's partial reload
     * (`router.get(..., { only: ['reservations', 'month', 'year'] })`) from
     * the frontend rather than either (a) a full Inertia visit re-fetching
     * every prop, or (b) a separate hand-rolled JSON endpoint. Reasoning:
     *   - A month's worth of reservations is a small payload (typically well
     *     under 100 rows), so there's no real cost concern either way.
     *   - The `priests` list doesn't change when you flip months, so a full
     *     Inertia visit would needlessly re-fetch and re-serialize it every
     *     time. Inertia's partial reload support (`only`) already solves
     *     this natively — asking the same controller action to return just
     *     the props that changed — so there's no need to hand-build and
     *     maintain a second JSON route that duplicates this controller's
     *     query logic.
     *   - A separate JSON endpoint would only pay off if the calendar needed
     *     a fundamentally different response shape (e.g. infinite scroll
     *     fetching many months at once), which isn't the case here.
     */
    public function index(Request $request): Response
    {
        $month = (int) $request->integer('month', now()->month);
        $year = (int) $request->integer('year', now()->year);

        // Guard against out-of-range values from a hand-edited URL.
        $month = max(1, min(12, $month));

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $reservations = Reservation::with('priest:id,name', 'location:id,name')
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->get();

        // The sidebar's "Weekly Masses" list used to be a hand-maintained JS
        // array that mirrored MassScheduleSeeder by hand and had drifted out
        // of sync with the real, editable mass_schedules table. Load the
        // actual template rows here instead so the sidebar always reflects
        // whatever is really in the database.
        $massSchedules = MassSchedule::with('location:id,name')
            ->where('is_active', true)
            ->orderBy('start_time')
            ->get(['id', 'label', 'days_of_week', 'start_time', 'end_time', 'language', 'is_livestreamed', 'location_id']);

        // Pre-Cana seminars are their own schedule (see WeddingSeminar),
        // not a Reservation row, so the month's worth are loaded and
        // handed to the calendar separately rather than the wedding's
        // own event_date/event_time. Never a Scheduled/Completed status
        // check here for filtering — a "Pending" seminar has no date yet
        // so the whereBetween on seminar_date already excludes it.
        $seminars = WeddingSeminar::with('reservation:id,contact_name')
            ->whereBetween('seminar_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('seminar_date')
            ->orderBy('start_time')
            ->get();

        // Canonical Interview / Marriage Banns / Wedding Rehearsal — same
        // idea as the Pre-Cana seminars above, but their dates live in
        // ReservationRequirement (meta / date_started / date_completed —
        // see MarriagePreparationSchedulingService) rather than their own
        // table. Loaded here as its own small set so each shows as its
        // own visually-distinguishable calendar chip, never merged into
        // the Wedding event itself (requirement #14/#15).
        $marriagePrep = $this->marriagePrepEvents($start, $end);

        return Inertia::render('Calendar/Index', [
            'reservations' => $reservations,
            'seminars' => $seminars,
            'marriagePrep' => $marriagePrep,
            'priests' => Priest::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'massSchedules' => $massSchedules,
            'colors' => config('calendar.colors'),
            'defaultColor' => config('calendar.default_color'),
            'month' => $month,
            'year' => $year,
        ]);
    }

    /**
     * Flat list of Canonical Interview / Marriage Banns / Wedding
     * Rehearsal calendar entries falling in the given month, one row per
     * activity per wedding. `schedule_source` is passed through so the
     * frontend can render a still-suggested (not yet reviewed) date more
     * lightly than one the admin has confirmed.
     */
    protected function marriagePrepEvents(Carbon $start, Carbon $end): array
    {
        $items = \App\Models\ReservationRequirement::query()
            ->whereIn('key', ['canonical_interview', 'wedding_rehearsal'])
            ->whereHas('reservation', fn ($q) => $q->where('type', 'wedding'))
            ->with('reservation:id,contact_name')
            ->get()
            ->map(function ($r) {
                $isInterview = $r->key === 'canonical_interview';
                $date = $isInterview ? ($r->meta['interview_date'] ?? null) : ($r->meta['rehearsal_date'] ?? null);
                $time = $isInterview ? ($r->meta['interview_time'] ?? null) : ($r->meta['rehearsal_time'] ?? null);

                if (! $date) {
                    return null;
                }

                return [
                    'id' => "{$r->key}-{$r->id}",
                    'type' => $r->key,
                    'date' => $date,
                    'time' => $time,
                    'venue' => $r->meta['venue'] ?? null,
                    'schedule_source' => $r->schedule_source,
                    'reservation_id' => $r->reservation_id,
                    'contact_name' => $r->reservation?->contact_name,
                ];
            })
            ->filter()
            ->filter(fn ($e) => $e['date'] >= $start->toDateString() && $e['date'] <= $end->toDateString())
            ->values();

        $banns = \App\Models\ReservationRequirement::query()
            ->where('key', 'marriage_banns')
            ->whereHas('reservation', fn ($q) => $q->where('type', 'wedding'))
            ->whereNotNull('date_started')
            ->with('reservation:id,contact_name')
            ->get()
            ->filter(fn ($r) => $r->date_started->toDateString() <= $end->toDateString()
                && ($r->date_completed?->toDateString() ?? $r->date_started->toDateString()) >= $start->toDateString())
            ->map(fn ($r) => [
                'id' => "marriage_banns-{$r->id}",
                'type' => 'marriage_banns',
                'date' => $r->date_started->toDateString(),
                'end_date' => $r->date_completed?->toDateString(),
                'schedule_source' => $r->schedule_source,
                'reservation_id' => $r->reservation_id,
                'contact_name' => $r->reservation?->contact_name,
            ])
            ->values();

        return $items->concat($banns)->values()->all();
    }
}