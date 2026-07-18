<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Homepage / parishioner-facing calendar. Deliberately a separate
 * controller (not a "guest mode" branch of CalendarController) so the
 * query and the payload shape can never accidentally leak staff-only
 * fields — only date/time/type/location ever leave this class.
 *
 * Visible here: CONFIRMED reservations whose type is in
 * config('calendar.public_types') (Mass schedules, community baptism
 * slots, confession hours). Everything else — who booked it, contact
 * info, fees, priest notes, draft/pending items — never appears.
 */
class PublicCalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $month = (int) $request->integer('month', now()->month);
        $year = (int) $request->integer('year', now()->year);
        $month = max(1, min(12, $month));

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $events = Reservation::query()
            ->where('status', 'confirmed')
            ->whereIn('type', config('calendar.public_types', []))
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('event_date')
            ->orderBy('event_time')
            ->with('location:id,name')
            ->get()
            ->map(fn (Reservation $r) => [
                'type' => $r->type,
                'event_date' => $r->event_date->toDateString(),
                'event_time' => $r->event_time,
                'location' => $r->location?->name,
                // A generic label, never the family/school's name or any
                // other contact detail.
                'title' => __(str($r->type)->replace('_', ' ')->title()),
            ]);

        return Inertia::render('Public/Calendar', [
            'events' => $events,
            'colors' => config('calendar.colors'),
            'month' => $month,
            'year' => $year,
        ]);
    }
}