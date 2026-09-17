<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->extractApiKey($request);

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key wajib. Kirim header Authorization: Bearer <api_key> atau X-API-Key.',
                'error' => 'missing_api_key',
            ], 401);
        }

        $hash = hash('sha256', $apiKey);
        $user = User::query()->where('api_key', $hash)->orWhere('api_key', $apiKey)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'API key tidak valid.',
                'error' => 'invalid_api_key',
            ], 401);
        }

        // Seamless auto-migration: if user matched via legacy plaintext key, upgrade to SHA-256 hash now
        if ($user->api_key === $apiKey) {
            $user->forceFill(['api_key' => $hash])->save();
        }

        // Bind authenticated user for controllers
        auth()->setUser($user);
        $request->setUserResolver(static fn () => $user);
        $request->attributes->set('api_user', $user);

        return $next($request);
    }

    private function extractApiKey(Request $request): ?string
    {
        $header = $request->header('X-API-Key')
            ?: $request->header('X-Api-Key');

        if (is_string($header) && $header !== '') {
            return trim($header);
        }

        $auth = $request->header('Authorization', '');
        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $auth, $m)) {
            return $m[1];
        }

        // Security Hardening: Do NOT accept api_key via URL query parameters
        // Query parameters leak to web server access logs, proxies, and browser history.

        return null;
    }
}
