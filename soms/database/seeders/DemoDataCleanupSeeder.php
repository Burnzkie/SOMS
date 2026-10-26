<?php
// database/seeders/DemoDataCleanupSeeder.php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AuditFinding;
use App\Models\AttendanceDelegate;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * Removes what DemoDataSeeder added: every "[DEMO] ..." event (and with it
 * its days, sessions, attendance and fines), the demo announcements, the
 * demo notifications and the three extra demo accounts.
 *
 * Does NOT remove DevSeeder's base accounts (admin, officers, S...001-020),
 * and leaves the activity log alone — it is a tamper-evident hash chain.
 */
class DemoDataCleanupSeeder extends Seeder
{
    public function run(): void
    {
        if (App::environment('production')) {
            $this->command->error('DemoDataCleanupSeeder must not run in production. Aborting.');
            return;
        }

        DB::transaction(function () {
            $prefix = DemoDataSeeder::DEMO_PREFIX . '%';

            $eventIds = Event::where('title', 'like', $prefix)->pluck('id');
            $sessionIds = EventSession::whereIn('event_day_id', function ($query) use ($eventIds) {
                $query->select('id')->from('event_days')->whereIn('event_id', $eventIds);
            })->pluck('id');

            // Explicit, so this doesn't depend on every foreign key cascading.
            AttendanceDelegate::whereIn('event_session_id', $sessionIds)->delete();

            $events = Event::whereIn('id', $eventIds)->delete();
            $announcements = Announcement::where('title', 'like', $prefix)->delete();
            AuditFinding::where('title', 'like', $prefix)->delete();
            $notifications = Notification::where('data->demo', true)->delete();
            // User soft-deletes; a soft-deleted demo account would still hold its
            // unique email/student_id and make the next DemoDataSeeder run fail.
            $users = User::withTrashed()
                ->whereIn('student_id', ['S0000000101', 'S0000000102', 'S0000000103'])
                ->forceDelete();

            $this->command->info("Removed demo data: {$events} events, {$announcements} announcements, {$notifications} notifications, {$users} accounts.");
        });
    }
}
