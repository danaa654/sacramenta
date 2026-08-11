<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two small, additive changes to support the reorganized Wedding
 * Requirements page:
 *
 *  1. `meta` (json, nullable) — flexible extra fields for a checklist item
 *     that don't deserve their own column (Canonical Interview's date/
 *     time/venue/facilitator, Marriage Banns' started/completed parish,
 *     etc). Kept generic rather than adding a column per field so future
 *     requirement types can carry their own shape without another
 *     migration.
 *
 *  2. Wedding's baptismal/CENOMAR/confirmation-certificate/civil-license
 *     items move from the `supporting` group into their own `documents`
 *     group, so the UI can show "Documents Requirements" as a section
 *     distinct from "Parish-Specific / Supporting Documents" (which is
 *     now just the reservation deposit). This is a relabel only — these
 *     items are still `is_required = false` and never block confirming
 *     the reservation, matching the existing behavior.
 *
 * Existing rows are updated in place; nothing is dropped or renamed away
 * from what already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_requirements', function (Blueprint $table) {
            if (! Schema::hasColumn('reservation_requirements', 'meta')) {
                $table->json('meta')->nullable()->after('date_completed');
            }
        });

        $documentKeys = [
            'baptismal_certificate_groom',
            'baptismal_certificate_bride',
            'confirmation_certificate_groom',
            'confirmation_certificate_bride',
            'cenomar_groom',
            'cenomar_bride',
            'civil_marriage_license',
        ];

        $weddingReservationIds = DB::table('reservations')->where('type', 'wedding')->pluck('id');

        if ($weddingReservationIds->isNotEmpty()) {
            DB::table('reservation_requirements')
                ->whereIn('reservation_id', $weddingReservationIds)
                ->whereIn('key', $documentKeys)
                ->update([
                    'group_key' => 'documents',
                    'group_label' => 'Documents Requirements',
                ]);
        }

        $this->addMissingOtherDocumentItems($weddingReservationIds);
    }

    /**
     * "Other required document(s)" — one optional slot per side, added to
     * every existing wedding that doesn't already have it. Defaulted to
     * Not Required so it doesn't read as a new outstanding item on
     * existing reservations; office staff can flip it to Pending/
     * Submitted/Verified if the parish actually needs something extra
     * from a specific couple.
     */
    protected function addMissingOtherDocumentItems($weddingReservationIds): void
    {
        $items = [
            ['key' => 'other_document_bride', 'label' => 'Other Required Document(s) — Bride'],
            ['key' => 'other_document_groom', 'label' => 'Other Required Document(s) — Groom'],
        ];

        foreach ($weddingReservationIds as $reservationId) {
            foreach ($items as $item) {
                $exists = DB::table('reservation_requirements')
                    ->where('reservation_id', $reservationId)
                    ->where('key', $item['key'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('reservation_requirements')->insert([
                    'reservation_id' => $reservationId,
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'is_completed' => false,
                    'status' => 'not_required',
                    'is_required' => false,
                    'group_key' => 'documents',
                    'group_label' => 'Documents Requirements',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('reservation_requirements', function (Blueprint $table) {
            if (Schema::hasColumn('reservation_requirements', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};