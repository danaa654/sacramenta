<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parish-wide blocked periods (Christmas, Holy Week, Parish Fiesta,
     * Church Maintenance, Retreat, etc.) that the Church Availability
     * Engine refuses new reservations against, unless an administrator
     * explicitly overrides — see ChurchAvailabilityService::isBlocked()
     * and ReservationController's override flow.
     */
    public function up(): void
    {
        Schema::create('blocked_dates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            // Future-ready: null means "blocks every venue". A specific
            // location_id would let a parish block just one venue (e.g.
            // "Parish of the Holy Sacraments closed for repainting") once a second venue
            // exists, without changing this table's shape.
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_dates');
    }
};