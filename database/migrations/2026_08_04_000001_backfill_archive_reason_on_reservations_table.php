<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time backfill for reservations that were archived BEFORE the
     * archive_reason column existed (added in
     * 2026_08_04_000000_add_archive_reason_to_reservations_table.php).
     * Those rows have archive_reason = NULL, which doesn't match
     * 'completed', so they silently don't count toward the "Completed"
     * dashboard stats until they get a value.
     *
     * There's no reliable way to tell, after the fact, whether an old
     * archived row was cancelled or actually happened — that distinction
     * was never recorded before now. This defaults every NULL to
     * 'completed', since paid/filled-in archived records (which is what
     * this app's seeded demo data represents) are far more often filed-
     * away completed events than cancellations in practice.
     *
     * If any of your real archived records were actually cancellations,
     * open them in Reservations/Archives and re-save their status once —
     * that runs them back through resolveArchiveReason() and corrects it,
     * or you can update the row directly in the database.
     */
    public function up(): void
    {
        DB::table('reservations')
            ->where('status', 'archived')
            ->whereNull('archive_reason')
            ->update(['archive_reason' => 'completed']);
    }

    public function down(): void
    {
        // Not reversible — we don't know which rows were NULL before this
        // ran, so there's nothing safe to revert back to.
    }
};