<?php

namespace App\Support;

use App\Models\RevokedJwtToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

class JwtService
{
    public function issueToken(User $user): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $issuedAt = CarbonImmutable::now();
        $expiresAt = $issuedAt->addMinutes((int) config('security.jwt_ttl', 30));

        $payload = [
            'iss' => config('app.url') ?: config('app.name', 'laravel'),
            'sub' => (string) $user->getKey(),
            'email' => $user->email,
            'role' => $user->role,
            'jti' => (string) Str::uuid(),
            'iat' => $issuedAt->timestamp,
            'nbf' => $issuedAt->timestamp,
            'exp' => $expiresAt->timestamp,
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $this->secret(), true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public function decodeToken(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $headerJson = $this->base64UrlDecode($encodedHeader);
        $payloadJson = $this->base64UrlDecode($encodedPayload);

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);

        if (!is_array($header) || !is_array($payload)) {
            throw new RuntimeException('Invalid token payload.');
        }

        if (($header['alg'] ?? null) !== 'HS256') {
            throw new RuntimeException('Unsupported signing algorithm.');
        }

        $expectedSignature = hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret(), true);
        $providedSignature = $this->base64UrlDecode($encodedSignature);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new RuntimeException('Invalid token signature.');
        }

        $now = CarbonImmutable::now()->timestamp;
        if (($payload['nbf'] ?? 0) > $now) {
            throw new RuntimeException('Token not yet valid.');
        }
        if (($payload['exp'] ?? 0) <= $now) {
            throw new RuntimeException('Token expired.');
        }
        if (empty($payload['jti'])) {
            // Tokens issued before the revocation upgrade intentionally stop
            // working so every active mobile session has a revocable ID.
            throw new RuntimeException('Token does not contain a revocation identifier.');
        }

        return $payload;
    }

    public function isRevoked(array $payload, User $user): bool
    {
        $jti = (string) ($payload['jti'] ?? '');
        if ($jti === '' || RevokedJwtToken::where('jti', $jti)->exists()) {
            return true;
        }

        $issuedAt = (int) ($payload['iat'] ?? 0);
        if ($user->jwt_revoked_before && $issuedAt <= $user->jwt_revoked_before->timestamp) {
            return true;
        }

        return false;
    }

    public function revokeToken(string $token, ?User $user = null, string $reason = 'logout'): void
    {
        $payload = $this->decodeToken($token);
        $jti = (string) $payload['jti'];

        RevokedJwtToken::firstOrCreate(
            ['jti' => $jti],
            [
                'user_id' => $user?->getKey() ?: (int) ($payload['sub'] ?? 0) ?: null,
                'expires_at' => CarbonImmutable::createFromTimestamp((int) $payload['exp']),
                'revoked_at' => now(),
                'reason' => mb_substr($reason, 0, 100),
            ]
        );
    }

    public function purgeExpiredRevocations(): void
    {
        RevokedJwtToken::where('expires_at', '<=', now())->delete();
    }

    private function secret(): string
    {
        $secret = (string) config('security.jwt_secret');
        if ($secret === '') {
            throw new RuntimeException('JWT secret is not configured.');
        }

        return $secret;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64 encoding in token.');
        }

        return $decoded;
    }
}
