<?php

use App\Http\Controllers\Api\Officer\AttendanceController;
use App\Http\Controllers\Api\Officer\CalendarController;
use App\Http\Controllers\Api\Officer\EventController;
use App\Http\Controllers\Api\Officer\FineController;
use Illuminate\Support\Facades\Route;

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);

// See 08-Announcements-Calendar-Notifications.md — same feed shape as the
// web FullCalendar.js source, consumed here by Flutter's table_calendar.
Route::get('/calendar', [CalendarController::class, 'index']);

// See 05-Attendance-Fines.md Part C and 10-Mobile-Deployment.md Part C
// for the offline-first scan-batch endpoint.
Route::middleware(['throttle:scan'])->group(function () {
    Route::post('/attendance/scan', [AttendanceController::class, 'scan']);
    Route::post('/attendance/scan-batch', [AttendanceController::class, 'scanBatch']);
    Route::post('/attendance/sessions/{session}/close', [AttendanceController::class, 'closeSession']);
});

Route::get('/fines', [FineController::class, 'index']);
Route::post('/fines/{fine}/clear', [FineController::class, 'clear']);
Route::post('/fines/{fine}/waive', [FineController::class, 'waive']);
