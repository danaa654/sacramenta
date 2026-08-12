<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a Super Admin resets someone's password (UserController::
 * resetPassword — whether it generated one or the Super Admin typed a
 * simple one), that user's must_change_password flag is set. This
 * middleware catches every authenticated request after that and bounces
 * them to the Profile page — where Auth\PasswordController::update
 * clears the flag once they've set their own password — before they can
 * reach anything else in the app.
 *
 * Deliberately allows through: the profile routes themselves (or the
 * user would be stuck unable to reach the form that clears the flag),
 * and logout (so they can always back out instead of being trapped).
 */
class EnsurePasswordIsNotExpired
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $exempt = $request->routeIs('profile.*')
            || $request->routeIs('logout')
            || $request->routeIs('password.update');

        if ($user && $user->must_change_password && ! $exempt) {
            return redirect()
                ->route('profile.edit')
                ->with('warning', 'Your password was reset by an administrator. Please set a new password to continue.');
        }

        return $next($request);
    }
}