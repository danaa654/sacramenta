<?php

namespace Database\Seeders;

use App\Models\Priest;
use Illuminate\Database\Seeder;

class PriestSeeder extends Seeder
{
    public function run(): void
    {
        $priests = [
            'Fr. Miguel Santos',
            'Fr. Bartolome Reyes',
            'Fr. Isidro Cruz',
        ];

        foreach ($priests as $name) {
            Priest::firstOrCreate(['name' => $name], ['status' => 'active']);
        }
    }
}