<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and attach OWASP-compliant security headers.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 1. Prevent Clickjacking (Layer 7 UI Redressing)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // 2. Prevent MIME Sniffing Attack (Layer 7 Content Inspection)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 3. Prevent Reflected XSS (Legacy Browser Guard)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // 4. Control Referrer Information Leakage
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 5. Restrict Unused Browser Device Features (Permissions-Policy)
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // 6. HTTP Strict Transport Security (HSTS - Layer 7 over TLS/Layer 6)
        if ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
