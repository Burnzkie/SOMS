<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/**
 * Process-alive check only -- no DB query, no auth. Required by spec
 * (09-Admin-Dashboard.md, Health Checks) as the target for uptime
 * monitors keeping the Render free-tier instance warm through cold
 * starts, deliberately kept DB-free so pinging it never touches the
 * 5-connection Clever Cloud cap. For the actual system-health detail
 * (queue staleness, log chain integrity, active Treasurer, DB note),
 * see the authenticated Admin\SystemHealthController below.
 */
Route::get('/ping', function () {
    return response()->json(['status' => 'ok']);
})->withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
]);

Route::get('/', function (){
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);

// Forgotten Password Flow (Hardened) -- see 03-Auth-Security.md Part A.
Route::get('/forgot-password', [ForgotPasswordController::class, 'showForm'])->name('forgot-password.show');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
    ->middleware('throttle:password-reset')
    ->name('forgot-password.send');
Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('reset-password.show');
Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('reset-password.update');

Route::middleware('auth')->group(function () {
    Route::get('/change-password', [AuthController::class, 'showChangePassword'])->name('change-password.show');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('change-password.update');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'must.change.password'])->group(function () {
    // Singleton resource — one avatar per user, no ID in the URL. Gives
    // proper REST verbs/route names (avatar.show/store/update/destroy)
    // for a per-user resource. creatable() must come first — it's what
    // adds store/destroy to the registration at all; only()/except() just
    // filter from whatever set creatable() produced. Available to every
    // role, self-only — enforced by UserPolicy. See AvatarController docblock.
    Route::singleton('avatar', AvatarController::class)
        ->creatable()
        ->except(['create', 'edit'])
        ->middleware('throttle:6,1');

    // Self-only personal info — every role, no target-user param. Sits
    // alongside avatar above; password change already exists separately
    // (see /change-password near top of file) and isn't duplicated here.
    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('settings.profile.edit');
    Route::put('/settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');

    Route::prefix('admin')->middleware('role:admin')->group(fn () => require base_path('routes/admin.php'));
    Route::prefix('officer')->middleware('role:officer')->group(fn () => require base_path('routes/officer.php'));
    Route::prefix('student')->middleware('role:student')->group(fn () => require base_path('routes/student.php'));
});