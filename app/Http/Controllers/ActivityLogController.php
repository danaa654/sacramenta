<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only view over App\Models\AuditLog (written by
 * App\Services\AuditLogger from ReservationController, MassSchedule
 * actions, and now UserController). Super Admin and Administrator can
 * both view it (§3 — Activity Logs is on both role lists); only
 * Manage Users itself is Super-Admin-only.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $userId = $request->integer('user_id') ?: null;
        $action = $request->string('action')->toString();
        $date = $request->string('date')->toString();

        $logs = AuditLog::query()
            ->with(['user:id,name', 'reservation:id,contact_name,type'])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($date, fn ($q) => $q->whereDate('created_at', $date))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'user_id' => $userId,
                'action' => $action ?: null,
                'date' => $date ?: null,
            ],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}