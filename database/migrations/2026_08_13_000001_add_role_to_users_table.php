<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every route in Sacramenta so far only requires auth+verified — any
 * logged-in user can hard-delete a confirmed reservation, correct a
 * locked/completed sacramental record, or override a schedule conflict
 * with nothing more than checking a box. This column is the minimum
 * piece of data needed to actually gate those actions: a simple
 * 'staff' | 'admin' role, checked by App\Policies\ReservationPolicy.
 *
 * Deliberately NOT a full permissions/roles table — Sacramenta only
 * needs two tiers today (parish office staff vs. the administrator who
 * can override/delete/correct). If that grows, this is the column to
 * replace with a proper roles table, not extend with more booleans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('staff')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};