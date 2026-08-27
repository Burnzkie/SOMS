<?php

use App\Console\Commands\IssueDueSessionFines;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Trigger 1 of the dual-triggered fine issuance flow -- see
// 05-Attendance-Fines.md Part A and Architecture Decision 2.13.
Schedule::command(IssueDueSessionFines::class)->everyFifteenMinutes();

// Daily activity-log hash-chain integrity check -- see 03-Auth-Security.md §20.7
// and 11-Testing-Maintenance.md (dead-man's-switch monitoring note).
Schedule::command('logs:verify')->daily();
