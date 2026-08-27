<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

/**
 * Daily tamper-evidence check for the hash-chained activity_logs table.
 * See 03-Auth-Security.md §20.7 and 11-Testing-Maintenance.md (a missed
 * run should itself raise an operational alert -- dead-man's-switch
 * monitoring; wiring an actual alert channel is a deployment-time task,
 * this command's non-zero exit code is what a monitor should watch for).
 */
class VerifyActivityLogChain extends Command
{
    protected $signature = 'logs:verify';

    protected $description = 'Verify the tamper-evident hash chain across all activity_logs entries';

    public function handle(): int
    {
        $ok = ActivityLog::verifyChainIntegrity();

        if ($ok) {
            $this->info('Activity log hash chain OK.');
            return self::SUCCESS;
        }

        $this->error('Activity log hash chain integrity check FAILED — possible tampering.');
        return self::FAILURE;
    }
}
