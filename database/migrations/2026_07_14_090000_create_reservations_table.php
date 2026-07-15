<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->date('event_date');
            $table->time('event_time')->nullable();
            $table->foreignId('priest_id')->nullable()->constrained('priests')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, confirmed, completed, cancelled
            $table->json('details')->nullable();
            $table->decimal('offering_amount', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};