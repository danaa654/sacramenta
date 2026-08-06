<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\MassSchedule;
use Illuminate\Database\Seeder;

class MassScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $mainSanctuary = Location::firstOrCreate(
            ['name' => 'Main Sanctuary'],
            ['is_active' => true]
        );

        $schedules = [

            /*
            |--------------------------------------------------------------------------
            | Monday - Saturday
            |--------------------------------------------------------------------------
            */

            [
                'label' => 'Weekday Morning Mass',
                'days_of_week' => [1,2,3,4,5,6],
                'start_time' => '06:00',
                'end_time' => '07:00',
            ],

            [
                'label' => 'Weekday Evening Mass',
                'days_of_week' => [1,2,3,4,5,6],
                'start_time' => '17:00',
                'end_time' => '18:00',
            ],

            /*
            |--------------------------------------------------------------------------
            | Sunday
            |--------------------------------------------------------------------------
            */

            [
                'label' => 'Sunday First Mass',
                'days_of_week' => [0],
                'start_time' => '06:00',
                'end_time' => '07:00',
            ],

            [
                'label' => 'Sunday Second Mass',
                'days_of_week' => [0],
                'start_time' => '08:00',
                'end_time' => '09:00',
            ],

            [
                'label' => 'Sunday High Mass',
                'days_of_week' => [0],
                'start_time' => '10:00',
                'end_time' => '11:00',
            ],

            [
                'label' => 'Sunday Afternoon Mass',
                'days_of_week' => [0],
                'start_time' => '15:00',
                'end_time' => '16:00',
            ],

            [
                'label' => 'Sunday Evening Mass',
                'days_of_week' => [0],
                'start_time' => '17:00',
                'end_time' => '18:00',
            ],

        ];

        foreach ($schedules as $schedule) {

            MassSchedule::updateOrCreate(

                [
                    'label' => $schedule['label']
                ],

                [

                    'days_of_week' => $schedule['days_of_week'],

                    'start_time' => $schedule['start_time'],

                    'end_time' => $schedule['end_time'],

                    'language' => null,

                    'is_livestreamed' => false,

                    'location_id' => $mainSanctuary->id,

                    'is_active' => true,

                ]

            );

        }
    }
}