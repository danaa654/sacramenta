<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * contact_name / contact_mobile were originally added as NOT NULL,
 * back when every reservation type had a single "Contact Person"
 * making the booking.
 *
 * Pamisa sa Kalag broke that assumption: it's an admin-entered list
 * of deceased names with no single contact person, so
 * StoreReservationRequest already marks contact_name/contact_mobile
 * as 'nullable' for that type (see validation rules) — but the DB
 * columns were never relaxed to match, causing:
 *   SQLSTATE[23000]: Integrity constraint violation: 1048
 *   Column 'contact_name' cannot be null
 *
 * This migration brings the schema in line with the validation rules
 * that already exist in the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->change();
            $table->string('contact_mobile')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Backfill so the column can safely be reverted to NOT NULL.
            \DB::table('reservations')->whereNull('contact_name')->update(['contact_name' => 'N/A']);
            \DB::table('reservations')->whereNull('contact_mobile')->update(['contact_mobile' => 'N/A']);

            $table->string('contact_name')->nullable(false)->change();
            $table->string('contact_mobile')->nullable(false)->change();
        });
    }
};