<?php

namespace App\Policies;

use App\Models\User;

/**
 * Manage Users is Super-Admin-only, full stop. Laravel resolves this
 * automatically for User via naming convention (App\Models\User ->
 * App\Policies\UserPolicy) — no manual registration needed.
 *
 * Every method here is checked in UserController via $this->authorize()
 * — never only hidden in the sidebar/UI. See Sidebar.vue for the
 * matching frontend-only visibility rules, which are a convenience,
 * not the actual security boundary.
 */
class UserPolicy
{
    /**
     * View the Manage Users list / any single user's detail.
     */
    public function viewAny(User $user): bool
    {
        return $user->canManageUsers();
    }

    public function view(User $user, User $target): bool
    {
        return $user->canManageUsers();
    }

    /**
     * Create User (any role, including another Super Admin).
     */
    public function create(User $user): bool
    {
        return $user->canManageUsers();
    }

    /**
     * Edit Full Name / Email / Role / Status from the Edit User form.
     */
    public function update(User $user, User $target): bool
    {
        return $user->canManageUsers();
    }

    /**
     * Reset another user's password (Super Admin never sees/sets the
     * existing password — see UserController::resetPassword).
     */
    public function resetPassword(User $user, User $target): bool
    {
        return $user->canManageUsers();
    }

    /**
     * Change Role — same permission as update(), but kept as its own
     * policy method because UserController checks it separately from
     * a plain profile edit, and it's where the "last active Super
     * Admin can't be demoted" rule (§13) is enforced.
     */
    public function changeRole(User $user, User $target): bool
    {
        if (! $user->canManageUsers()) {
            return false;
        }

        // Demoting the only active Super Admin (including demoting
        // themselves) would leave Sacramenta with none — never allowed,
        // regardless of who is asking.
        if ($target->isSuperAdmin() && $target->isActive() && User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->count() <= 1) {
            return false;
        }

        return true;
    }

    /**
     * Activate / Deactivate. Enforces §13/§20: the last active Super
     * Admin can never be deactivated, by themselves or anyone else.
     */
    public function toggleStatus(User $user, User $target): bool
    {
        if (! $user->canManageUsers()) {
            return false;
        }

        if ($target->isSuperAdmin() && $target->isActive() && User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->count() <= 1) {
            return false;
        }

        return true;
    }
}