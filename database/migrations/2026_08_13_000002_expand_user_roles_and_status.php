<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expands Sacramenta's user access from the original two-tier
 * staff/admin split into the three-tier Super Admin / Administrator /
 * Staff model needed for Manage Users + RBAC.
 *
 * - 'status' (active/inactive) replaces "delete a user" — deactivated
 *   users keep their row, their AuditLog entries, and every record
 *   they're attached to (created_by, confirmed_by, etc.) intact.
 * - 'last_login_at' powers the "Last Login" column on Manage Users.
 * - The data step below re-maps the two roles that already exist so
 *   the currently-working login keeps working with no manual fixup:
 *   the prior 'admin' role becomes 'super_admin' (Sacramenta's single
 *   existing administrator account is preserved as the first Super
 *   Admin, per spec — nobody has to register a second account), and
 *   'staff' is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->after('role');
            $table->timestamp('last_login_at')->nullable()->after('status');
        });

        // Re-map the old two-tier values to the new three-tier roles.
        // Anyone who was 'admin' becomes 'super_admin' so the existing
        // login/account keeps its full access with nothing to redo.
        User::query()->where('role', 'admin')->update(['role' => User::ROLE_SUPER_ADMIN]);
        User::query()->where('role', 'staff')->update(['role' => User::ROLE_STAFF]);

        // Edge case: a fresh install with no admin/staff rows yet, or a
        // users table with no rows at all, needs nothing further here —
        // the first account created via `php artisan db:seed` or
        // tinker should be given role=super_admin explicitly.
    }

    public function down(): void
    {
        // Best-effort revert: collapse administrator back into admin,
        // drop the new columns. (super_admin also maps back to 'admin'
        // since that's the role it originated from.)
        User::query()->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMINISTRATOR])
            ->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'last_login_at']);
        });
    }
};