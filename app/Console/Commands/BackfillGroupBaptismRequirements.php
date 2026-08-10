<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use Illuminate\Console\Command;

class BackfillGroupBaptismRequirements extends Command
{
    /**
     * Existing Group/Community baptism reservations created before
     * per-child requirement rows existed have a single shared checklist
     * (child_index/child_name both null on every row). This command
     * fans each of those flat rows out into one copy per child, then
     * removes the old shared rows — so old reservations end up looking
     * exactly like ones created after the change.
     *
     * Safe to re-run: reservations that already have any row with a
     * non-null child_index are skipped.
     */
    protected $signature = 'reservations:backfill-group-baptism-requirements';

    protected $description = 'Split shared requirement checklists into per-child rows for existing Group/Community baptism reservations';

    public function handle(): int
    {
        $reservations = Reservation::where('type', 'baptism')
            ->whereJsonContains('details->baptism_type', 'group')
            ->with('requirements')
            ->get();

        $updated = 0;

        foreach ($reservations as $reservation) {
            $children = $reservation->details['children'] ?? [];

            if (empty($children) || $reservation->requirements->isEmpty()) {
                continue;
            }

            // Already split (e.g. created after the fix, or already backfilled).
            if ($reservation->requirements->contains(fn ($r) => $r->child_index !== null)) {
                continue;
            }

            $sharedRows = $reservation->requirements;

            foreach ($children as $index => $child) {
                foreach ($sharedRows as $row) {
                    $reservation->requirements()->create([
                        'child_index' => $index,
                        'child_name' => $child['child_name'] ?? 'Child '.($index + 1),
                        'key' => $row->key,
                        'label' => $row->label,
                        'description' => $row->description,
                        'is_completed' => false,
                        'status' => \App\Models\ReservationRequirement::STATUS_PENDING,
                        'is_required' => $row->is_required ?? true,
                        'group_key' => $row->group_key,
                        'group_label' => $row->group_label,
                    ]);
                }
            }

            foreach ($sharedRows as $row) {
                $row->delete();
            }

            $updated++;
            $this->line("Reservation #{$reservation->id}: split into ".count($children)." child checklist(s).");
        }

        $this->info("Done. {$updated} reservation(s) updated.");

        return self::SUCCESS;
    }
}