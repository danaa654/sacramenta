<?php

namespace App\Console\Commands;

use App\Models\MassSchedule;
use App\Models\Reservation;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

/**
 * Stamps out real, individually-editable `reservations` rows (type =
 * 'mass') from the active MassSchedule templates, some weeks ahead of
 * time (config('mass_schedule.generate_weeks_ahead'), default 8).
 *
 * Design notes (see the church's brief for full context):
 *
 *  - Generated rows are created ALREADY CONFIRMED, skipping the normal
 *    draft -> review -> confirm pipeline, and WITHOUT firing the
 *    "new reservation created" admin notification — that notification is
 *    for staff-entered bookings, not ~30+/week routine Masses.
 *  - `priest_id` is intentionally left null at generation time; priests
 *    are assigned later via the "unassigned Masses" summary view
 *    (see MassScheduleController::unassigned).
 *  - Idempotent / safe to re-run (daily, via the scheduler in
 *    routes/console.php): each (mass_schedule_id, event_date) pair is
 *    unique (DB-level unique index), and we use firstOrCreate so
 *    re-running never creates duplicates or touches rows that were
 *    already individually edited/cancelled (e.g. a typhoon-cancelled
 *    Friday Mass stays cancelled — we never overwrite an existing row).
 *  - Editing the template afterward (or deactivating a slot) only
 *    affects future runs; it never reaches back and modifies rows
 *    already generated for past runs.
 */
class GenerateMassSchedule extends Command
{
    protected $signature = 'mass:generate-schedule {--weeks= : Override how many weeks ahead to generate}';

    protected $description = 'Generate confirmed Reservation rows from the active weekly Mass schedule templates';

    public function handle(): int
    {
        $weeksAhead = (int) ($this->option('weeks') ?? config('mass_schedule.generate_weeks_ahead', 8));

        $templates = MassSchedule::where('is_active', true)->get();

        if ($templates->isEmpty()) {
            $this->comment('No active Mass schedule templates — nothing to generate.');

            return self::SUCCESS;
        }

        $start = now()->startOfDay();
        $end = now()->addWeeks($weeksAhead)->endOfDay();

        $created = 0;

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $dayOfWeek = $date->dayOfWeek; // Carbon: 0 = Sunday ... 6 = Saturday

            foreach ($templates as $template) {
                if (! $template->appliesOnDayOfWeek($dayOfWeek)) {
                    continue;
                }

                [$reservation, $wasCreated] = $this->generateOccurrence($template, $date->toDateString());

                if ($wasCreated) {
                    $created++;
                }
            }
        }

        $this->info("Mass schedule generation complete: {$created} new occurrence(s) created for the next {$weeksAhead} week(s).");

        return self::SUCCESS;
    }

    /**
     * @return array{0: Reservation, 1: bool} the reservation and whether it was just created
     */
    protected function generateOccurrence(MassSchedule $template, string $date): array
    {
        $existing = Reservation::query()
            ->where('mass_schedule_id', $template->id)
            ->whereDate('event_date', $date)
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $reservation = Reservation::create([
            'type' => 'mass',
            // No actual requester — this is the parish's own standing
            // schedule, not a family/staff booking. These are required
            // (NOT NULL) columns on `reservations`, so we fill them with
            // a clear placeholder rather than leaving them blank.
            'contact_name' => 'Parish Office (Regular Mass Schedule)',
            'contact_mobile' => 'N/A',
            'event_date' => $date,
            'event_time' => $template->start_time,
            'priest_id' => null, // assigned later, see "unassigned Masses" view
            'location_id' => $template->location_id,
            'mass_schedule_id' => $template->id,
            // Skips draft -> review -> confirm entirely: this is the
            // parish's authoritative standing schedule, not a request
            // awaiting review.
            'status' => 'confirmed',
            'details' => [
                'language' => $template->language,
                'is_livestreamed' => $template->is_livestreamed,
            ],
            'offering_amount' => null,
            'payment_status' => 'waived',
        ]);

        return [$reservation, true];
    }
}