<?php
// routes/officer.php

use App\Http\Controllers\Officer\AnnouncementController;
use App\Http\Controllers\Officer\AuditController;
use App\Http\Controllers\Officer\AuditFindingController;
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

// Events & attendance -- see 05-Attendance-Fines.md Part A.
Route::get('/events', [EventController::class, 'index'])->name('officer.events.index');
Route::get('/events/create', [EventController::class, 'create'])->name('officer.events.create');
Route::post('/events', [EventController::class, 'store'])->name('officer.events.store');
Route::get('/events/{event}', [EventController::class, 'show'])->name('officer.events.show');
Route::patch('/events/{event}', [EventController::class, 'update'])->name('officer.events.update');
Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('officer.events.destroy');
Route::post('/events/{event}/publish', [EventController::class, 'publish'])->name('officer.events.publish');
Route::patch('/events/{event}/reschedule', [EventController::class, 'reschedule'])->name('officer.events.reschedule');
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
    Route::post('/attendance/{attendance}/review', [AttendanceController::class, 'reviewFlagged'])->name('officer.attendance.review');
});

// Fines -- Treasurer + Admin only, gated by FinePolicy inside the controller.
Route::get('/fines', [FineController::class, 'index'])->name('officer.fines.index');
Route::post('/fines/{fine}/clear', [FineController::class, 'clear'])->name('officer.fines.clear');
Route::post('/fines/{fine}/waive', [FineController::class, 'waive'])->name('officer.fines.waive');
// Bulk: settle ALL of one student's unpaid fines at once (treasurer convenience).
Route::post('/fines/student/{student}/clear', [FineController::class, 'clearAll'])->name('officer.fines.clearAll');
Route::post('/fines/student/{student}/waive', [FineController::class, 'waiveAll'])->name('officer.fines.waiveAll');

// Auditor workspace -- read-only records, discrepancy report, exports and findings.
// Each page checks its own permission inside the controller (no implicit access).
Route::prefix('audit')->group(function () {
    Route::get('/', [AuditController::class, 'dashboard'])->name('officer.audit.dashboard');
    Route::get('/fines', [AuditController::class, 'fines'])->name('officer.audit.fines');
    Route::get('/attendance', [AuditController::class, 'attendance'])->name('officer.audit.attendance');
    Route::get('/discrepancies', [AuditController::class, 'discrepancies'])->name('officer.audit.discrepancies');
    Route::get('/activity-log', [AuditController::class, 'activityLog'])->name('officer.audit.activity-log');
    Route::get('/export/fines', [AuditController::class, 'exportFines'])->name('officer.audit.export.fines');
    Route::get('/export/discrepancies', [AuditController::class, 'exportDiscrepancies'])->name('officer.audit.export.discrepancies');

    Route::get('/findings', [AuditFindingController::class, 'index'])->name('officer.audit.findings.index');
    Route::get('/findings/create', [AuditFindingController::class, 'create'])->name('officer.audit.findings.create');
    Route::post('/findings', [AuditFindingController::class, 'store'])->name('officer.audit.findings.store');
    Route::post('/findings/from-check', [AuditFindingController::class, 'fromCheck'])->name('officer.audit.findings.from-check');
    Route::get('/findings/{finding}', [AuditFindingController::class, 'show'])->name('officer.audit.findings.show');
    Route::patch('/findings/{finding}', [AuditFindingController::class, 'update'])->name('officer.audit.findings.update');
    Route::post('/findings/{finding}/withdraw', [AuditFindingController::class, 'withdraw'])->name('officer.audit.findings.withdraw');
    Route::post('/findings/{finding}/reopen', [AuditFindingController::class, 'reopen'])->name('officer.audit.findings.reopen');
});

// Announcements -- draft (all tiers) / publish (Executive only).
Route::get('/announcements', [AnnouncementController::class, 'index'])->name('officer.announcements.index');
Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('officer.announcements.create');
Route::post('/announcements', [AnnouncementController::class, 'store'])->name('officer.announcements.store');
Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->name('officer.announcements.publish');
Route::post('/announcements/{announcement}/unpublish', [AnnouncementController::class, 'unpublish'])->name('officer.announcements.unpublish');