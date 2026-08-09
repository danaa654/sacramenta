<?php

namespace Database\Seeders;

use App\Models\BlockedDate;
use Illuminate\Database\Seeder;

/**
 * Example blocked periods for the Church Availability & Conflict Detection
 * Engine, matching the spec's own examples. Adjust dates/titles per parish;
 * these are meant as a starting point, not a fixed calendar.
 */
class BlockedDateSeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;

        $blocks = [
            ['title' => 'Christmas', 'start_date' => "{$year}-12-24", 'end_date' => "{$year}-12-26", 'reason' => 'Christmas liturgies — no outside bookings.'],
            ['title' => 'Holy Week', 'start_date' => "{$year}-04-13", 'end_date' => "{$year}-04-20", 'reason' => 'Holy Thursday through Easter Sunday.'],
            ['title' => 'Parish Fiesta', 'start_date' => "{$year}-05-15", 'end_date' => "{$year}-05-16", 'reason' => 'Annual parish fiesta celebrations.'],
            ['title' => 'Church Maintenance', 'start_date' => "{$year}-09-01", 'end_date' => "{$year}-09-03", 'reason' => 'Scheduled roof and electrical maintenance.'],
            ['title' => 'Parish Retreat', 'start_date' => "{$year}-08-20", 'end_date' => "{$year}-08-22", 'reason' => 'Clergy and staff retreat — limited availability.'],
        ];

        foreach ($blocks as $block) {
            BlockedDate::firstOrCreate(
                ['title' => $block['title'], 'start_date' => $block['start_date']],
                $block
            );
        }
    }
}