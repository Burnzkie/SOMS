<?php

use App\Http\Controllers\Api\Officer\AttendanceController;
use App\Http\Controllers\Api\Officer\AttendanceDelegateController;
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
});

// Attendance delegate assignment — mirrors routes/officer.php's web
// delegate routes. See Api\Officer\AttendanceDelegateController.
Route::get('/attendance/sessions/{session}/delegates', [AttendanceDelegateController::class, 'index']);
Route::post('/attendance/sessions/{session}/delegates', [AttendanceDelegateController::class, 'store']);
Route::delete('/attendance/sessions/{session}/delegates/{delegate}', [AttendanceDelegateController::class, 'destroy']);

Route::get('/fines', [FineController::class, 'index']);
Route::post('/fines/{fine}/clear', [FineController::class, 'clear']);
Route::post('/fines/{fine}/waive', [FineController::class, 'waive']);


Route::get('/announcements', [AnnouncementController::class, 'index']);
Route::post('/announcements', [AnnouncementController::class, 'store']);
Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish']);
Route::post('/announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish']);