<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OfficerAppointmentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;


Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
Route::get('/system-health', [SystemHealthController::class, 'index'])->name('admin.system-health');

Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
Route::get('/users/{user}/activity-log', [UserController::class, 'activityLog'])->name('admin.users.activity-log');
Route::post('/users/{user}/approve', [UserController::class, 'approve'])->name('admin.users.approve');
Route::post('/users/{user}/reject', [UserController::class, 'reject'])->name('admin.users.reject');
Route::get('/officers', [OfficerAppointmentController::class, 'index'])->name('admin.officers.index');
Route::post('/officers/appoint', [OfficerAppointmentController::class, 'store'])->name('admin.officers.appoint');
Route::post('/officers/{position}/revoke', [OfficerAppointmentController::class, 'revoke'])->name('admin.officers.revoke');
Route::get('/reports', [ReportController::class, 'index'])->name('admin.reports.index');
Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');