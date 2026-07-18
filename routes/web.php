<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCalendarController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RotaController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Public parishioner calendar — no auth. Only ever shows confirmed,
// non-private event types (see PublicCalendarController).
Route::get('/parish-calendar', [PublicCalendarController::class, 'index'])->name('calendar.public');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');

    Route::get('financials', [FinancialsController::class, 'index'])->name('financials.index');
    Route::patch('financials/{reservation}', [FinancialsController::class, 'update'])->name('financials.update');

    // NOTE: this must be registered before Route::resource() below, otherwise
    // the resource's GET reservations/{reservation} route will swallow
    // "availability" as if it were a reservation ID.
    Route::get('reservations/availability', [ReservationController::class, 'availability'])
        ->name('reservations.availability');

    Route::patch('reservations/{reservation}/requirements', [ReservationController::class, 'updateRequirements'])
        ->name('reservations.requirements.update');

    Route::patch('reservations/{reservation}/rota', [RotaController::class, 'update'])
        ->name('reservations.rota.update');

    Route::patch('reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])
        ->name('reservations.status.update');

    Route::resource('reservations', ReservationController::class);

    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';