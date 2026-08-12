<?php

namespace App\Policies;

use App\Models\User;

/**
 * Per §3/§4: Activity Logs is on the Super Admin AND Administrator
 * sidebars, but not Staff's. Manage Users itself stays Super-Admin-
 * only — see UserPolicy — this is a separate, slightly wider grant.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdministrator();
    }
}