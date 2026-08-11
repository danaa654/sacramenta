<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs wedding `reservation_requirements` rows so every wedding
 * reservation — old or new — actually has the rows the Wedding
 * Requirements page expects. This does not touch any other reservation
 * type, and never deletes or resets an existing row's status/note.
 *
 * Two separate bugs are fixed here:
 *
 *  1. `ReservationController::seedRequirements()` (before this fix) only
 *     wrote `key`/`label`/`is_completed` for a brand-new reservation's
 *     checklist rows, leaving `group_key` null. The Wedding Requirements
 *     panel splits its rows by `group_key`, so this silently made every
 *     wedding created after the Documents/Marriage Preparation split
 *     (2026_08_09_000002) show "0 of 0" for both sections — the rows
 *     existed, but weren't tagged into a group the panel looks for.
 *
 *  2. The earlier 2026_08_09_000002 migration filed the per-side document
 *     items (baptismal certificate, cenomar, marriage license) into a
 *     `supporting` group instead of `documents`, so even correctly-tagged
 *     older rows were invisible in the Documents Requirements section
 *     (the panel only renders `documents` and `pre_marriage` groups).
 *
 * This migration is safe to run multiple times and safe on a database
 * with no wedding reservations yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        $weddingIds = DB::table('reservations')->where('type', 'wedding')->pluck('id');

        if ($weddingIds->isEmpty()) {
            return;
        }

        $this->retagMisfiledDocumentRows($weddingIds);
        $this->splitSharedMarriageLicenseRows($weddingIds);
        $this->fillUngroupedRows($weddingIds);
        $this->seedMissingRows($weddingIds);
    }

    /**
     * Move the per-side document rows that 2026_08_09_000002 filed under
     * `supporting` back into `documents`, matching the current
     * config/reservation_requirements.php grouping. Confirmation
     * Certificate rows are deliberately left untouched — they're no
     * longer part of the checklist going forward (see the config file's
     * note), but existing data for older reservations is preserved as-is
     * per the "don't delete existing requirements" rule.
     */
    protected function retagMisfiledDocumentRows($weddingIds): void
    {
        DB::table('reservation_requirements')
            ->whereIn('reservation_id', $weddingIds)
            ->whereIn('key', [
                'baptismal_certificate_bride',
                'baptismal_certificate_groom',
                'cenomar_bride',
                'cenomar_groom',
                'other_document_bride',
                'other_document_groom',
            ])
            ->where(function ($q) {
                $q->where('group_key', '!=', 'documents')->orWhereNull('group_key');
            })
            ->update([
                'group_key' => 'documents',
                'group_label' => 'Documents Requirements',
                'is_required' => false,
            ]);
    }

    /**
     * The old single `civil_marriage_license` row (one per couple) is
     * replaced by two per-side rows so it displays under both Bride and
     * Groom, like every other document. Where an old shared row exists,
     * its status/note is copied onto both new rows rather than lost, and
     * the old row is left in place untouched (not deleted) in case
     * anything else still references it.
     */
    protected function splitSharedMarriageLicenseRows($weddingIds): void
    {
        $sharedRows = DB::table('reservation_requirements')
            ->whereIn('reservation_id', $weddingIds)
            ->where('key', 'civil_marriage_license')
            ->get();

        foreach ($sharedRows as $row) {
            foreach (['bride', 'groom'] as $side) {
                $key = "civil_marriage_license_{$side}";

                $exists = DB::table('reservation_requirements')
                    ->where('reservation_id', $row->reservation_id)
                    ->where('key', $key)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('reservation_requirements')->insert([
                    'reservation_id' => $row->reservation_id,
                    'key' => $key,
                    'label' => 'Marriage License ('.ucfirst($side).')',
                    'description' => null,
                    'is_completed' => $row->is_completed,
                    'status' => $row->status ?? 'pending',
                    'is_required' => false,
                    'group_key' => 'documents',
                    'group_label' => 'Documents Requirements',
                    'note' => $row->note,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Any wedding requirement row left with `group_key = null` (created
     * by the pre-fix seedRequirements()) is matched back to its config
     * definition by `key` and filled in, so old "0 of 0" weddings start
     * showing their real counts without losing whatever status/note an
     * admin already set.
     */
    protected function fillUngroupedRows($weddingIds): void
    {
        $items = collect(config('reservation_requirements.checklists.wedding', []))->keyBy('key');

        $ungrouped = DB::table('reservation_requirements')
            ->whereIn('reservation_id', $weddingIds)
            ->whereNull('group_key')
            ->get();

        foreach ($ungrouped as $row) {
            $item = $items->get($row->key);

            if (! $item) {
                continue;
            }

            DB::table('reservation_requirements')->where('id', $row->id)->update([
                'is_required' => $item['is_required'] ?? true,
                'group_key' => $item['group_key'] ?? null,
                'group_label' => $item['group_label'] ?? null,
                'description' => $row->description ?? ($item['description'] ?? null),
            ]);
        }
    }

    /**
     * Any wedding reservation missing a row entirely for a current
     * checklist key (e.g. it was created between the Documents/Marriage
     * Preparation split and this fix, so seedRequirements() silently
     * dropped it) gets that row added now, defaulted to Pending.
     */
    protected function seedMissingRows($weddingIds): void
    {
        $items = config('reservation_requirements.checklists.wedding', []);

        foreach ($weddingIds as $reservationId) {
            $existingKeys = DB::table('reservation_requirements')
                ->where('reservation_id', $reservationId)
                ->pluck('key')
                ->all();

            foreach ($items as $item) {
                if (in_array($item['key'], $existingKeys, true)) {
                    continue;
                }

                DB::table('reservation_requirements')->insert([
                    'reservation_id' => $reservationId,
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'description' => $item['description'] ?? null,
                    'is_completed' => false,
                    'status' => 'pending',
                    'is_required' => $item['is_required'] ?? true,
                    'group_key' => $item['group_key'] ?? null,
                    'group_label' => $item['group_label'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally a no-op: this migration only repairs/fills data
        // (retag, split, backfill) and never removes rows, so there is
        // nothing destructive to reverse. Rolling it back would require
        // guessing which rows it added vs. which already existed.
    }
};