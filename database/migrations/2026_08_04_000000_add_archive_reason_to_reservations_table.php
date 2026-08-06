<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Archiving a reservation (status = 'archived') can mean two very
            // different things: the event was cancelled before it happened,
            // or it actually happened (status was 'completed') and staff
            // simply filed it into history afterward. Both used to collapse
            // into the same 'archived' status with no way to tell them
            // apart, which meant a genuinely completed-then-archived record
            // silently dropped out of the "Completed" stats. This column
            // records which of the two applied at the moment of archiving,
            // set automatically server-side (see ReservationController) —
            // never surfaced as an editable field.
            $table->string('archive_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('archive_reason');
        });
    }
};