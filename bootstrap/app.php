<?php

require_once __DIR__.'/../app/Support/polyfills.php';

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->trustProxies(at: '*');

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'approver' => \App\Http\Middleware\ApproverMiddleware::class,
            'supply_access' => \App\Http\Middleware\SupplyAccessMiddleware::class,
            'fmo_access' => \App\Http\Middleware\FmoAccessMiddleware::class,
            'fmo_super_admin' => \App\Http\Middleware\FmoSuperAdminMiddleware::class,
            'asset_management' => \App\Http\Middleware\AssetManagementMiddleware::class,
            'auth.jwt' => \App\Http\Middleware\JwtAuthMiddleware::class,
            'erp.api' => \App\Http\Middleware\ErpApiKeyMiddleware::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         | An idle tab outlives its session (SESSION_LIFETIME, 120 minutes by
         | default) while the CSRF token baked into the already-rendered HTML
         | stays on the page. The next POST from that page -- most visibly the
         | Logout button, which is the one control users press after leaving a
         | tab open -- threw a bare TokenMismatchException and rendered an
         | unstyled error page.
         |
         | Logout is idempotent: if the session is gone the user is already
         | signed out, so send them to the login screen as a success. Every
         | other stale POST gets an explicit "session expired" notice instead
         | of a dead end.
         */
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired. Please sign in again.',
                ], 419);
            }

            if ($request->routeIs('logout') || $request->is('logout')) {
                return redirect()->route('login')->with('success', 'You have been signed out.');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Your session expired before that was submitted. Please sign in again.',
            ]);
        });
    })->create();