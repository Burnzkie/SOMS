<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Mobile counterpart to Http\Controllers\ForgotPasswordController — same
 * broker, same enumeration-safe response, same Sanctum token revocation
 * on reset. See 03-Auth-Security.md Part A and 10-Mobile-Deployment.md
 * (API response format: { success, data, message } / { success:false, message, errors }).
 */
class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        ActivityLog::record(null, 'password_reset_requested', null, null, ['ip' => $request->ip()], $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'If that email exists, a reset link has been sent.',
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
                ActivityLog::record($user->id, 'password_reset_completed', null, null, [], request()->ip());
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'Reset failed or link expired.',
                'errors'  => ['email' => ['Reset failed or link expired.']],
            ], 422);
        }

        return response()->json(['success' => true, 'message' => 'Password reset. Please log in.']);
    }
}
