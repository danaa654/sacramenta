<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\MassSchedule;
use Illuminate\Database\Seeder;

/**
 * Seeds the parish's standing weekly Mass schedule: SUNDAY, WEEKDAY
 * (Mon-Thu), FRIDAY, and SATURDAY templates. These are recurrence RULES,
 * not bookings — App\Console\Commands\GenerateMassSchedule reads them to
 * stamp out real, per-week Reservation rows.
 *
 * `days_of_week` uses Carbon's weekday ints: 0 = Sunday, 1 = Monday, ...,
 * 6 = Saturday.
 */
class MassScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $mainSanctuary = Location::where('name', 'Main Sanctuary')->firstOrFail();
        $chapel = Location::where('name', 'Chapel')->firstOrFail();

        $stJosephChapel = Location::firstOrCreate(
            ['name' => 'St. Joseph Chapel'],
            ['is_active' => true]
        );

        foreach ($this->templates($mainSanctuary->id, $chapel->id, $stJosephChapel->id) as $row) {
            MassSchedule::firstOrCreate(
                ['label' => $row['label'], 'start_time' => $row['start_time']],
                $row
            );
        }
    }

    /**
     * @return array<int, array{
     *   label: string, days_of_week: array<int>, start_time: string,
     *   end_time: string, language: ?string, is_livestreamed: bool,
     *   location_id: int, is_active: bool
     * }>
     */
    protected function templates(int $mainSanctuaryId, int $chapelId, int $stJosephChapelId): array
    {
        $sunday = [0];
        $weekday = [1, 2, 3, 4]; // Monday - Thursday
        $friday = [5];
        $saturday = [6];

        $rows = [];

        // ------------------------------------------------------------------
        // SUNDAY — 10 Slots (Highest Density day of obligation)
        // Run almost hourly in the morning, followed by a mid-afternoon block 
        // into the evening to handle massive parishioner turnouts.
        // ------------------------------------------------------------------
        $sundaySlots = [
            ['05:00', '06:00', 'Cebuano', false],
            ['06:15', '07:15', 'Cebuano', false],
            ['07:30', '08:30', 'English', false],
            ['08:45', '09:45', 'Cebuano', true],  // Primary Livestreamed Mass
            ['10:00', '11:00', 'English', true],  // High Mass
            ['11:15', '12:15', 'English', false], 
            ['14:30', '15:30', 'Cebuano', false], // Afternoon block resumes
            ['15:45', '16:45', 'Cebuano', false],
            ['17:00', '18:00', 'English', false],
            ['18:15', '19:15', 'Cebuano', false],
        ];

        foreach ($sundaySlots as [$start, $end, $language, $livestreamed]) {
            $rows[] = [
                'label' => 'Sunday '.$this->to12Hour($start).' ('.$language.')',
                'days_of_week' => $sunday,
                'start_time' => $start,
                'end_time' => $end,
                'language' => $language,
                'is_livestreamed' => $livestreamed,
                'location_id' => $mainSanctuaryId,
                'is_active' => true,
            ];
        }

        // ------------------------------------------------------------------
        // WEEKDAY (Mon-Thu) — 4 Standard Slots
        // Concise low-mass blocks built for workers and students.
        // ------------------------------------------------------------------
        $weekdaySlots = [
            ['06:00', '06:45', 'Cebuano'], 
            ['07:00', '07:45', 'English'],
            ['12:15', '13:00', 'English'], // Lunch break Mass
            ['17:30', '18:15', 'Cebuano'], // After-work Mass
        ];

        foreach ($weekdaySlots as [$start, $end, $language]) {
            $rows[] = [
                'label' => 'Weekday '.$this->to12Hour($start).' ('.$language.')',
                'days_of_week' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
                'language' => $language,
                'is_livestreamed' => false,
                'location_id' => $mainSanctuaryId,
                'is_active' => true,
            ];
        }

        // ------------------------------------------------------------------
        // FRIDAY — 5 Slots (Streamlined with intentional testing overlap)
        // Mimics a standard weekday framework but adds an afternoon slot for 
        // the traditional 3:00 PM Divine Mercy devotion.
        // Includes your location conflict test: 3:00 PM Main vs 3:00 PM Chapel.
        // ------------------------------------------------------------------
        $fridaySlots = [
            ['06:00', '06:45', $mainSanctuaryId, 'Cebuano'],
            ['07:00', '07:45', $mainSanctuaryId, 'English'],
            ['12:15', '13:00', $mainSanctuaryId, 'English'],
            ['15:00', '16:00', $mainSanctuaryId, 'Cebuano'], // Overlap Test A (Main Sanctuary)
            ['15:00', '16:00', $chapelId,         'Cebuano'], // Overlap Test B (Divine Mercy Hour Chapel)
            ['17:30', '18:30', $mainSanctuaryId, 'English'], // First Friday Devotional Evening Mass
        ];

        foreach ($fridaySlots as [$start, $end, $locationId, $language]) {
            $venueLabel = $locationId === $chapelId ? 'Chapel (overflow)' : 'Main Sanctuary';

            $rows[] = [
                'label' => 'Friday '.$this->to12Hour($start).' ('.$language.') — '.$venueLabel,
                'days_of_week' => $friday,
                'start_time' => $start,
                'end_time' => $end,
                'language' => $language,
                'is_livestreamed' => false,
                'location_id' => $locationId,
                'is_active' => true,
            ];
        }

        // ------------------------------------------------------------------
        // SATURDAY — 5 Slots
        // Morning devotions (Mother of Perpetual Help), a clean 5-hour afternoon 
        // gap dedicated completely to weddings/baptisms, followed by 
        // the standard Anticipated Sunday obligation Masses.
        // ------------------------------------------------------------------
        $saturdaySlots = [
            ['06:00', '06:45', 'Cebuano', $mainSanctuaryId, 'Morning Mass'],
            ['07:00', '08:00', 'English', $stJosephChapelId, 'Devotional Mass (St. Joseph Chapel)'],
            ['07:30', '08:30', 'Cebuano', $mainSanctuaryId, 'Communal Confirmation Mass'], // 30m intentional multi-location overlap
            // --- 08:30 AM to 4:30 PM: Clear liturgical gap reserved for weddings/baptisms ---
            ['16:30', '17:30', 'English', $mainSanctuaryId, 'Anticipated Sunday Mass (Vigil)'],
            ['18:00', '19:00', 'Cebuano', $mainSanctuaryId, 'Anticipated Sunday Mass (Late Vigil)'],
        ];

        foreach ($saturdaySlots as [$start, $end, $language, $locationId, $note]) {
            $rows[] = [
                'label' => 'Saturday '.$this->to12Hour($start).' — '.$note,
                'days_of_week' => $saturday,
                'start_time' => $start,
                'end_time' => $end,
                'language' => $language,
                'is_livestreamed' => false,
                'location_id' => $locationId,
                'is_active' => true,
            ];
        }

        return $rows;
    }

    protected function to12Hour(string $time): string
    {
        return date('g:i A', strtotime($time));
    }
}