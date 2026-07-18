<?php

namespace App\Http\Controllers;

use App\Models\Priest;
use App\Models\Reservation;
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

        return Inertia::render('Calendar/Index', [
            'reservations' => $reservations,
            'priests' => Priest::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'colors' => config('calendar.colors'),
            'defaultColor' => config('calendar.default_color'),
            'month' => $month,
            'year' => $year,
        ]);
    }
}