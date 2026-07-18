<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'notifications' => $user
                ? $user->notifications()
                    ->latest()
                    ->limit(20)
                    ->get()
                    ->map(fn ($n) => [
                        'id' => $n->id,
                        'kind' => $n->data['kind'] ?? 'reminder',
                        'title' => $n->data['title'] ?? '',
                        'body' => $n->data['body'] ?? '',
                        'url' => $n->data['url'] ?? null,
                        'read' => $n->read_at !== null,
                        'created_at' => $n->created_at->diffForHumans(),
                    ])
                : [],
            'unreadNotificationsCount' => $user ? $user->unreadNotifications()->count() : 0,
        ];
    }
}