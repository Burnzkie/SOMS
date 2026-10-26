<?php
// routes/api_officer.php

use App\Http\Controllers\Api\Officer\AttendanceController;
use App\Http\Controllers\Api\Officer\AttendanceDelegateController;
use App\Http\Controllers\Api\Officer\AuditController;
use App\Http\Controllers\Api\Officer\AnnouncementController;
use App\Http\Controllers\Api\Officer\CalendarController;
use App\Http\Controllers\Api\Officer\DashboardController;
use App\Http\Controllers\Api\Officer\EventController;
use App\Http\Controllers\Api\Officer\FineController;
use Illuminate\Support\Facades\Route;

// Active-members count only — see DashboardController docblock for why
// this doesn't mirror the web dashboard's full stats block.
Route::get('/dashboard-summary', [DashboardController::class, 'summary']);

Route::get('/events', [EventController::class, 'index']);
Route::post('/events', [EventController::class, 'store']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::patch('/events/{event}', [EventController::class, 'update']);
Route::delete('/events/{event}', [EventController::class, 'destroy']);
Route::patch('/events/{event}/reschedule', [EventController::class, 'reschedule']);

// See 08-Announcements-Calendar-Notifications.md — same feed shape as the
// web FullCalendar.js source, consumed here by Flutter's table_calendar.
Route::get('/calendar', [CalendarController::class, 'index']);

// See 05-Attendance-Fines.md Part C and 10-Mobile-Deployment.md Part C
// for the offline-first scan-batch endpoint.
Route::middleware(['throttle:scan'])->group(function () {
    Route::post('/attendance/scan', [AttendanceController::class, 'scan']);
    Route::post('/attendance/scan-batch', [AttendanceController::class, 'scanBatch']);
    Route::post('/attendance/sessions/{session}/close', [AttendanceController::class, 'closeSession']);
    Route::post('/attendance/sessions/{session}/override', [AttendanceController::class, 'manualOverride']);
    // Auditor workspace (read-only) -- each endpoint checks its own permission.
    Route::get('/audit/summary', [AuditController::class, 'summary']);
    Route::get('/audit/fines', [AuditController::class, 'fines']);
    Route::get('/audit/discrepancies', [AuditController::class, 'discrepancies']);
    Route::get('/audit/attendance', [AuditController::class, 'attendance']);
    Route::get('/audit/activity-log', [AuditController::class, 'activityLog']);
    Route::get('/audit/findings', [AuditController::class, 'findings']);
    Route::post('/audit/findings', [AuditController::class, 'storeFinding']);
    // Review offline scans flagged for clock drift (approve / reject).
    Route::get('/attendance/sessions/{session}/flagged', [AttendanceController::class, 'flagged']);
    Route::post('/attendance/{attendance}/review', [AttendanceController::class, 'reviewFlagged']);
});

// Attendance delegate assignment — mirrors routes/officer.php's web
// delegate routes. See Api\Officer\AttendanceDelegateController.
Route::get('/attendance/sessions/{session}/delegates', [AttendanceDelegateController::class, 'index']);
Route::post('/attendance/sessions/{session}/delegates', [AttendanceDelegateController::class, 'store']);
Route::delete('/attendance/sessions/{session}/delegates/{delegate}', [AttendanceDelegateController::class, 'destroy']);

Route::get('/fines', [FineController::class, 'index']);
Route::post('/fines/{fine}/clear', [FineController::class, 'clear']);
Route::post('/fines/{fine}/waive', [FineController::class, 'waive']);

// Per-student totals (grouped) + bulk settle -- see Api\Officer\FineController.
Route::get('/fines/students', [FineController::class, 'students']);
Route::post('/fines/student/{student}/clear', [FineController::class, 'clearAll']);
Route::post('/fines/student/{student}/waive', [FineController::class, 'waiveAll']);


Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::post('/announcements', [AnnouncementController::class, 'store']);
Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish']);
Route::post('/announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish']);