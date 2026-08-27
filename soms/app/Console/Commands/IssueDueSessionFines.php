<?php

namespace App\Console\Commands;

use App\Jobs\IssueSessionFinesJob;
use App\Models\EventSession;
use Illuminate\Console\Command;

/**
 * Trigger 1 of the dual-triggered fine issuance flow — see
 * 05-Attendance-Fines.md Part A. Scheduled every 15 minutes
 * (routes/console.php). Finds sessions where the time-in window (and,
 * for morning/afternoon, the time-out window) has already closed but
 * fines_issued is still false, and dispatches the queued job for each.
 */
class IssueDueSessionFines extends Command
{
    protected $signature = 'attendance:issue-due-fines';

    protected $description = 'Dispatch IssueSessionFinesJob for event sessions whose scan window has closed';

    public function handle(): int
    {
        $now = now();

        $due = EventSession::where('fines_issued', false)
            ->where('timein_end', '<=', $now)
            ->get();

        foreach ($due as $session) {
            IssueSessionFinesJob::dispatch($session->id);
        }

        $this->info("Dispatched fine issuance for {$due->count()} due session(s).");

        return self::SUCCESS;
    }
}
