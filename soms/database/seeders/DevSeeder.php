<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\EventFineRule;
use App\Models\EventSession;
use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class DevSeeder extends Seeder
{
    public function run(): void
    {
        if (App::environment('production')) {
            $this->command->error('DevSeeder must not run in production. Aborting.');
            return;
        }

        $academicYear = '2025-2026';

        // ---------------------------------------------------------------
        // 1. Organization
        // ---------------------------------------------------------------
        $org = Organization::firstOrCreate(
            ['name' => 'Student Government Organization'],
            [
                'description'   => 'Philippine Advent College — Student Government Organization',
                'logo'          => null,
                'department'    => 'All Departments',
                'academic_year' => $academicYear,
                'is_active'     => true,
            ]
        );

       
        $admin = User::firstOrCreate(
            ['student_id' => 'A0000000000'],
            [
                'name'                 => 'Dev Admin',
                'email'                => 'admin@dev.local',
                'password'             => Hash::make('password'),
                'role'                 => 'admin',
                'department'           => 'SGO',
                'program'              => 'N/A',
                'level'                => 'N/A',
                'is_approved'          => true,
                'must_change_password' => false,
            ]
        );

        ActivityLog::record($admin->id, 'dev_seeded_admin', User::class, $admin->id, [
            'student_id' => $admin->student_id,
        ]);

        // ---------------------------------------------------------------
        // 3. Five appointed officers (one per default SGO position)
        //    Simulates an Admin appointment — officer_positions row
        //    created directly, no election/vote/winner-promotion path.
        // ---------------------------------------------------------------
        $positions = [
            'President',
            'Vice President',
            'Secretary',
            'Treasurer',
            'Auditor',
            'Public Relations Officer',
        ];

        $officers = [];

        foreach ($positions as $i => $position) {
            $studentId = sprintf('O%010d', $i + 1); // O0000000001, O0000000002, ...

            $officer = User::firstOrCreate(
                ['student_id' => $studentId],
                [
                    'name'                 => "Officer " . ($i + 1) . " ({$position})",
                    'email'                => 'officer' . ($i + 1) . '@dev.local',
                    'password'             => Hash::make('123456'),
                    'role'                 => 'officer',
                    'department'           => 'BSIT',
                    'program'              => 'BS Information Technology',
                    'level'                => '3rd Year',
                    'is_approved'          => true,
                    'must_change_password' => false,
                ]
            );

            OrganizationMember::firstOrCreate([
                'organization_id' => $org->id,
                'user_id'         => $officer->id,
            ], [
                'joined_at' => now(),
            ]);

            OfficerPosition::firstOrCreate(
                [
                    'user_id'         => $officer->id,
                    'organization_id' => $org->id,
                    'position_title'  => $position,
                    'academic_year'   => $academicYear,
                ],
                [
                    'is_active'    => true,
                    'appointed_at' => now(),
                    'appointed_by' => $admin->id,
                ]
            );

            ActivityLog::record($admin->id, 'officer_appointed', OfficerPosition::class, $officer->id, [
                'user_id'       => $officer->id,
                'position'      => $position,
                'academic_year' => $academicYear,
                'seeded'        => true,
            ]);

            $officers[] = $officer;
        }

        // ---------------------------------------------------------------
        // 4. Twenty approved students
        // ---------------------------------------------------------------
        $departments = ['BSIT', 'BSED', 'BSBA', 'BSCrim', 'BSN'];

        for ($i = 1; $i <= 20; $i++) {
            $studentId = sprintf('S%010d', $i); // S0000000001 ... S0000000020

            $student = User::firstOrCreate(
                ['student_id' => $studentId],
                [
                    'name'                 => "Student {$i}",
                    'email'                => "student{$i}@dev.local",
                    'password'             => Hash::make('123456'),
                    'role'                 => 'student',
                    'department'           => $departments[$i % count($departments)],
                    'program'              => 'Sample Program',
                    'level'                => (($i % 4) + 1) . 'st Year',
                    'is_approved'          => true,
                    'must_change_password' => false,
                    // QR is now a live rotating token (App\Services\QrTokenService),
                    // nothing to seed for it — qr_generated_at just marks activation.
                    'qr_generated_at'      => now(),
                ]
            );

            OrganizationMember::firstOrCreate([
                'organization_id' => $org->id,
                'user_id'         => $student->id,
            ], [
                'joined_at' => now(),
            ]);
        }

        // ---------------------------------------------------------------
        // 5. Two events with dual-session setup
        //    Event 1: Foundation Day — morning + afternoon + parade
        //    Event 2: General event — morning + afternoon only
        // ---------------------------------------------------------------
        $creator = $officers[0]; // President creates events

        $this->seedEvent(
            org: $org,
            creator: $creator,
            title: 'Foundation Week Day 1',
            type: 'foundation_day',
            hasParade: true,
            daysFromNow: 3
        );

        $this->seedEvent(
            org: $org,
            creator: $creator,
            title: 'General Assembly',
            type: 'other',
            hasParade: false,
            daysFromNow: 10
        );

        $this->command->info('DevSeeder complete: 1 admin, 5 officers, 20 students, 2 events seeded.');
        $this->command->info('Admin login — student_id: A0000000000 / password: password');
        $this->command->info('Officer login — student_id: O0000000001..O0000000006 / password: 123456');
        $this->command->info('Student login — student_id: S0000000001..S0000000020 / password: 123456');
    }

    /**
     * Create a single event with one event_day, its sessions
     * (morning/afternoon, +parade if requested), and fine rules.
     */
    protected function seedEvent(
        Organization $org,
        User $creator,
        string $title,
        string $type,
        bool $hasParade,
        int $daysFromNow
    ): void {
        $date = now()->addDays($daysFromNow)->toDateString();

        $event = Event::firstOrCreate(
            ['organization_id' => $org->id, 'title' => $title],
            [
                'created_by'   => $creator->id,
                'description'  => "{$title} — seeded dev event.",
                'venue'        => 'Main Campus Grounds',
                'type'         => $type,
                'date_start'   => $date,
                'date_end'     => $date,
                'has_parade'   => $hasParade,
                'is_published' => true,
            ]
        );

        $eventDay = EventDay::firstOrCreate(
            ['event_id' => $event->id, 'date' => $date],
            ['label' => 'Day 1']
        );

        // Morning session
        EventSession::firstOrCreate(
            ['event_day_id' => $eventDay->id, 'session_type' => 'morning'],
            [
                'timein_start'  => $date . ' 07:00:00',
                'timein_end'    => $date . ' 08:00:00',
                'timeout_start' => $date . ' 11:30:00',
                'timeout_end'   => $date . ' 12:00:00',
                'fines_issued'  => false,
            ]
        );

        // Afternoon session
        EventSession::firstOrCreate(
            ['event_day_id' => $eventDay->id, 'session_type' => 'afternoon'],
            [
                'timein_start'  => $date . ' 13:00:00',
                'timein_end'    => $date . ' 13:30:00',
                'timeout_start' => $date . ' 17:00:00',
                'timeout_end'   => $date . ' 17:30:00',
                'fines_issued'  => false,
            ]
        );

        // Parade session (time-in only) if applicable
        if ($hasParade) {
            EventSession::firstOrCreate(
                ['event_day_id' => $eventDay->id, 'session_type' => 'parade'],
                [
                    'timein_start'  => $date . ' 06:00:00',
                    'timein_end'    => $date . ' 06:45:00',
                    'timeout_start' => null,
                    'timeout_end'   => null,
                    'fines_issued'  => false,
                ]
            );
        }

        // Fine rules per violation type
        $fineRules = [
            'missed_morning_timein'   => 50.00,
            'missed_morning_timeout'  => 30.00,
            'missed_afternoon_timein' => 50.00,
            'missed_afternoon_timeout' => 30.00,
        ];

        if ($hasParade) {
            $fineRules['missed_parade'] = 100.00;
        }

        foreach ($fineRules as $violationType => $amount) {
            EventFineRule::firstOrCreate(
                ['event_id' => $event->id, 'violation_type' => $violationType],
                ['amount' => $amount]
            );
        }
    }
}