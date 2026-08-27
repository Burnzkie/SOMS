<?php

use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\OfficerAppointmentController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\UserController;
use Illuminate\Support\Facades\Route;

/**
 * Admin mobile API layer — see 10-Mobile-Deployment.md (Admin nav: Users,
 * Officer Appointment, Reports, Logs) and 09-Admin-Dashboard.md.
 * Mirrors routes/admin.php one-for-one; JSON responses instead of Blade.
 */
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{user}/activity-log', [UserController::class, 'activityLog']);
Route::post('/users/{user}/approve', [UserController::class, 'approve']);
Route::post('/users/{user}/reject', [UserController::class, 'reject']);

Route::get('/officers', [OfficerAppointmentController::class, 'index']);
Route::post('/officers/appoint', [OfficerAppointmentController::class, 'store']);
Route::post('/officers/{position}/revoke', [OfficerAppointmentController::class, 'revoke']);

Route::get('/reports', [ReportController::class, 'index']);
Route::get('/activity-logs', [ActivityLogController::class, 'index']);

// Same controller/method as the web /admin/system-health route -- see
// Admin\SystemHealthController docblock.
Route::get('/system-health', [SystemHealthController::class, 'index']);
