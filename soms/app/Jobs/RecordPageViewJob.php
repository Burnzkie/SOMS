<?php
// app/Jobs/RecordPageViewJob.php

namespace App\Jobs;

use App\Models\AnalyticsDailyHit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued rather than written synchronously in TrackPageView middleware for
 * two reasons: it keeps analytics off the request/response path entirely
 * (zero added latency), and it avoids an extra DB write on every single
 * request contending with Clever Cloud's 5-connection cap in the same
 * breath as the request that's already using one. Runs through the same
 * `QUEUE_CONNECTION=database` worker already deployed for
 * IssueSessionFinesJob (see render.yaml's soms-queue-worker service) --
 * no new infrastructure.
 */
class RecordPageViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $date,      // Y-m-d, computed at dispatch time (request time), not job-run time
        public readonly string $routeName,
        public readonly string $platform,  // 'web' | 'mobile'
    ) {}

    public function handle(): void
    {
        // firstOrCreate + increment rather than a raw upsert-with-increment
        // statement: MySQL's "ON DUPLICATE KEY UPDATE hits = hits + 1" and
        // SQLite's "ON CONFLICT DO UPDATE" are different enough syntax that
        // a raw query would need a driver branch to stay testable against
        // the SQLite test DB (see the events-enum migration fix earlier in
        // this project for exactly that problem) -- two portable queries
        // beat one driver-specific one here, and this runs off the request
        // path anyway so the extra query costs nothing user-facing.
        $row = AnalyticsDailyHit::firstOrCreate(
            [
                'date' => $this->date,
                'route_name' => $this->routeName,
                'platform' => $this->platform,
            ],
            ['hits' => 0]
        );

        $row->increment('hits');
    }
}
