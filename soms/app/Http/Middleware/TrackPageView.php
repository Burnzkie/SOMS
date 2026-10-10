<?php
// app/Http/Middleware/TrackPageView.php

namespace App\Http\Middleware;

use App\Jobs\RecordPageViewJob;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * First-party usage analytics -- see the analytics_daily_hits migration
 * docblock for the privacy rationale (no PII, ever, by design -- not by
 * policy). This middleware only ever reads the route name/URI pattern
 * and dispatches a queued job; it never touches request body, headers,
 * IP, or session.
 *
 * Uses the route NAME when one exists (e.g. "admin.dashboard" for the
 * web/Blade routes, all of which are named), falling back to the
 * route's URI PATTERN (e.g. "api/v1/officer/events", not the resolved
 * URL) when it doesn't. This matters here specifically: every route in
 * routes/api.php, api_admin.php, api_officer.php, and api_student.php
 * is unnamed. A name-only design would have silently excluded every
 * single mobile request from analytics entirely -- caught during
 * testing, not guessed. The URI *pattern* (with `{param}` placeholders
 * still in it, not the literal resolved path) is used rather than
 * $request->path(), so a hit on `/api/v1/admin/officers/42/revoke`
 * still buckets under one low-cardinality identifier instead of one
 * unique row per officer ID ever revoked.
 */
class TrackPageView
{
    /**
     * Identifiers (route name OR uri-pattern fallback) that would
     * dominate the counts with noise rather than signal: the uptime
     * health check (hit by Render's own monitor on a fixed interval,
     * not a person -- registered with no name, uri pattern "up") and
     * the app-version check (the Flutter app polls this at every
     * launch and update-check, not a meaningful "page view" -- also
     * unnamed, uri pattern "api/v1/app-version").
     */
    private const EXCLUDED_IDENTIFIERS = [
        'up',
        'api/v1/app-version',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $route = $request->route();
        $identifier = $route?->getName() ?? $route?->uri();

        if ($identifier !== null && !in_array($identifier, self::EXCLUDED_IDENTIFIERS, true)) {
            $platform = $request->is('api/*') ? 'mobile' : 'web';
            RecordPageViewJob::dispatch(now()->toDateString(), $identifier, $platform);
        }

        return $response;
    }
}
