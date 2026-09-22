<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Laravel's built-in guest middleware always sends already-logged-in users to the
     * hardcoded "dashboard" route. That route is admin-only content, so any other role
     * (Dean, Requestor, FMO, etc.) landing on "/" or "/login" while already signed in
     * got bounced straight into the admin dashboard — a page not even listed in their
     * own sidebar. Route them to their own homeRouteName() instead.
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                $redirectTo = $user && method_exists($user, 'homeRouteName')
                    ? $user->homeRouteName()
                    : 'dashboard';

                return $request->expectsJson()
                    ? new Response('', 200)
                    : redirect()->route($redirectTo);
            }
        }

        return $next($request);
    }
}
