<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pamisa sa Kalag does not book independent church time — it attaches to
 * one specific, already-existing Mass occurrence (a `reservations` row
 * with type = 'mass', e.g. "Dec 16, 2026, 6:00 AM, Fr. Bartolome Reyes").
 *
 * `linked_mass_reservation_id` is that live link: a self-referencing FK
 * pointing at the exact Mass occurrence row, NOT at the `mass_schedules`
 * weekly template (see `mass_schedule_id`, which is provenance-only and
 * never live-linked). This is deliberately a *different* column from
 * `mass_schedule_id` — that one is a dedup/traceability pointer set only
 * on auto-generated Masses; this one is the actual live relationship a
 * Pamisa sa Kalag reservation depends on, and can be set on ANY Mass
 * occurrence (regular or special/Simbang-Gabi-style).
 *
 * `nullOnDelete()` intentionally does NOT null this out silently on its
 * own — Mass reservation rows are never hard-deleted in this system
 * (cancel() only flips status), so in practice this FK stays populated
 * and `mass_link_needs_review` (see below) is how a broken link surfaces.
 *
 * `mass_link_needs_review` / `mass_link_review_reason`: if the linked Mass
 * is later cancelled, or its date/time changes, the Pamisa sa Kalag
 * reservation is flagged here instead of silently drifting out of sync —
 * see Reservation::bootPamisaMassLinkObserver().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('linked_mass_reservation_id')->nullable()->after('mass_schedule_id')
                ->constrained('reservations')->nullOnDelete();
            $table->boolean('mass_link_needs_review')->default(false)->after('linked_mass_reservation_id');
            $table->string('mass_link_review_reason')->nullable()->after('mass_link_needs_review');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_mass_reservation_id');
            $table->dropColumn(['mass_link_needs_review', 'mass_link_review_reason']);
        });
    }
};