<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage Users — Super Admin only. Every action here is gated by
 * UserPolicy (via $this->authorize()), not just hidden in the sidebar
 * (Sidebar.vue) — see §5/§20 of the spec: "Never assume that hiding a
 * button is security." routes/web.php also requires auth+verified
 * before any of these are reached.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = $request->string('search')->toString();
        $role = $request->string('role')->toString();
        $status = $request->string('status')->toString();

        $users = User::query()
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search ?: null,
                'role' => $role ?: null,
                'status' => $status ?: null,
            ],
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role' => ['required', Rule::in(User::ROLES)],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // 'role' and 'status' are deliberately excluded from $fillable
        // (see User model) — set explicitly here, the one place a new
        // account's access level is decided.
        $user->forceFill([
            'role' => $data['role'],
            'status' => $data['status'],
        ])->save();

        AuditLogger::log(
            'user_created',
            "{$request->user()->name} created a new {$user->roleLabel()} account for {$user->name}.",
            null,
            ['target_user_id' => $user->id, 'role' => $user->role]
        );

        return redirect()->route('users.index')->with('success', "{$user->name} was added as {$user->roleLabel()}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->fill($data)->save();

        AuditLogger::log(
            'user_updated',
            "{$request->user()->name} updated {$user->name}'s account details.",
            null,
            ['target_user_id' => $user->id]
        );

        return back()->with('success', "{$user->name}'s details were updated.");
    }

    /**
     * Change Role — separate endpoint from update() so the "last
     * active Super Admin can't be demoted" rule (§13) is enforced by
     * UserPolicy::changeRole() specifically, not folded into a general
     * profile edit.
     */
    public function changeRole(Request $request, User $user): RedirectResponse
    {
        $this->authorize('changeRole', $user);

        $data = $request->validate([
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        $previousRole = $user->roleLabel();
        $user->forceFill(['role' => $data['role']])->save();

        AuditLogger::log(
            'user_role_changed',
            "{$request->user()->name} changed {$user->name}'s role from {$previousRole} to {$user->roleLabel()}.",
            null,
            ['target_user_id' => $user->id, 'from' => $previousRole, 'to' => $user->roleLabel()]
        );

        return back()->with('success', "{$user->name} is now {$user->roleLabel()}.");
    }

    /**
     * Activate / Deactivate (§7) — never a hard delete, so historical
     * records (reservations created_by, AuditLog entries, etc.) stay
     * intact and attributed to the same account.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleStatus', $user);

        $newStatus = $user->isActive() ? User::STATUS_INACTIVE : User::STATUS_ACTIVE;
        $user->forceFill(['status' => $newStatus])->save();

        $verb = $newStatus === User::STATUS_ACTIVE ? 'activated' : 'deactivated';

        AuditLogger::log(
            "user_{$verb}",
            "{$request->user()->name} {$verb} {$user->name}'s account.",
            null,
            ['target_user_id' => $user->id]
        );

        return back()->with('success', ucfirst($verb).' '.$user->name.".");
    }

    /**
     * Super Admin sets a new password for another user — the current
     * password is never shown or retrievable (§10/§11). Generates a
     * secure random temporary password and returns it once, in the
     * flash message only, so it can be relayed to that staff member
     * out of band; it is never written to the Activity Log (§10).
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $temporaryPassword = Str::password(12);
        $user->forceFill(['password' => Hash::make($temporaryPassword)])->save();

        AuditLogger::log(
            'user_password_reset',
            "{$request->user()->name} reset {$user->name}'s password.",
            null,
            ['target_user_id' => $user->id]
        );

        return back()->with('success', "Password reset for {$user->name}.")
            ->with('temporaryPassword', $temporaryPassword);
    }
}