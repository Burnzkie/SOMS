<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Forgotten Password Flow (Hardened) — see 03-Auth-Security.md Part A.
 * 30-min single-use token, account-enumeration-safe response (identical
 * message whether the email exists or not), Sanctum token revocation on
 * successful reset. Login is by student_id, but the reset broker keys on
 * email — the only channel a locked-out student can actually receive a
 * link through.
 */
class ForgotPasswordController extends Controller
{
    public function showForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        ActivityLog::record(null, 'password_reset_requested', null, null, ['ip' => $request->ip()], $request->ip());

        // Same message regardless of whether the email exists — prevents
        // account enumeration. Do not branch on Password::sendResetLink's
        // return status here.
        return back()->with('status', 'If that email exists, a reset link has been sent.');
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
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

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Password reset. Please log in.')
            : back()->withErrors(['email' => 'Reset failed or link expired.']);
    }
}
