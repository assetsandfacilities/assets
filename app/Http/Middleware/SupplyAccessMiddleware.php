<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks direct URL access to OPEX Inventory / Requisitions for requestors
 * whose department is flagged as supply-restricted (e.g. Facilities
 * Management Office). Only affects plain requestors — admins, dean/executive
 * approvers, and other roles that also touch these routes for approval or
 * management purposes are unaffected, since canRequestSupplies() only ever
 * returns false for the requestor role.
 */
class SupplyAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->isRequestor() && !$user->canRequestSupplies()) {
            return redirect()->route($user->homeRouteName())
                ->with('error', 'OPEX Inventory and Requisitions are not available for your department.');
        }

        return $next($request);
    }
}
