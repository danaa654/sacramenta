<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical spaces the parish can double-book: Main Sanctuary, Parish Hall,
 * Chapel, plus any barangay/kapilya chapels. Generalizes what the
 * chapel_mass-only `details->chapel` string used to do, so ANY reservation
 * type (wedding, burial, school_mass, etc.) can be pinned to a venue and
 * conflict-checked against everything else booked in that same room.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('priest_id')
                ->constrained('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::dropIfExists('locations');
    }
};