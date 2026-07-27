<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A `mass_schedules` row is a TEMPLATE slot in the parish's standing weekly
 * Mass schedule (Sunday / Weekday / Friday) — not a booking. It describes
 * "every week, on these day(s), there's a Mass at this time, in this
 * language, at this venue". The `mass:generate-schedule` command reads
 * these rows and stamps out real, individually-editable `reservations`
 * rows (type = 'mass') some weeks ahead.
 *
 * `days_of_week` is a JSON array of Carbon-style weekday ints
 * (0 = Sunday ... 6 = Saturday), not a single day column, so the WEEKDAY
 * template (Mon-Thu) can be expressed as one set of rows tagged
 * [1, 2, 3, 4] instead of four near-duplicate rows per time slot.
 *
 * Editing or deactivating a template row only changes future generation —
 * it is never live-linked to Reservation rows already generated from it
 * (see reservations.mass_schedule_id, which is a provenance pointer for
 * dedup/traceability only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_schedules', function (Blueprint $table) {
            $table->id();
            // Purely descriptive, e.g. "Sunday 5:30 AM (Cebuano)" — shown
            // in the template management UI, never copied onto generated
            // reservations (those get their own details snapshot instead).
            $table->string('label')->nullable();
            $table->json('days_of_week');
            $table->time('start_time');
            $table->time('end_time');
            // Cebuano, English, etc. Nullable since not every parish
            // bothers to tag language for every slot.
            $table->string('language')->nullable();
            $table->boolean('is_livestreamed')->default(false);
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_schedules');
    }
};