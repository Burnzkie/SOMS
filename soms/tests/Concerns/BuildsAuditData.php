<?php
// tests/Concerns/BuildsAuditData.php

namespace Tests\Concerns;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventDay;
use App\Models\EventFineRule;
use App\Models\EventSession;
use App\Models\Fine;
use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\User;

/**
 * Builds the small world the audit tests need: one organization, one
 * published event with a Morning session whose windows have already closed
 * (so fines are due), fine rules, officers with chosen permissions, and
 * students registered before the session closed.
 */
trait BuildsAuditData
{
    protected Organization $org;
    protected User $admin;
    protected Event $event;
    protected EventSession $session;

    protected function setUpAuditData(): void
    {
        $this->org = Organization::create([
            'name' => 'Student Government Organization',
            'department' => 'All Departments',
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $this->admin = User::factory()->admin()->create();

        $this->event = Event::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'title' => 'Audit Test Event',
            'type' => 'other',
            'date_start' => now()->toDateString(),
            'date_end' => now()->toDateString(),
            'is_published' => true,
        ]);
        $day = EventDay::create(['event_id' => $this->event->id, 'date' => now()->toDateString()]);

        // Both windows closed in the past, so one job run assesses time-in AND time-out.
        $this->session = EventSession::create([
            'event_day_id' => $day->id,
            'session_type' => 'morning',
            'timein_start' => now()->subHours(2),
            'timein_end' => now()->subHour(),
            'timeout_start' => now()->subMinutes(50),
            'timeout_end' => now()->subMinutes(10),
        ]);

        EventFineRule::create(['event_id' => $this->event->id, 'violation_type' => 'missed_morning_timein', 'amount' => 50]);
        EventFineRule::create(['event_id' => $this->event->id, 'violation_type' => 'missed_morning_timeout', 'amount' => 30]);
    }

    /** An approved student who registered well before the session closed. */
    protected function makeStudent(string $name = 'Test Student', array $overrides = []): User
    {
        return User::factory()->create($overrides + [
            'name' => $name,
            'is_approved' => true,
            'created_at' => now()->subDays(2),
        ]);
    }

    /** An officer holding exactly the given permissions under the given title. */
    protected function makeOfficerWith(string $title, array $permissions): User
    {
        $officer = User::factory()->officer()->create(['is_approved' => true]);

        OfficerPosition::create([
            'user_id' => $officer->id,
            'organization_id' => $this->org->id,
            'position_title' => $title,
            'permissions' => $permissions,
            'academic_year' => '2026-2027',
            'is_active' => true,
            'appointed_at' => now(),
        ]);

        return $officer;
    }

    /** The Auditor: every read-only audit permission plus findings. */
    protected function makeAuditor(): User
    {
        return $this->makeOfficerWith('Auditor', [
            'view_reports', 'view_fines', 'view_attendance', 'view_audit_logs', 'manage_audit_findings',
        ]);
    }

    protected function makeTreasurer(): User
    {
        return $this->makeOfficerWith('Treasurer', ['view_dashboard']);
    }

    /** A fine on the test session, unpaid unless overridden. */
    protected function makeFine(User $student, array $overrides = []): Fine
    {
        return Fine::create($overrides + [
            'user_id' => $student->id,
            'event_id' => $this->event->id,
            'event_day_id' => $this->session->event_day_id,
            'event_session_id' => $this->session->id,
            'violation_type' => 'missed_morning_timein',
            'reason' => 'Missed morning timein',
            'amount' => 50,
            'issued_at' => now()->subHour(),
            'status' => 'unpaid',
        ]);
    }

    protected function recordScan(User $student, string $scanType = 'time_in', string $status = 'present', array $overrides = []): EventAttendance
    {
        return EventAttendance::create($overrides + [
            'event_id' => $this->event->id,
            'event_day_id' => $this->session->event_day_id,
            'event_session_id' => $this->session->id,
            'user_id' => $student->id,
            'scan_type' => $scanType,
            'scanned_at' => now()->subHours(2),
            'status' => $status,
        ]);
    }
}
