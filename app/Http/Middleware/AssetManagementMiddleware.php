<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mirror image of FmoAccessMiddleware.
 *
 * Blocks Facilities-side accounts (FMO Super Admin, FMO staff) from reaching
 * Asset Management / OPEX routes by direct URL, even for the routes that are
 * otherwise open to every signed-in user (Requisitions, Activity Proposals,
 * Notifications-adjacent asset pages, etc).
 */
class AssetManagementMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && !$user->canAccessAssetManagement()) {
            abort(403, 'Asset Management is not available for Facilities Management accounts.');
        }

        return $next($request);
    }
}
