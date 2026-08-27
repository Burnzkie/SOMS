<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MustChangePasswordApiMiddleware
{
    /**
     * API counterpart to MustChangePasswordMiddleware — returns a JSON 403
     * instead of a redirect, since there's no "show change-password form"
     * concept over the API. The Flutter client reads this and routes to
     * its own ChangePasswordScreen. See 10-Mobile-Deployment.md.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            return response()->json([
                'success' => false,
                'message' => 'Password change required before continuing.',
                'must_change_password' => true,
            ], 403);
        }

        return $next($request);
    }
}
