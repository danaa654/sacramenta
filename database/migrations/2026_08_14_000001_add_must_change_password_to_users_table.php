<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the "Super Admin sets a temporary/simple password, user changes
 * it themselves on next login" flow. UserController::resetPassword sets
 * this true (whether it generated a random password or the Super Admin
 * typed a simple one to read out loud to the person). The
 * EnsurePasswordIsNotExpired middleware then forces that user to the
 * Profile page before they can use anything else, and
 * Auth\PasswordController::update clears the flag once they've set
 * their own password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};