<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected array $defaults = [
        'Main Sanctuary',
        'Parish Hall',
        'Chapel',
        'San Isidro Chapel',
        'Sto. Niño Chapel',
        'Our Lady of Fatima Chapel',
        'San Roque Chapel',
        'Sacred Heart Chapel',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->defaults as $name) {
            DB::table('locations')->insertOrIgnore([
                'name' => $name,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('locations')->whereIn('name', $this->defaults)->delete();
    }
};