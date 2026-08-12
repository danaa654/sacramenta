<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Church Availability & Conflict Detection Engine (ChurchAvailabilityService)
 * and the Reservations list both filter on event_date/type/status/location_id
 * on nearly every request:
 *
 *   - occupiedPeriods(): whereDate('event_date', ...)->whereIn('status', ...)
 *     ->whereIn('type', ...)
 *   - ReservationController::index(): where('type', ...)->where('status', ...)
 *     ->orderByDesc('event_date')
 *
 * None of those columns were indexed (only priest_id got one, implicitly,
 * from its foreign key). Fine at today's row counts; will show up as slow
 * table scans once the archive grows. This adds the composite/single
 * indexes that actually match the query patterns above, plus the matching
 * ones on mass_schedules (queried by is_active on every availability call).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Backs occupiedPeriods()'s whereDate('event_date', ...)
            // ->whereIn('status', [...]) and the Reservations list's
            // ->where('status', ...)->orderByDesc('event_date').
            $table->index(['event_date', 'status'], 'reservations_event_date_status_index');

            // Backs ->where('type', '!=', 'mass') / ->where('type', $type)
            // on the Reservations list and the availability engine's
            // ->whereIn('type', config('church_schedule.occupying_types')).
            $table->index('type', 'reservations_type_index');

            // Backs findLocationConflict()'s ->where('location_id', ...).
            $table->index('location_id', 'reservations_location_id_index');
        });

        Schema::table('mass_schedules', function (Blueprint $table) {
            // occupiedPeriods() runs MassSchedule::where('is_active', true)
            // ->get() on every single availability/conflict check.
            $table->index('is_active', 'mass_schedules_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_event_date_status_index');
            $table->dropIndex('reservations_type_index');
            $table->dropIndex('reservations_location_id_index');
        });

        Schema::table('mass_schedules', function (Blueprint $table) {
            $table->dropIndex('mass_schedules_is_active_index');
        });
    }
};