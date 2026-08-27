<?php

namespace Tests\Feature;

use App\Jobs\IssueSessionFinesJob;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventDay;
use App\Models\EventFineRule;
use App\Models\EventSession;
use App\Models\Fine;
use App\Models\Notification;
use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FineIssuanceAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Student Government Organization',
            'department' => 'All Departments',
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->admin()->create();
    }

    protected function makeMorningSession(string $eventType = 'other'): EventSession
    {
        $event = Event::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'title' => 'Test Event',
            'type' => $eventType,
            'date_start' => now()->toDateString(),
            'date_end' => now()->toDateString(),
            'is_published' => true,
        ]);

        $day = EventDay::create([
            'event_id' => $event->id,
            'date' => now()->toDateString(),
        ]);

        return EventSession::create([
            'event_day_id' => $day->id,
            'session_type' => 'morning',
            'timein_start' => now()->subHour(),
            'timein_end' => now()->subMinutes(5), // window already closed
            'timeout_start' => null,
            'timeout_end' => null,
        ]);
    }

    protected function makeParadeSession(): EventSession
    {
        $event = Event::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'title' => 'Foundation Day',
            'type' => 'foundation_day',
            'has_parade' => true,
            'date_start' => now()->toDateString(),
            'date_end' => now()->toDateString(),
            'is_published' => true,
        ]);

        $day = EventDay::create([
            'event_id' => $event->id,
            'date' => now()->toDateString(),
        ]);

        return EventSession::create([
            'event_day_id' => $day->id,
            'session_type' => 'parade',
            'timein_start' => now()->subHour(),
            'timein_end' => now()->subMinutes(5),
        ]);
    }

    protected function enrollMember(EventSession $session): User
    {
        $member = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $this->org->id,
            'user_id' => $member->id,
            'joined_at' => now(),
        ]);
        return $member;
    }

    // ---------------------------------------------------------------
    // Fine issuance
    // ---------------------------------------------------------------

    public function test_member_who_never_scanned_time_in_gets_fined_once_the_window_closes(): void
    {
        $session = $this->makeMorningSession();
        $event = $session->eventDay->event;
        $member = $this->enrollMember($session);

        EventFineRule::create([
            'event_id' => $event->id,
            'violation_type' => 'missed_morning_timein',
            'amount' => 50,
        ]);

        IssueSessionFinesJob::dispatchSync($session->id);

        $this->assertDatabaseHas('fines', [
            'user_id' => $member->id,
            'event_session_id' => $session->id,
            'violation_type' => 'missed_morning_timein',
            'status' => 'unpaid',
            'amount' => 50,
        ]);
    }

    public function test_member_who_scanned_present_does_not_get_fined(): void
    {
        $session = $this->makeMorningSession();
        $event = $session->eventDay->event;
        $member = $this->enrollMember($session);

        EventFineRule::create([
            'event_id' => $event->id,
            'violation_type' => 'missed_morning_timein',
            'amount' => 50,
        ]);

        EventAttendance::create([
            'event_id' => $event->id,
            'event_day_id' => $session->event_day_id,
            'event_session_id' => $session->id,
            'user_id' => $member->id,
            'scan_type' => 'time_in',
            'scanned_at' => now(),
            'status' => 'present',
        ]);

        IssueSessionFinesJob::dispatchSync($session->id);

        $this->assertDatabaseMissing('fines', [
            'user_id' => $member->id,
            'event_session_id' => $session->id,
        ]);
    }

    /**
     * The job is dispatched from two places (15-min scheduler + officer's
     * manual "Close Session" button) and relies on firstOrCreate for
     * idempotency. This confirms running it twice for the same session
     * neither duplicates the fine nor sends a second notification.
     */
    public function test_fine_issuance_is_idempotent_across_repeated_dispatch(): void
    {
        $session = $this->makeMorningSession();
        $event = $session->eventDay->event;
        $member = $this->enrollMember($session);

        EventFineRule::create([
            'event_id' => $event->id,
            'violation_type' => 'missed_morning_timein',
            'amount' => 50,
        ]);

        IssueSessionFinesJob::dispatchSync($session->id, true);
        IssueSessionFinesJob::dispatchSync($session->id, true);

        $this->assertSame(1, Fine::where('user_id', $member->id)
            ->where('event_session_id', $session->id)
            ->count());

        $this->assertSame(1, Notification::where('user_id', $member->id)
            ->where('type', 'fine_issued')
            ->count());
    }

    /**
     * NEW FINDING (not one of the previously-fixed bugs): for a parade
     * session, the job builds the violation type as
     * 'missed_' . session_type . '_timein' -> 'missed_parade_timein'.
     * But the enum on both event_fine_rules.violation_type and
     * fines.violation_type only defines 'missed_parade' (no _timein
     * suffix). The lookup in issueForScanType() never matches, so
     * EventFineRule::where(...)->first() returns null and the method
     * returns early -- parade absences are silently never fined.
     *
     * This test asserts the *intended* behavior (spec: a missed parade
     * still produces a 'missed_parade' fine) and will fail against the
     * current job code, which is the point: it documents a real defect
     * distinct from anything already fixed this session.
     */
    public function test_member_who_misses_the_parade_gets_fined(): void
    {
        $session = $this->makeParadeSession();
        $event = $session->eventDay->event;
        $member = $this->enrollMember($session);

        EventFineRule::create([
            'event_id' => $event->id,
            'violation_type' => 'missed_parade',
            'amount' => 100,
        ]);

        IssueSessionFinesJob::dispatchSync($session->id);

        $this->assertDatabaseHas('fines', [
            'user_id' => $member->id,
            'event_session_id' => $session->id,
            'violation_type' => 'missed_parade',
        ]);
    }

    // ---------------------------------------------------------------
    // Attendance scanning
    // ---------------------------------------------------------------

    protected function makeScanningOfficer(): User
    {
        $officer = User::factory()->officer()->create();
        OfficerPosition::create([
            'user_id' => $officer->id,
            'organization_id' => $this->org->id,
            'position_title' => 'Secretary', // Administrative tier -> manage_attendance
            'academic_year' => '2026-2027',
            'is_active' => true,
            'appointed_at' => now(),
        ]);
        return $officer->fresh();
    }

    protected function makeOpenSession(): EventSession
    {
        $event = Event::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'title' => 'Live Session',
            'type' => 'other',
            'date_start' => now()->toDateString(),
            'date_end' => now()->toDateString(),
            'is_published' => true,
        ]);
        $day = EventDay::create(['event_id' => $event->id, 'date' => now()->toDateString()]);

        return EventSession::create([
            'event_day_id' => $day->id,
            'session_type' => 'morning',
            'timein_start' => now()->subMinutes(5),
            'timein_end' => now()->addMinutes(30), // window open
        ]);
    }

    public function test_officer_with_manage_attendance_permission_can_scan_a_valid_qr(): void
    {
        $officer = $this->makeScanningOfficer();
        $session = $this->makeOpenSession();
        $student = User::factory()->create(['is_approved' => true]);

        $token = QrTokenService::current($student)['token'];

        $response = $this->actingAs($officer)->postJson('/officer/attendance/scan', [
            'session_id' => $session->id,
            'token' => $student->id . ':' . $token,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'present']);

        $this->assertDatabaseHas('event_attendance', [
            'event_session_id' => $session->id,
            'user_id' => $student->id,
            'scan_type' => 'time_in',
            'status' => 'present',
        ]);
    }

    /**
     * Authorization boundary for the scan endpoint -- a plain student
     * (no officer position at all) must not be able to mark attendance,
     * which is exactly the kind of check the tier() bug could have
     * silently broken if manage_attendance were ever granted to the
     * PublicRelations fallback tier (it isn't, but this pins the
     * behavior so a future permissions change can't regress it).
     */
    public function test_a_non_officer_cannot_scan_attendance(): void
    {
        $student = User::factory()->create();
        $session = $this->makeOpenSession();
        $target = User::factory()->create(['is_approved' => true]);

        $token = QrTokenService::current($target)['token'];

        $response = $this->actingAs($student)->postJson('/officer/attendance/scan', [
            'session_id' => $session->id,
            'token' => $target->id . ':' . $token,
        ]);

        $response->assertForbidden();
    }

    public function test_scanning_the_same_student_twice_returns_already_marked_not_a_duplicate_row(): void
    {
        $officer = $this->makeScanningOfficer();
        $session = $this->makeOpenSession();
        $student = User::factory()->create(['is_approved' => true]);
        $token = QrTokenService::current($student)['token'];

        $payload = ['session_id' => $session->id, 'token' => $student->id . ':' . $token];

        $this->actingAs($officer)->postJson('/officer/attendance/scan', $payload);
        $second = $this->actingAs($officer)->postJson('/officer/attendance/scan', $payload);

        $second->assertOk();
        $second->assertJson(['status' => 'already_marked']);

        $this->assertSame(1, EventAttendance::where('event_session_id', $session->id)
            ->where('user_id', $student->id)
            ->count());
    }
}
