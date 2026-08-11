<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `schedule_source` (generated | manual) to the two places a wedding's
 * marriage-preparation dates already live:
 *
 *  - `reservation_requirements` — Canonical Interview and Marriage Banns
 *    store their dates in `meta` / `date_started` / `date_completed`
 *    (see 2026_08_11_000001). Wedding Rehearsal (new checklist item, see
 *    config/reservation_requirements.php) uses the same `meta` pattern.
 *  - `wedding_seminars` — the Pre-Cana / Marriage Preparation Seminar.
 *
 * This lets MarriagePreparationSchedulingService tell an automatically
 * generated suggestion apart from a date the admin manually typed in, so
 * regenerating never silently clobbers a manual adjustment (see
 * app/Services/MarriagePreparationSchedulingService.php).
 *
 * Deliberately NOT a new table — reuses the existing architecture per the
 * "extend, don't duplicate" requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_requirements', function (Blueprint $table) {
            if (! Schema::hasColumn('reservation_requirements', 'schedule_source')) {
                $table->string('schedule_source', 20)->nullable()->after('meta');
            }
        });

        Schema::table('wedding_seminars', function (Blueprint $table) {
            if (! Schema::hasColumn('wedding_seminars', 'schedule_source')) {
                $table->string('schedule_source', 20)->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservation_requirements', function (Blueprint $table) {
            if (Schema::hasColumn('reservation_requirements', 'schedule_source')) {
                $table->dropColumn('schedule_source');
            }
        });

        Schema::table('wedding_seminars', function (Blueprint $table) {
            if (Schema::hasColumn('wedding_seminars', 'schedule_source')) {
                $table->dropColumn('schedule_source');
            }
        });
    }
};