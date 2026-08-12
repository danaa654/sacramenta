<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The three Sacramenta roles. Super Admin has full system access
     * including Manage Users; Administrator has full day-to-day access
     * except user management/system settings; Staff has the working
     * subset needed for parish office reservation/scheduling tasks.
     * See App\Policies\UserPolicy and App\Policies\ReservationPolicy
     * for exactly what each role can/can't do.
     */
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMINISTRATOR = 'administrator';

    public const ROLE_STAFF = 'staff';

    /** Kept for compatibility with any old code/data still referencing 'admin'. */
    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMINISTRATOR,
        self::ROLE_STAFF,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /**
     * The attributes that are mass assignable.
     *
     * Deliberately does NOT include 'role' or 'status' — role/status
     * changes must go through an explicit Super Admin action
     * (UserController::changeRole / toggleStatus), never through a
     * form a user submits about themselves (e.g. ProfileController::
     * update), or any other endpoint that mass-assigns from request
     * input. Promoting/demoting/deactivating a user is intentionally a
     * separate, deliberate write — see UserController.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMINISTRATOR;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    /**
     * True for Super Admin OR Administrator — the two roles with full
     * reservation-management access (create/edit/confirm/cancel plus
     * the three destructive/override actions gated by
     * ReservationPolicy: delete, correct a locked record, and override
     * a detected scheduling conflict). Staff gets the day-to-day
     * subset but not those three.
     *
     * Kept as `isAdmin()` (rather than renamed) so the existing
     * ReservationPolicy — which predates the three-tier role model —
     * keeps working unchanged.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMINISTRATOR], true);
    }

    /**
     * Only a Super Admin can reach Manage Users, Create User, Change
     * Role, Reset Password, or Activity Logs/Settings. See UserPolicy.
     */
    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_ADMINISTRATOR => 'Administrator',
            self::ROLE_STAFF => 'Staff',
            default => ucfirst((string) $this->role),
        };
    }
}