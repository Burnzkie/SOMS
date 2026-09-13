<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security headers to every response (web + API).
 *
 * CSP allow-list is intentionally scoped to what the app actually loads
 * today: Google Fonts CSS/fonts, the FullCalendar CDN bundle used on the
 * officer calendar, and R2/S3-hosted avatar images over HTTPS. 'unsafe-inline'
 * is kept for script-src/style-src because the Blade views use inline
 * <script>/style= extensively (quick-create modal, chart bootstrapping,
 * etc.) — tightening this to a nonce-based policy is future work, not a
 * blocker for this pass. See README "Security Headers" section.
 *
 * Added registration: bootstrap/app.php, global 'web' + 'api' middleware.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=()'
        );

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]));

        // Only meaningful (and only sent) over an actual HTTPS connection —
        // sending it over plain HTTP is a no-op per spec, and in local dev
        // (http://localhost) it would just be dead weight.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Best-effort — not all SAPIs allow removing this after the fact,
        // but harmless to try.
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
