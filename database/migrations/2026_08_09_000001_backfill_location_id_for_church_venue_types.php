<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data-only backfill for the venue-resolution bug fixed alongside this
 * migration (see App\Support\ReservationVenue and
 * StoreReservationRequest::prepareForValidation()).
 *
 * Before the fix, only Wedding/Baptism/Burial reservations were
 * automatically assigned the Main Sanctuary's location_id on save. Any
 * existing First Communion or Confirmation reservation — which also has no
 * venue picker in the UI and is always held at the Main Sanctuary — was
 * left with location_id = null, making it invisible to the Church
 * Availability engine's venue-conflict checks.
 *
 * This does not touch any other field, does not delete anything, and is
 * safe to run repeatedly (it only ever fills in a currently-null
 * location_id on the two affected types).
 */
return new class extends Migration
{
    public function up(): void
    {
        $mainSanctuaryId = DB::table('locations')
            ->where('kind', 'main_sanctuary')
            ->value('id');

        if (! $mainSanctuaryId) {
            return;
        }

        DB::table('reservations')
            ->whereIn('type', ['first_communion', 'confirmation'])
            ->whereNull('location_id')
            ->update(['location_id' => $mainSanctuaryId]);

        // School Mass only gets a location_id when the admin picked
        // details.venue === 'church' (see App\Support\ReservationVenue).
        // Read row-by-row rather than a JSON_EXTRACT WHERE clause, since
        // that operator's syntax differs between MySQL and SQLite and this
        // only runs once against however many School Mass rows exist.
        DB::table('reservations')
            ->where('type', 'school_mass')
            ->whereNull('location_id')
            ->select('id', 'details')
            ->orderBy('id')
            ->get()
            ->each(function ($row) use ($mainSanctuaryId) {
                $details = json_decode((string) $row->details, true) ?: [];

                if (($details['venue'] ?? null) === 'church') {
                    DB::table('reservations')
                        ->where('id', $row->id)
                        ->update(['location_id' => $mainSanctuaryId]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally a no-op: reverting would mean re-nulling
        // location_id on real reservation records, which would just
        // reintroduce the bug this migration fixes. Nothing destructive
        // to undo.
    }
};