<?php 

use App\Http\Controllers\Api\StudentApiController;
use Illuminate\Support\Facades\Route;


Route::get('/dashboard', [StudentApiController::class, 'dashboard']);
Route::get('/qr', [StudentApiController::class, 'qrCurrent']);
Route::get('/events', [StudentApiController::class, 'events']);
Route::get('/events/{event}', [StudentApiController::class, 'eventShow']);
Route::get('/fines', [StudentApiController::class, 'fines']);
Route::get('/announcements',[StudentApiController::class, 'announcements']);
Route::get('/announcements/{announcement}', [StudentApiController::class, 'announcementShow']);