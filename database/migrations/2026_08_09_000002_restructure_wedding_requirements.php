<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reworks the Wedding Requirements checklist so that:
 *
 *  - Each requirement has a real status (pending / in_progress / completed
 *    / not_required) instead of a bare boolean, so an admin can explicitly
 *    mark something "Not Required" for a parish that doesn't use it,
 *    without that reading as "still outstanding".
 *  - Requirements are split into `is_required` (blocks confirming the
 *    reservation) vs optional/supporting documents (never blocks).
 *  - Confirmation-related certificates move out of the blocking checklist
 *    into the optional "Parish-Specific / Supporting Documents" group —
 *    see config/reservation_requirements.php.
 *
 * This is additive and safe: no existing columns or rows are dropped.
 * `is_completed` is kept in sync with the new `status` column so any
 * older code path that still reads it keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_requirements', function (Blueprint $table) {
            if (! Schema::hasColumn('reservation_requirements', 'status')) {
                $table->string('status', 20)->default('pending')->after('is_completed');
            }
            if (! Schema::hasColumn('reservation_requirements', 'is_required')) {
                $table->boolean('is_required')->default(true)->after('status');
            }
            if (! Schema::hasColumn('reservation_requirements', 'group_key')) {
                $table->string('group_key')->nullable()->after('is_required');
            }
            if (! Schema::hasColumn('reservation_requirements', 'group_label')) {
                $table->string('group_label')->nullable()->after('group_key');
            }
            if (! Schema::hasColumn('reservation_requirements', 'description')) {
                $table->string('description', 500)->nullable()->after('group_label');
            }
            if (! Schema::hasColumn('reservation_requirements', 'date_started')) {
                $table->date('date_started')->nullable()->after('note');
            }
            if (! Schema::hasColumn('reservation_requirements', 'date_completed')) {
                $table->date('date_completed')->nullable()->after('date_started');
            }
        });

        // Backfill status from the existing boolean for every row already
        // in the table (any reservation type, not just weddings).
        DB::table('reservation_requirements')->where('is_completed', true)->update(['status' => 'completed']);
        DB::table('reservation_requirements')->where('is_completed', false)->update(['status' => 'pending']);

        $this->restructureExistingWeddingRows();
        $this->addMissingRequiredDocumentsItem();
    }

    /**
     * Existing wedding reservations were seeded under the old flat
     * checklist. Re-tag their rows to match the new grouping so old
     * records display consistently with newly-created ones, without
     * touching who-completed-what.
     */
    protected function restructureExistingWeddingRows(): void
    {
        $preMarriageKeys = ['canonical_interview', 'marriage_banns', 'pre_cana_seminar'];

        $supportingKeys = [
            'reservation_deposit',
            'baptismal_certificate_groom',
            'baptismal_certificate_bride',
            'confirmation_certificate_groom',
            'confirmation_certificate_bride',
            'cenomar_groom',
            'cenomar_bride',
            'civil_marriage_license',
        ];

        $weddingReservationIds = DB::table('reservations')->where('type', 'wedding')->pluck('id');

        if ($weddingReservationIds->isEmpty()) {
            return;
        }

        DB::table('reservation_requirements')
            ->whereIn('reservation_id', $weddingReservationIds)
            ->whereIn('key', $preMarriageKeys)
            ->update([
                'is_required' => true,
                'group_key' => 'pre_marriage',
                'group_label' => 'Pre-Marriage Requirements',
            ]);

        DB::table('reservation_requirements')
            ->whereIn('reservation_id', $weddingReservationIds)
            ->whereIn('key', $supportingKeys)
            ->update([
                // Never block confirmation — these are supporting documents,
                // not gating requirements. A completed/not_required status
                // set previously is left as-is; a still-pending one no
                // longer holds the reservation back.
                'is_required' => false,
                'group_key' => 'supporting',
                'group_label' => 'Parish-Specific / Supporting Documents',
            ]);
    }

    /**
     * "Required Documents Verified" is a brand-new checklist item (it
     * didn't exist under the old flat list). Add it to every existing
     * wedding reservation that doesn't already have it, defaulted to
     * Pending so admins can review and update it — never auto-completed.
     */
    protected function addMissingRequiredDocumentsItem(): void
    {
        $weddings = DB::table('reservations')->where('type', 'wedding')->get(['id']);

        foreach ($weddings as $reservation) {
            $exists = DB::table('reservation_requirements')
                ->where('reservation_id', $reservation->id)
                ->where('key', 'required_documents_verified')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('reservation_requirements')->insert([
                'reservation_id' => $reservation->id,
                'key' => 'required_documents_verified',
                'label' => 'Required Documents Verified',
                'is_completed' => false,
                'status' => 'pending',
                'is_required' => true,
                'group_key' => 'pre_marriage',
                'group_label' => 'Pre-Marriage Requirements',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('reservation_requirements', function (Blueprint $table) {
            foreach (['status', 'is_required', 'group_key', 'group_label', 'description', 'date_started', 'date_completed'] as $column) {
                if (Schema::hasColumn('reservation_requirements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};