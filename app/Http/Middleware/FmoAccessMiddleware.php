<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every Facilities Management route.
 *
 * This is the backend half of the separation: hiding the sidebar links is not
 * enough, so an Asset Management Super Admin (or Admin, approver, requestor)
 * that types an /fmo/... or /facilities/... URL by hand is stopped here with a
 * 403 before the controller ever runs.
 */
class FmoAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user || !$user->canManageFacilities()) {
            abort(403, 'Facilities Management access required. This area belongs to the FMO Super Admin and FMO staff only.');
        }

        return $next($request);
    }
}
