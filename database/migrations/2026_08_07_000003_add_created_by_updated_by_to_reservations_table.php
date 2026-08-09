<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administrative "who/when" tracking for a reservation RECORD, separate
 * from the event_date/event_time columns that already store the church
 * EVENT schedule. created_at/updated_at (Laravel's own timestamps, already
 * present via $table->timestamps() on the base table) are the system's
 * official reservation-created/last-updated moments — administrators never
 * enter them. created_by/updated_by capture which administrator was
 * responsible, alongside those timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};