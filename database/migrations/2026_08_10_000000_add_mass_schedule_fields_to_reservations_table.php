<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports one-off / special Mass Schedule entries (Simbang Gabi, Christmas
 * Eve Mass, Fiesta Mass, etc.) created directly by an admin, as opposed to
 * regular Masses generated from a `mass_schedules` weekly template.
 *
 * These are still plain `reservations` rows with type = 'mass' — no new
 * table — just two extra nullable columns:
 *
 *   - title:     the event name shown in the UI ("Simbang Gabi"), instead
 *                of borrowing contact_name for display purposes. Regular
 *                template-generated Masses leave this null and keep using
 *                contact_name as before.
 *   - series_id: a UUID shared by every occurrence created together from
 *                one "repeat daily from X to Y" submission, so the admin
 *                can see/manage them as one series without them being
 *                live-linked the way mass_schedule_id-based recurrence is.
 *
 * Duration overrides for special Masses live in the existing `details` JSON
 * column (details->duration_minutes) rather than a new column — see
 * App\Support\ReservationDuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('title')->nullable()->after('type');
            $table->uuid('series_id')->nullable()->after('mass_schedule_id');
            $table->index('series_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['series_id']);
            $table->dropColumn(['title', 'series_id']);
        });
    }
};