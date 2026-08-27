<?php

use App\Http\Controllers\Student\AnnouncementController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\EventController;
use App\Http\Controllers\Student\FineController;
use App\Http\Controllers\Student\QrController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('student.dashboard');

Route::get('/qr', [QrController::class, 'show'])->name('student.qr.show');
Route::get('/qr/current', [QrController::class, 'current'])->name('student.qr.current');

Route::get('/events', [EventController::class, 'index'])->name('student.events.index');
Route::get('/events/{event}', [EventController::class, 'show'])->name('student.events.show');

Route::get('/fines', [FineController::class, 'index'])->name('student.fines.index');

Route::get('/announcements', [AnnouncementController::class, 'index'])->name('student.announcements.index');
Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('student.announcements.show');
