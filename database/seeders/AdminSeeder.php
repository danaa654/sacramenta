<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@sacramenta.test'],
            [
                'name' => 'Admin',
                'password' => Hash::make('admin@sacramenta.test'),
                'email_verified_at' => now(),
            ]
        );
    }
}