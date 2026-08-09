<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminSeeder::class);
        $this->call(PriestSeeder::class);
        $this->call(MassScheduleSeeder::class);
        $this->call(ReservationSeeder::class);
        this->call(BlockedDateSeeder::class);
    }
}