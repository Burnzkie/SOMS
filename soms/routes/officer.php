<?php

use App\Http\Controllers\Officer\AnnouncementController;
use App\Http\Controllers\Officer\AttendanceController;
use App\Http\Controllers\Officer\AttendanceDelegateController;
use App\Http\Controllers\Officer\CalendarController;
use App\Http\Controllers\Officer\DashboardController;
use App\Http\Controllers\Officer\EventController;
use App\Http\Controllers\Officer\FineController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('officer.dashboard');

// Officer calendar -- see 08-Announcements-Calendar-Notifications.md.
Route::get('/calendar', [CalendarController::class, 'index'])->name('officer.calendar.index');
Route::post('/calendar', [CalendarController::class, 'store'])->name('officer.calendar.store');
Route::put('/calendar/{entry}', [CalendarController::class, 'update'])->name('officer.calendar.update');
Route::delete('/calendar/{entry}', [CalendarController::class, 'destroy'])->name('officer.calendar.destroy');

// Events & attendance -- see 05-Attendance-Fines.md Part A.
Route::get('/events', [EventController::class, 'index'])->name('officer.events.index');
Route::get('/events/create', [EventController::class, 'create'])->name('officer.events.create');
Route::post('/events', [EventController::class, 'store'])->name('officer.events.store');
Route::get('/events/{event}', [EventController::class, 'show'])->name('officer.events.show');
Route::post('/events/{event}/publish', [EventController::class, 'publish'])->name('officer.events.publish');
Route::post('/events/{event}/fine-rules', [EventController::class, 'updateFineRules'])->name('officer.events.fine-rules');
Route::post('/sessions/{session}', [EventController::class, 'updateSession'])->name('officer.sessions.update');

// Attendance delegate assignment -- scoped per session, no caching of grants.
Route::post('/sessions/{session}/delegates', [AttendanceDelegateController::class, 'store'])->name('officer.delegates.store');
Route::delete('/sessions/{session}/delegates/{delegate}', [AttendanceDelegateController::class, 'destroy'])->name('officer.delegates.destroy');

// Scan routes additionally wrapped with throttle:scan per 10-Mobile-Deployment.md.
Route::middleware(['throttle:scan'])->group(function () {
    Route::get('/attendance/sessions/{session}/scan', [AttendanceController::class, 'station'])->name('officer.attendance.station');
    Route::post('/attendance/scan', [AttendanceController::class, 'scan'])->name('officer.attendance.scan');
    Route::post('/attendance/sessions/{session}/close', [AttendanceController::class, 'closeSession'])->name('officer.attendance.close');
    Route::post('/attendance/sessions/{session}/override', [AttendanceController::class, 'manualOverride'])->name('officer.attendance.override');
});

// Fines -- Treasurer + Admin only, gated by FinePolicy inside the controller.
Route::get('/fines', [FineController::class, 'index'])->name('officer.fines.index');
Route::post('/fines/{fine}/clear', [FineController::class, 'clear'])->name('officer.fines.clear');
Route::post('/fines/{fine}/waive', [FineController::class, 'waive'])->name('officer.fines.waive');

// Announcements -- draft (all tiers) / publish (Executive only).
Route::get('/announcements', [AnnouncementController::class, 'index'])->name('officer.announcements.index');
Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('officer.announcements.create');
Route::post('/announcements', [AnnouncementController::class, 'store'])->name('officer.announcements.store');
Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->name('officer.announcements.publish');
Route::post('/announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish'])->name('officer.announcements.unpublish');
