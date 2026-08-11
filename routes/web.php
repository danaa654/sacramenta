<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChurchAvailabilityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinancialsController;
use App\Http\Controllers\MassScheduleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RotaController;
use App\Http\Controllers\SeminarController;
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
    Route::post('masses', [MassScheduleController::class, 'store'])->name('masses.store');
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

    // Real Mass Schedule occurrences for a date — powers the Pamisa sa
    // Kalag "attach to an existing Mass" dropdown with actual mass_schedules
    // rows instead of a hardcoded placeholder.
    Route::get('reservations/mass-schedules', [ReservationController::class, 'massSchedules'])
        ->name('reservations.mass-schedules');

    // Church Availability & Conflict Detection Engine: whole-day occupied/
    // available timeline, live conflict check, and nearest-slot suggestions.
    Route::get('church-availability', [ChurchAvailabilityController::class, 'day'])
        ->name('church-availability.day');

    Route::get('reservations/{reservation}/receipt', [ReservationController::class, 'receipt'])
        ->name('reservations.receipt');

    Route::get('reservations/{reservation}/certificate', [ReservationController::class, 'certificate'])
        ->name('reservations.certificate');

    Route::patch('reservations/{reservation}/requirements', [ReservationController::class, 'updateRequirements'])
        ->name('reservations.requirements.update');

    // Pre-Cana / Marriage Preparation Seminar scheduling — separate from
    // the wedding's own Event Date/Event Time (reservations.status.update
    // / reservations.actions.update). See SeminarController.
    Route::post('reservations/{reservation}/seminar', [SeminarController::class, 'store'])
        ->name('reservations.seminar.store');
    Route::patch('reservations/{reservation}/seminar/{seminar}', [SeminarController::class, 'update'])
        ->name('reservations.seminar.update');
    Route::patch('reservations/{reservation}/seminar/{seminar}/complete', [SeminarController::class, 'complete'])
        ->name('reservations.seminar.complete');
    Route::delete('reservations/{reservation}/seminar/{seminar}', [SeminarController::class, 'destroy'])
        ->name('reservations.seminar.destroy');

    Route::patch('reservations/{reservation}/rota', [RotaController::class, 'update'])
        ->name('reservations.rota.update');

    Route::patch('reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])
        ->name('reservations.status.update');

    Route::patch('reservations/{reservation}/actions', [ReservationController::class, 'updateActions'])
        ->name('reservations.actions.update');

    Route::patch('reservations/{reservation}/correct', [ReservationController::class, 'correct'])
        ->name('reservations.correct');

    Route::resource('reservations', ReservationController::class);

    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
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