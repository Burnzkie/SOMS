<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MustChangePasswordMiddleware
{
    /**
     * No dashboard route is accessible until the password is changed.
     * See 01-Overview-Architecture.md Decision 2.2, 03-Auth-Security.md Part A.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && !$request->routeIs('change-password.*')) {
            return redirect()->route('change-password.show');
        }

        return $next($request);
    }
}