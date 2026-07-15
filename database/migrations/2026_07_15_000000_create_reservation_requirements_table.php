<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each row is a single checklist item belonging to a reservation
     * (e.g. "Baptismal Certificate (Groom)" for a wedding). We chose a
     * dedicated table over a JSON column on `reservations` because:
     *
     *  - Toggling one checkbox becomes a single-row UPDATE instead of
     *    reading, mutating, and rewriting an entire JSON blob.
     *  - "3 of 5 complete" and "is this reservation confirmable" become
     *    simple COUNT/WHERE queries instead of PHP array loops.
     *  - Each item can carry its own note and (later, if needed) its own
     *    timestamp/who-checked-it-off without reshaping a JSON schema.
     *
     * The label is snapshotted onto the row at creation time (rather than
     * looked up live from config) so that if the checklist definition for
     * a type changes later, existing reservations still show the wording
     * that was in effect when they were created.
     */
    public function up(): void
    {
        Schema::create('reservation_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->boolean('is_completed')->default(false);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['reservation_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_requirements');
    }
};