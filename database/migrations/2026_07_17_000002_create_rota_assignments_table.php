<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per volunteer/ministry slot needed for a reservation (e.g.
 * "2 Altar Servers", "1 Commentator/Lector", "1 Choir group"). Seeded
 * automatically from config/rota_roles.php when a reservation is
 * confirmed, mirroring how reservation_requirements are seeded on create.
 *
 * `slot_number` distinguishes rows when a role needs more than one person
 * (e.g. Altar Server #1 vs #2) — pairs with `role_key` for a stable unique
 * key per reservation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rota_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('role_key');
            $table->string('role_label');
            $table->unsignedTinyInteger('slot_number')->default(1);
            $table->string('volunteer_name')->nullable();
            $table->string('status')->default('needed'); // needed, requested, confirmed
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['reservation_id', 'role_key', 'slot_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rota_assignments');
    }
};