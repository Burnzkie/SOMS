<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
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
        //
    })->create();
