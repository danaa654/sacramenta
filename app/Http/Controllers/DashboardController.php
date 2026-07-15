<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        return Inertia::render('Dashboard', [
            'todayEvents' => Reservation::whereDate('event_date', $today)->get(),
            'upcomingEvents' => Reservation::where('event_date', '>', $today)
                ->orderBy('event_date')
                ->limit(10)
                ->get(),
            'stats' => [
                'total' => Reservation::count(),
                'pending' => Reservation::where('status', 'draft')->count(),
                'confirmed' => Reservation::where('status', 'confirmed')->count(),
                'completedThisMonth' => Reservation::where('status', 'completed')
                    ->whereMonth('event_date', $today->month)
                    ->count(),
            ],
        ]);
    }
}