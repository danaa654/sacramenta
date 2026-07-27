<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `mass_schedule_id` is a nullable provenance pointer: which template row
 * (if any) generated this reservation. It's null for every normal
 * staff-entered booking (wedding, baptism, etc.) and set only for
 * auto-generated type='mass' rows.
 *
 * The unique (mass_schedule_id, event_date) index is how
 * `mass:generate-schedule` avoids duplicate generation on re-run — MySQL/
 * Postgres treat NULLs as distinct for uniqueness purposes, so this index
 * never affects ordinary (non-mass) reservations, only guards against the
 * same template slot being stamped out twice for the same date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('mass_schedule_id')->nullable()->after('location_id')
                ->constrained('mass_schedules')->nullOnDelete();

            $table->unique(['mass_schedule_id', 'event_date'], 'reservations_mass_schedule_event_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique('reservations_mass_schedule_event_date_unique');
            $table->dropConstrainedForeignId('mass_schedule_id');
        });
    }
};