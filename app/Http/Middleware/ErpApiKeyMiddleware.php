<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine-to-machine authentication for the Procurement / ERP export API.
 *
 * The ERP secret is never stored in the database or source code. Configure it
 * through ERP_API_KEY on the server and send it as X-ERP-API-Key. An optional
 * comma-separated ERP_ALLOWED_IPS list can further restrict callers.
 */
class ErpApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('security.erp_api_key', '');
        if ($configuredKey === '') {
            return response()->json([
                'message' => 'ERP integration is not configured on this server.',
            ], 503);
        }

        $providedKey = (string) $request->header('X-ERP-API-Key', '');
        if ($providedKey === '' || !hash_equals($configuredKey, $providedKey)) {
            return response()->json(['message' => 'Invalid ERP API credentials.'], 401);
        }

        $allowedIps = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('security.erp_allowed_ips', ''))
        )));

        if ($allowedIps !== [] && !in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['message' => 'This IP address is not allowed to use the ERP API.'], 403);
        }

        return $next($request);
    }
}
