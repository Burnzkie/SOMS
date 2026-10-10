<?php
// config/sentry.php
//
// Requires: composer require sentry/sentry-laravel
// (see CHANGES.md for the full signup + install walkthrough)

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    // Empty by default = every release reports as "unspecified" in
    // Sentry. Set to your composer.json/pubspec.yaml version, or a git
    // commit hash, if you want errors grouped by release later.
    'release' => env('SENTRY_RELEASE'),

    'environment' => env('APP_ENV', 'production'),

    // Performance tracing sample rate: 0.2 = trace 20% of requests. Kept
    // deliberately low -- this is a free-tier Sentry project and a
    // free-tier Render/Clever Cloud stack; no need to trace every single
    // request when the goal here is catching errors, not deep APM.
    // Raise this temporarily while debugging a specific performance
    // issue, then lower it back.
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.2),

    // CRITICAL for this project: SOMS handles student records under RA
    // 10173 (Data Privacy Act of 2012). Sentry's default behavior
    // (send_default_pii=true) attaches the request URL with full query
    // string, IP address, cookies, and authenticated user info
    // (id/email/username if you've called Sentry's setUser -- this app
    // doesn't) to every error report. Keeping this false means Sentry
    // only ever receives the exception, stack trace, and route name --
    // never request bodies, query strings, cookies, or user identity.
    // Do not flip this to true without re-reading Sentry's PII docs
    // first: https://docs.sentry.io/platforms/php/data-management/sensitive-data/
    'send_default_pii' => false,

    // Belt-and-suspenders alongside send_default_pii=false above: even
    // with PII off, strip cookies/headers if they ever end up in a
    // breadcrumb or exception context by some other path. A static
    // method reference, NOT an inline closure -- start.sh runs
    // `php artisan config:cache` on every deploy, and a Closure here
    // would break that entirely ("configuration files are not
    // serializable"). See app/Sentry/Callbacks.php.
    'before_send' => [\App\Sentry\Callbacks::class, 'beforeSend'],

    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => false, // query bindings can contain student_id / names -- keep off
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
    ],
];
