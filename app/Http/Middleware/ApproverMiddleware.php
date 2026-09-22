<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApproverMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !(auth()->user()->isAdmin() || auth()->user()->isApprover())) {
            abort(403, 'Approval access required.');
        }

        return $next($request);
    }
}
