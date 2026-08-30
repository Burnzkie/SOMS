<?php

use App\Http\Controllers\Api\Auth\ChangePasswordController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProfileController;


/**
 * See 10-Mobile-Deployment.md Part B for the response format convention:
 * { success, data, message } / { success:false, message, errors }.
 */
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    // Public — feeds the mobile registration form's department/program
    // dropdowns from the same config/academic_programs.php the web
    // registration blade and RegisterRequest's validation already use, so
    // there's one source of truth instead of a second hardcoded copy.
    Route::get('/academic-programs', fn () => response()->json([
        'success' => true,
        'data' => config('academic_programs', []),
    ]));

    Route::post('/auth/login', [LoginController::class, 'login']);
    Route::post('/auth/register', [RegisterController::class, 'register']);
    Route::post('/auth/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
        ->middleware('throttle:password-reset');
    Route::post('/auth/reset-password', [ForgotPasswordController::class, 'reset']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [LoginController::class, 'logout']);

        // Deliberately outside must.change.password.api — this is the one
        // endpoint a student with must_change_password=true must still be
        // able to reach. Mirrors routes/web.php, where /change-password
        // sits outside the equivalent web group.
        Route::post('/auth/change-password', [ChangePasswordController::class, 'update']);

        Route::middleware('must.change.password.api')->group(function () {
            // Singleton resource — one avatar per user, no ID in the URL.
            // apiSingleton() excludes the web-only create/edit form routes
            // by default; ->creatable() adds store/destroy on top of the
            // default show/update. Available to every role — self-only,
            // enforced by UserPolicy.
            //
            // Named routes are explicitly prefixed "api." here -- without
            // this, apiSingleton()'s default names (avatar.store,
            // avatar.update, ...) collide with routes/web.php's identically-
            // named singleton. Since this file loads after web.php in
            // bootstrap/app.php's withRouting(), the API names silently won
            // and route('avatar.store') in Blade resolved to this
            // auth:sanctum-gated /api/v1/avatar instead of the session-gated
            // /avatar -- every web-UI avatar upload 401'd and bounced to
            // /login with zero visible error. Flutter isn't affected by this
            // rename since it calls the literal URL string, never Laravel's
            // route() helper.
            Route::get('/profile', [ProfileController::class, 'show']);
            Route::put('/profile', [ProfileController::class, 'update']);
            Route::name('api.')->group(function () {
                Route::apiSingleton('avatar', AvatarController::class)
                    ->creatable()
                    ->middleware('throttle:6,1');
            });

            Route::prefix('student')->middleware('role:student')->group(fn () => require base_path('routes/api_student.php'));
            Route::prefix('officer')->middleware('role:officer')->group(fn () => require base_path('routes/api_officer.php'));
            Route::prefix('admin')->middleware('role:admin')->group(fn () => require base_path('routes/api_admin.php'));

            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        });
    });
});