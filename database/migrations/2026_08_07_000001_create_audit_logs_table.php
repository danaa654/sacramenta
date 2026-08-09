<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every scheduling action the Church Availability & Conflict
     * Detection Engine cares about — Reservation Created/Updated/
     * Cancelled, Conflict Prevented, Conflict Overridden, Mass Schedule
     * Updated — gets a row here. See App\Services\AuditLogger.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // e.g. reservation_created, conflict_overridden
            $table->string('description');
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};