<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
         $middleware->alias([
        'must.change.password'     => \App\Http\Middleware\MustChangePasswordMiddleware::class,
        'must.change.password.api' => \App\Http\Middleware\MustChangePasswordApiMiddleware::class,
        'role'                     => \App\Http\Middleware\RoleMiddleware::class,
    ]);

        // Security audit (Sep 2026) — baseline headers (CSP, X-Frame-Options,
        // X-Content-Type-Options, Referrer-Policy, Permissions-Policy, HSTS
        // on HTTPS) on every response, web and API alike.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // First-party analytics (Sep 2026) — see analytics_daily_hits
        // migration and TrackPageView docblocks for the privacy rationale.
        // Appended globally (web + API alike), same as SecurityHeaders
        // above; the middleware itself detects web vs mobile from the
        // request path rather than needing separate wiring per group.
        $middleware->append(\App\Http\Middleware\TrackPageView::class);

        // Render terminates SSL upstream and forwards requests over plain
        // HTTP internally. Without this, Laravel doesn't know the original
        // request was HTTPS, so it generates http:// URLs (form actions,
        // asset(), route(), etc.) — triggering Chrome's "not secure" mixed
        // content warning. '*' trusts any proxy IP since Render doesn't
        // publish a static edge IP range; only the listed X-Forwarded-*
        // headers are trusted, not arbitrary client input.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Error tracking (Sep 2026) — reports uncaught exceptions to
        // Sentry. No-ops safely if SENTRY_LARAVEL_DSN isn't set (local
        // dev without a Sentry account configured), because the SDK
        // itself checks for a DSN before trying to send anything — this
        // call is safe to leave in unconditionally. See config/sentry.php
        // for send_default_pii=false and why that matters here.
        if (class_exists(\Sentry\Laravel\Integration::class)) {
            \Sentry\Laravel\Integration::handles($exceptions);
        }
    })->create();

// Credential-separation fix (Sep 2026): .env was already git-ignored, but
// a *zip* of the project folder (as opposed to a git export) has no
// concept of .gitignore and would still sweep it up. If a `soms-secrets`
// folder exists as a SIBLING of the repo root (../../soms-secrets/soms
// relative to this file, i.e. outside D:\soms\ entirely — see
// scripts/setup-secrets.ps1), Laravel reads .env from there instead of
// this project's own root, so it can never be inside anything zipped
// from D:\soms\.
//
// Zero effect in production: Render never ships a .env file at all —
// every value in render.yaml's soms-shared-env group is injected as a
// real environment variable — so LoadEnvironmentVariables finds nothing
// to override either way there.
$externalEnvPath = dirname($app->basePath(), 2).'/soms-secrets/soms';
if (is_file($externalEnvPath.'/.env')) {
    $app->useEnvironmentPath($externalEnvPath);
}

return $app;
