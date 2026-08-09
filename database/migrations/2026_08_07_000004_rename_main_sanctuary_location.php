<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The parish's single venue was originally seeded as "Main Sanctuary".
 * Renamed to "Parish of the Holy Sacraments" as the church's proper name,
 * shown wherever that venue appears (reservation venue field, availability
 * messages, certificates, etc.). Data-only migration — safe to run on a
 * database that already has real reservations; it only updates the
 * `locations.name` value, nothing else.
 */
return new class extends Migration
{
    protected string $oldName = 'Main Sanctuary';

    protected string $newName = 'Parish of the Holy Sacraments';

    public function up(): void
    {
        DB::table('locations')
            ->where('name', $this->oldName)
            ->update(['name' => $this->newName]);
    }

    public function down(): void
    {
        DB::table('locations')
            ->where('name', $this->newName)
            ->update(['name' => $this->oldName]);
    }
};