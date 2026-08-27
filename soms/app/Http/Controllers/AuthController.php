<?php
namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user());
        }

        return view('auth.login');
    }

    /**
     * Handle a login attempt.
     * Student ID + password (not email) — see 03-Auth-Security.md Part A.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('student_id', 'password');

        $user = User::where('student_id', $credentials['student_id'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            ActivityLog::record(
                $user?->id,
                'login_failed',
                null,
                null,
                ['student_id' => $credentials['student_id']],
                $request->ip()
            );

            return back()
                ->withErrors(['student_id' => 'Invalid Student ID or password.'])
                ->onlyInput('student_id');
        }

        if (! $user->is_approved) {
            return back()
                ->withErrors(['student_id' => 'Your account is still pending Admin approval.'])
                ->onlyInput('student_id');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        ActivityLog::record($user->id, 'login_success', null, null, ['platform' => 'web'], $request->ip());

        if ($user->must_change_password) {
            return redirect()->route('change-password.show');
        }

        return $this->redirectForRole($user);
    }

    /**
     * Show the registration form.
     */
    public function showRegister()
    {
        if (Auth::check()) {
            return $this->redirectForRole(Auth::user());
        }

        return view('auth.register');
    }

    /**
     * Handle self-registration.
     * Default password 123456, forced change, pending Admin approval — see 01-Overview-Architecture.md §2.2.
     */
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name'                 => $request->name,
            'student_id'           => $request->student_id,
            'email'                => $request->email,
            'department'           => $request->department,
            'program'              => $request->program,
            'level'                => $request->level,
            'password'             => Hash::make('123456'),
            'role'                 => 'student',
            'is_approved'          => false,
            'must_change_password' => true,
        ]);

        ActivityLog::record($user->id, 'student_registered', User::class, $user->id, [
            'student_id' => $user->student_id,
        ], $request->ip());

        return redirect()->route('login')
            ->with('status', 'Registration submitted. Your account is pending Admin approval.');
    }

    /**
     * Show the change-password form.
     * Reachable both for the forced first-login flow and voluntary changes later
     * (route sits outside the must.change.password middleware group — see 10-Mobile-Deployment.md).
     */
    public function showChangePassword()
    {
        return view('auth.change-password');
    }

    /**
     * Handle a password change.
     * Validates current password, enforces policy, revokes all Sanctum tokens,
     * logs the user out and redirects to login — see 03-Auth-Security.md Part A.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = Auth::user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            ActivityLog::record($user->id, 'change_password_failed', User::class, $user->id, [], $request->ip());

            return back()
                ->withErrors(['current_password' => 'Current password is incorrect.'])
                ->onlyInput();
        }

        $user->forceFill([
            'password'             => Hash::make($request->input('password')),
            'must_change_password' => false,
        ])->save();

        // Revoke all Sanctum tokens (mobile sessions) on password change — 03-Auth-Security.md Part A.
        $user->tokens()->delete();

        ActivityLog::record($user->id, 'password_changed', User::class, $user->id, [], $request->ip());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Password changed successfully. Please log in with your new password.');
    }

    /**
     * Log the user out.
     */
    public function logout()
    {
        $userId = Auth::id();

        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        ActivityLog::record($userId, 'logout');

        return redirect()->route('login')->with('status', 'You have been logged out.');
    }

    /**
     * Route the authenticated user to their role-based landing page.
     */
    protected function redirectForRole(User $user)
    {
        return match ($user->role) {
            'admin'   => redirect()->route('admin.dashboard'),
            'officer' => redirect()->route('officer.dashboard'),
            default   => redirect()->route('student.dashboard'),
        };
    }
}