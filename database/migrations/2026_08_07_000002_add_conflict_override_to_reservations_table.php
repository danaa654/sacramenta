<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks whether a reservation was saved by overriding a detected
     * church-schedule conflict (Church Availability & Conflict Detection
     * Engine), and who/why — the full record also lands in audit_logs,
     * but keeping a copy on the reservation itself lets the Show page
     * surface "this was saved despite a conflict" at a glance without an
     * extra join.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->boolean('conflict_overridden')->default(false)->after('status');
            $table->string('override_reason')->nullable()->after('conflict_overridden');
            $table->foreignId('overridden_by')->nullable()->after('override_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable()->after('overridden_by');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overridden_by');
            $table->dropColumn(['conflict_overridden', 'override_reason', 'overridden_at']);
        });
    }
};