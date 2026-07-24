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

        $user = User::query()->where('api_key', $apiKey)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'API key tidak valid.',
                'error' => 'invalid_api_key',
            ], 401);
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

        $query = $request->query('api_key');
        if (is_string($query) && $query !== '') {
            return $query;
        }

        return null;
    }
}
