<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JwtAuthMiddleware
{
    public function __construct(private JwtService $jwtService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json(['message' => 'JWT bearer token is required.'], 401);
        }

        try {
            $payload = $this->jwtService->decodeToken($bearerToken);
            $user = User::with('department')->find($payload['sub'] ?? null);

            if (!$user) {
                return response()->json(['message' => 'Token user no longer exists.'], 401);
            }

            if ($this->jwtService->isRevoked($payload, $user)) {
                return response()->json(['message' => 'This mobile session has been revoked. Please sign in again.'], 401);
            }

            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
            $request->attributes->set('jwt_payload', $payload);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Invalid, expired, or revoked JWT.',
                'error' => $e->getMessage(),
            ], 401);
        }

        return $next($request);
    }
}
