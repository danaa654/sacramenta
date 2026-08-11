<?php

namespace App\Http\Controllers;

use App\Notifications\ReservationActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Dedicated "View All Notifications" page (request #12). Filters by
     * read state and by the same broad groupings shown in the bell
     * dropdown -- Upcoming, Requirements, Overdue, Weddings, Marriage
     * Preparation -- mapped from the `kind` stored in each notification's
     * data blob, so no separate notification "category" table is needed.
     */
    public function index(Request $request): Response
    {
        $filter = $request->query('filter', 'all');

        $kindsByFilter = [
            'upcoming' => ['reminder', 'wedding_seminar_upcoming', 'wedding_upcoming'],
            'requirements' => ['wedding_requirements', 'wedding_documents'],
            'overdue' => ['wedding_seminar_overdue', 'wedding_activity_overdue'],
            'weddings' => ['wedding_upcoming', 'wedding_requirements', 'wedding_documents'],
            'marriage_preparation' => [
                'wedding_seminar_upcoming',
                'wedding_seminar_pending',
                'wedding_seminar_overdue',
                'wedding_activity_pending',
                'wedding_activity_overdue',
                'wedding_requirements',
                'wedding_documents',
            ],
        ];

        $query = $request->user()->notifications()->latest();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif (isset($kindsByFilter[$filter])) {
            $query->where(function ($q) use ($kindsByFilter, $filter) {
                foreach ($kindsByFilter[$filter] as $kind) {
                    $q->orWhere('data->kind', $kind);
                }
            });
        }

        $notifications = $query->paginate(30)->withQueryString()->through(fn ($n) => [
            'id' => $n->id,
            'kind' => $n->data['kind'] ?? 'reminder',
            'priority' => $n->data['priority'] ?? ReservationActivityNotification::PRIORITY_INFO,
            'title' => $n->data['title'] ?? '',
            'body' => $n->data['body'] ?? '',
            'category' => $n->data['category'] ?? null,
            'action_label' => $n->data['action_label'] ?? 'View Reservation',
            'url' => $n->data['url'] ?? null,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at->diffForHumans(),
        ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filter' => $filter,
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()
            ->notifications()
            ->where('id', $notification)
            ->first()
            ?->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}