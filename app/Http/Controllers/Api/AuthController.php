<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Support\JwtService;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private JwtService $jwtService)
    {
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::with('department')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->is_approved) {
            return response()->json(['message' => 'Your account is still pending admin approval.'], 403);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill([
                'password' => Hash::driver('bcrypt')->make($request->password, [
                    'rounds' => (int) config('security.bcrypt_rounds', 12),
                ]),
            ])->save();
            $user->refresh();
        }

        // Keep the blacklist compact; expired JWTs can no longer be used anyway.
        $this->jwtService->purgeExpiredRevocations();
        $token = $this->jwtService->issueToken($user);

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $token,
            'expires_in_minutes' => (int) config('security.jwt_ttl', 30),
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->bearerToken();
        if ($token) {
            try {
                $this->jwtService->revokeToken($token, $request->user(), 'logout');
            } catch (Throwable) {
                // The request already passed JWT middleware. A concurrent expiry
                // should not turn an otherwise successful logout into a 500.
            }
        }

        return response()->json(['message' => 'Logged out successfully. The server has revoked this JWT.']);
    }

    public function logoutAll(Request $request)
    {
        $user = $request->user();
        $user->forceFill(['jwt_revoked_before' => now()])->save();

        if ($request->bearerToken()) {
            try {
                $this->jwtService->revokeToken($request->bearerToken(), $user, 'logout_all');
            } catch (Throwable) {
                // The user-wide timestamp above already invalidates the session.
            }
        }

        return response()->json([
            'message' => 'All mobile JWT sessions issued before now have been revoked.',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('department'));
    }
}
