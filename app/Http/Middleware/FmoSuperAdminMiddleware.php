<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the highest-privilege Facilities routes (FMO user management and
 * destructive venue/reservation actions) to the FMO Super Admin.
 */
class FmoSuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user || !$user->isFmoSuperAdmin()) {
            abort(403, 'FMO Super Admin access required.');
        }

        return $next($request);
    }
}
