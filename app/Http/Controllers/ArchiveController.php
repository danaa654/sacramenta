<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only "past records" view: reservations that are done, one way or
 * another — completed (the event happened) or archived (no longer active
 * but kept for the record, e.g. superseded/cancelled-and-filed regular
 * Masses). Deliberately no edit/delete/status actions here; this is a
 * history log, not a working list. Use Reservations (index/edit/destroy)
 * or Masses (cancel/restore) for anything that changes a record's status.
 */
class ArchiveController extends Controller
{
    public function index(Request $request): Response
    {
        $reservations = Reservation::with('priest:id,name', 'location:id,name')
            ->whereIn('status', ['completed', 'archived'])
            ->when($request->string('search')->toString(), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('contact_name', 'like', "%{$search}%")
                        ->orWhere('receipt_number', 'like', "%{$search}%");
                });
            })
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('event_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Archives/Index', [
            'reservations' => $reservations,
            'filters' => $request->only(['search', 'type', 'status']),
        ]);
    }
}