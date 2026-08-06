<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialsController;
use App\Http\Controllers\MassScheduleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
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

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');

    Route::get('financials', [FinancialsController::class, 'index'])->name('financials.index');
    Route::patch('financials/{reservation}', [FinancialsController::class, 'update'])->name('financials.update');

    Route::get('archives', [ArchiveController::class, 'index'])->name('archives.index');

    Route::get('masses/unassigned', [MassScheduleController::class, 'unassigned'])->name('masses.unassigned');
    Route::patch('masses/{reservation}/assign-priest', [MassScheduleController::class, 'assignPriest'])
        ->name('masses.assign-priest');
    Route::patch('masses/{reservation}/cancel', [MassScheduleController::class, 'cancel'])
        ->name('masses.cancel');
    Route::patch('masses/{reservation}/restore', [MassScheduleController::class, 'restore'])
        ->name('masses.restore');

    // NOTE: this must be registered before Route::resource() below, otherwise
    // the resource's GET reservations/{reservation} route will swallow
    // "availability" as if it were a reservation ID.
    Route::get('reservations/availability', [ReservationController::class, 'availability'])
        ->name('reservations.availability');

    Route::get('reservations/{reservation}/receipt', [ReservationController::class, 'receipt'])
        ->name('reservations.receipt');

    Route::get('reservations/{reservation}/certificate', [ReservationController::class, 'certificate'])
        ->name('reservations.certificate');

    Route::patch('reservations/{reservation}/requirements', [ReservationController::class, 'updateRequirements'])
        ->name('reservations.requirements.update');

    Route::patch('reservations/{reservation}/rota', [RotaController::class, 'update'])
        ->name('reservations.rota.update');

    Route::patch('reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])
        ->name('reservations.status.update');

    Route::patch('reservations/{reservation}/actions', [ReservationController::class, 'updateActions'])
        ->name('reservations.actions.update');

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