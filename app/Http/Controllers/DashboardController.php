<?php

namespace App\Http\Controllers;

use App\Models\MassSchedule;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected array $massLikeTypes = ['mass', 'chapel_mass', 'school_mass'];

    public function index(Request $request)
    {
        $today = Carbon::today();
        $now = Carbon::now();

        $todayAll = Reservation::with(['priest', 'location'])
            ->whereDate('event_date', $today)
            ->where('status', '!=', 'archived')
            ->orderBy('event_time')
            ->get();

        // "Today's Schedule" and "Upcoming Reservations" are about actual
        // sacrament bookings (weddings, baptisms, burials, etc.) — the
        // recurring daily/weekly Masses already have their own dedicated
        // "Regular Mass Schedule" widget, so we keep them out of here to
        // avoid the same thing showing up twice.
        $todayEvents = $todayAll->whereNotIn('type', $this->massLikeTypes)->values();

        return Inertia::render('Dashboard', [
            'todayEvents' => $todayEvents,

            'upcomingEvents' => Reservation::with(['priest', 'location'])
                ->where('event_date', '>', $today)
                ->whereNotIn('type', $this->massLikeTypes)
                ->where('status', '!=', 'archived')
                ->orderBy('event_date')
                ->orderBy('event_time')
                ->limit(6)
                ->get(),

            'stats' => [
                // Actual sacrament/event reservations only — the recurring
                // daily/weekly Masses have their own "Regular Mass Schedule"
                // widget and shouldn't inflate this count.
                'total' => Reservation::whereNotIn('type', $this->massLikeTypes)->count(),
                'pending' => Reservation::where('status', 'draft')->count(),
                // Confirmed regular Masses happening this week (Mon-Sun),
                // rather than confirmed sacrament reservations of any kind —
                // pairs with the Regular Mass Schedule widget below.
                'confirmed' => Reservation::whereIn('type', $this->massLikeTypes)
                    ->where('status', 'confirmed')
                    ->whereBetween('event_date', [
                        $today->copy()->startOfWeek(),
                        $today->copy()->endOfWeek(),
                    ])
                    ->count(),
                'completedThisMonth' => Reservation::where(function ($q) {
                        $q->where('status', 'completed')
                            ->orWhere(function ($q) {
                                // Filed into Archives, but it happened — see
                                // resolveArchiveReason() in ReservationController.
                                $q->where('status', 'archived')->where('archive_reason', 'completed');
                            });
                    })
                    ->whereMonth('event_date', $today->month)
                    ->whereYear('event_date', $today->year)
                    ->count(),
                'completedThisYear' => Reservation::where(function ($q) {
                        $q->where('status', 'completed')
                            ->orWhere(function ($q) {
                                $q->where('status', 'archived')->where('archive_reason', 'completed');
                            });
                    })
                    ->whereYear('event_date', $today->year)
                    ->count(),
            ],

            // Hero card: the next Mass still to come today (or null if the
            // day's Masses are already done), plus how many sacrament
            // reservations land on today and today's regular Mass times.
            'nextMass' => $todayAll
                ->whereIn('type', $this->massLikeTypes)
                ->filter(fn ($r) => $r->event_time === null || $r->event_time >= $now->format('H:i:s'))
                ->sortBy('event_time')
                ->first(),

            // "Reservations Today" on the hero card is about actual sacrament
            // bookings (weddings, baptisms, burials, etc.) only — regular
            // Masses are surfaced separately via todayMassSchedule below, so
            // they shouldn't be double-counted here.
            'reservationsTodayCount' => $todayEvents->count(),

            // Total number of Masses today, shown alongside Reservations
            // Today on the hero card (not each individual time — that's
            // already what the clock on screen and the regular schedule
            // widget further down are for).
            'todayMassCount' => $todayAll
                ->whereIn('type', $this->massLikeTypes)
                ->count(),

            // Weekly regular Mass schedule, shaped as one row per day with
            // all of that day's times so the front end can render a simple
            // Mon-Sun table like the reservations calendar legend.
            'regularMassSchedule' => $this->weeklyMassSchedule(),

            // Small calendar for the current month: a status per day driven
            // by how many reservations are actually booked on it.
            'calendarMonth' => $this->calendarMonth($today),

            // Recent activity feed reuses the same notifications each admin
            // already sees in the bell dropdown.
            'recentActivity' => $request->user()
                ? $request->user()->notifications()->latest()->limit(5)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'kind' => $n->data['kind'] ?? 'reminder',
                    'title' => $n->data['title'] ?? '',
                    'body' => $n->data['body'] ?? '',
                    'url' => $n->data['url'] ?? null,
                    'created_at' => $n->created_at->diffForHumans(),
                ])
                : [],

            'financialOverview' => $this->financialOverview($today),
        ]);
    }

    protected function weeklyMassSchedule(): array
    {
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];

        $schedules = MassSchedule::where('is_active', true)->orderBy('start_time')->get();

        return collect($days)->map(function ($label, $dow) use ($schedules) {
            $times = $schedules
                ->filter(fn ($s) => $s->appliesOnDayOfWeek($dow))
                ->map(fn ($s) => $s->start_time)
                ->unique()
                ->values()
                ->all();

            return [
                'day' => $label,
                'times' => $times,
            ];
        })->values()->all();
    }

    protected function calendarMonth(Carbon $today): array
    {
        $start = $today->copy()->startOfMonth();
        $end = $today->copy()->endOfMonth();

        $counts = Reservation::whereBetween('event_date', [$start, $end])
            ->where('status', '!=', 'archived')
            ->selectRaw('event_date, count(*) as total')
            ->groupBy('event_date')
            ->pluck('total', 'event_date')
            ->mapWithKeys(fn ($total, $date) => [Carbon::parse($date)->format('Y-m-d') => $total]);

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $count = $counts[$key] ?? 0;

            $status = 'none';
            if ($count > 0) {
                $status = $count >= 8 ? 'full' : ($count >= 4 ? 'almost' : 'available');
            }

            $days[] = [
                'date' => $key,
                'day' => $d->day,
                'status' => $status,
                'count' => $count,
            ];
        }

        return [
            'label' => $start->format('F Y'),
            'startWeekday' => $start->dayOfWeek,
            'days' => $days,
        ];
    }

    protected function financialOverview(Carbon $today): array
    {
        return [
            'month' => $this->financialPeriod($today->copy()->startOfMonth(), $today->copy()->endOfMonth()),
            'year' => $this->financialPeriod($today->copy()->startOfYear(), $today->copy()->endOfYear()),
        ];
    }

    /**
     * Offerings/collected/outstanding totals (plus a daily series for the
     * sparkline) for reservations whose EVENT falls inside [$start, $end].
     * Shared by the Financial Overview widget's "This Month" and "This
     * Year" views — same shape, different window.
     */
    protected function financialPeriod(Carbon $start, Carbon $end): array
    {
        $rows = Reservation::whereBetween('event_date', [$start, $end])
            ->whereNotNull('offering_amount')
            ->get(['offering_amount', 'amount_paid', 'payment_status', 'event_date']);

        $series = $rows
            ->groupBy(fn ($r) => Carbon::parse($r->event_date)->format('Y-m-d'))
            ->map(fn ($group, $date) => [
                'date' => $date,
                'amount' => (float) $group->sum('amount_paid'),
            ])
            ->values()
            ->sortBy('date')
            ->values()
            ->all();

        return [
            'offerings' => (float) $rows->sum('offering_amount'),
            'collected' => (float) $rows->sum('amount_paid'),
            'outstanding' => (float) $rows->sum(fn ($r) => max(0, $r->offering_amount - $r->amount_paid)),
            'series' => $series,
        ];
    }
}