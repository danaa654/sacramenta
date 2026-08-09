<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Classifies each `locations` row as one of:
 *   - main_sanctuary : the parish's primary worship space
 *   - chapel         : a secondary on-site chapel (distinct from the free-text
 *                       kapilya/barangay "chapel" used by Chapel Mass, which
 *                       is off-site and never touches this table)
 *   - other          : any other on-site venue (Parish Hall, function room,
 *                       courtyard, etc.)
 *
 * This does NOT change how conflicts are detected — SchedulingConflictService
 * already keys conflicts off `location_id` ("same location + overlapping
 * time = conflict"), regardless of kind. `kind` exists purely so the admin
 * UI and reporting can label a conflict as a Main Sanctuary conflict, a
 * Chapel conflict, or an Other Venue conflict, versus a reservation that
 * carries no location_id at all (house blessing, business blessing, etc.
 * held off-site) — which uses no church venue at all.
 *
 * No new table: this reuses the existing `locations` table added in
 * 2026_07_17_000000_create_locations_table.php, per the "don't create
 * unnecessary venue tables" guidance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('kind')->default('other')->after('name');
        });

        // Backfill the one venue we know is the Main Sanctuary today. Any
        // other existing location (e.g. "Parish Hall") keeps the 'other'
        // default set above. Parishes can reclassify a location to
        // 'chapel' later via the Locations admin screen if/when a second
        // on-site chapel is added.
        DB::table('locations')
            ->where('name', 'Parish of the Holy Sacraments')
            ->update(['kind' => 'main_sanctuary']);
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};