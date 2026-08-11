<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Pre-Cana / Marriage Preparation Seminar has its own schedule,
 * completely separate from the wedding's own Event Date/Event Time —
 * see App\Models\WeddingSeminar. One row per wedding reservation (a
 * couple only ever has one active seminar booking at a time; rescheduling
 * updates this row rather than creating a new one, so history of a
 * cancelled/superseded seminar isn't tracked separately).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wedding_seminars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->date('seminar_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('venue')->nullable();
            $table->string('venue_other')->nullable();
            // Array of { type: 'priest'|'lay_facilitator'|'couple_facilitator'|'other', name, priest_id? }.
            // A priest is never mandatory here (unlike the wedding's own
            // Assigned Priest) — this can be empty, one, or several.
            $table->json('facilitators')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending | scheduled | completed | not_required
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wedding_seminars');
    }
};