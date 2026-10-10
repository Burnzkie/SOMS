<?php
// tests/Feature/EventScheduleAndProgramTest.php

namespace Tests\Feature;

use App\Jobs\IssueSessionFinesJob;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventDay;
use App\Models\EventFineRule;
use App\Models\EventSession;
use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\User;
use App\Services\EventSetupService;
use App\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the per-date parade flag, optional Morning/Afternoon time windows,
 * required-program targeting, and how the fines job / scan endpoints use them.
 */
class EventScheduleAndProgramTest extends TestCase
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

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** An approved student in the given program (pass $approved = false for a pending one). */
    protected function makeStudent(string $program = 'BSIT', bool $approved = true): User
    {
        return User::factory()->create(['program' => $program, 'is_approved' => $approved]);
    }

    /** Creates an event through the same service the web form and mobile app use. */
    protected function createEvent(array $overrides = []): Event
    {
        return EventSetupService::create(array_merge([
            'title'      => 'Intramurals 2026',
            'type'       => 'other',
            'date_start' => '2026-10-10',
            'date_end'   => '2026-10-14',
        ], $overrides), $this->org->id, $this->admin->id);
    }

    /** A single-session event whose time-in window already closed (fines are due). */
    protected function makeClosedSession(array $eventOverrides = [], array $sessionOverrides = []): EventSession
    {
        $event = Event::create(array_merge([
            'organization_id' => $this->org->id,
            'created_by'      => $this->admin->id,
            'title'           => 'Test Event',
            'type'            => 'other',
            'date_start'      => now()->toDateString(),
            'date_end'        => now()->toDateString(),
            'is_published'    => true,
        ], $eventOverrides));

        $day = EventDay::create(['event_id' => $event->id, 'date' => now()->toDateString()]);

        return EventSession::create(array_merge([
            'event_day_id' => $day->id,
            'session_type' => 'morning',
            'timein_start' => now()->subHour(),
            'timein_end'   => now()->subMinutes(5),
        ], $sessionOverrides));
    }

    protected function setFineRule(Event $event, string $violationType, float $amount): void
    {
        EventFineRule::create(['event_id' => $event->id, 'violation_type' => $violationType, 'amount' => $amount]);
    }

    protected function makeScanningOfficer(): User
    {
        $officer = User::factory()->officer()->create();
        OfficerPosition::create([
            'user_id' => $officer->id,
            'organization_id' => $this->org->id,
            'position_title' => 'Secretary',
            'permissions' => ['manage_attendance'],
            'academic_year' => '2026-2027',
            'is_active' => true,
            'appointed_at' => now(),
        ]);

        return $officer->fresh();
    }

    // ---------------------------------------------------------------
    // Event creation: parade dates + time windows
    // ---------------------------------------------------------------

    public function test_parade_date_flags_only_that_days_morning_session(): void
    {
        $event = $this->createEvent(['parade_dates' => ['2026-10-10']]);

        $this->assertSame(10, EventSession::count()); // 5 days x (morning + afternoon)

        $paradeSessions = EventSession::where('is_parade', true)->get();
        $this->assertCount(1, $paradeSessions);
        $this->assertSame('morning', $paradeSessions->first()->session_type);
        $this->assertSame('2026-10-10', $paradeSessions->first()->eventDay->date->toDateString());

        $this->assertTrue($event->fresh()->has_parade);
        $this->assertDatabaseHas('event_fine_rules', ['event_id' => $event->id, 'violation_type' => 'missed_parade']);
    }

    public function test_event_without_parade_dates_has_no_parade_flag_or_parade_fine_rule(): void
    {
        $event = $this->createEvent();

        $this->assertSame(0, EventSession::where('is_parade', true)->count());
        $this->assertFalse($event->fresh()->has_parade);
        $this->assertDatabaseMissing('event_fine_rules', ['event_id' => $event->id, 'violation_type' => 'missed_parade']);
    }

    public function test_legacy_has_parade_flag_without_dates_means_parade_on_the_first_day(): void
    {
        // Older mobile builds only send has_parade=true.
        $this->createEvent(['has_parade' => true]);

        $flagged = EventSession::where('is_parade', true)->get();
        $this->assertCount(1, $flagged);
        $this->assertSame('2026-10-10', $flagged->first()->eventDay->date->toDateString());
    }

    public function test_blank_timeout_creates_a_session_with_no_timeout_window(): void
    {
        $this->createEvent([
            'date_end'             => '2026-10-10',
            'morning_timein_start' => '11:00',
            'morning_timein_end'   => '12:00',
            'morning_timeout_start' => null,
            'morning_timeout_end'   => null,
        ]);

        $morning = EventSession::where('session_type', 'morning')->first();
        $this->assertSame('11:00', $morning->timein_start->format('H:i'));
        $this->assertNull($morning->timeout_start);
        $this->assertNull($morning->timeout_end);

        // Afternoon sent no times at all, so it keeps the defaults (including its time-out).
        $afternoon = EventSession::where('session_type', 'afternoon')->first();
        $this->assertSame('13:00', $afternoon->timein_start->format('H:i'));
        $this->assertNotNull($afternoon->timeout_start);
    }

    // ---------------------------------------------------------------
    // Editing an existing event's schedule / programs
    // ---------------------------------------------------------------

    public function test_unchanged_schedule_submission_changes_nothing(): void
    {
        $event = $this->createEvent(['parade_dates' => ['2026-10-10']]);
        $before = EventSession::orderBy('id')->get()->toArray();

        $error = EventSetupService::updateSchedule($event, [
            'parade_dates_submitted' => 1,
            'parade_dates'           => ['2026-10-10'],
        ]);

        $this->assertNull($error);
        $this->assertSame($before, EventSession::orderBy('id')->get()->toArray());
    }

    public function test_parade_dates_can_be_changed_before_attendance_exists(): void
    {
        $event = $this->createEvent(['parade_dates' => ['2026-10-10']]);

        $error = EventSetupService::updateSchedule($event, [
            'parade_dates_submitted' => 1,
            'parade_dates'           => ['2026-10-12'],
        ]);

        $this->assertNull($error);
        $flagged = EventSession::where('is_parade', true)->get();
        $this->assertCount(1, $flagged);
        $this->assertSame('2026-10-12', $flagged->first()->eventDay->date->toDateString());
    }

    public function test_schedule_changes_are_blocked_once_attendance_is_recorded(): void
    {
        $event = $this->createEvent();
        $session = EventSession::first();
        $student = $this->makeStudent();

        EventAttendance::create([
            'event_id' => $event->id,
            'event_day_id' => $session->event_day_id,
            'event_session_id' => $session->id,
            'user_id' => $student->id,
            'scan_type' => 'time_in',
            'scanned_at' => now(),
            'status' => 'present',
        ]);

        $error = EventSetupService::updateSchedule($event, [
            'parade_dates_submitted' => 1,
            'parade_dates'           => ['2026-10-10'],
        ]);

        $this->assertNotNull($error);
        $this->assertSame(0, EventSession::where('is_parade', true)->count());
    }

    public function test_required_programs_can_be_set_and_are_locked_after_fines_are_issued(): void
    {
        $this->makeStudent('BSIT'); // programs are chosen from approved students' programs
        $event = $this->createEvent();

        $error = EventSetupService::updateSchedule($event, [
            'target_programs_submitted' => 1,
            'target_programs'           => ['BSIT'],
        ]);
        $this->assertNull($error);
        $this->assertSame(['BSIT'], $event->fresh()->target_programs);

        EventSession::first()->update(['fines_issued' => true]);

        $blocked = EventSetupService::updateSchedule($event->fresh(), [
            'target_programs_submitted' => 1,
            'target_programs'           => [],
        ]);
        $this->assertNotNull($blocked);
        $this->assertSame(['BSIT'], $event->fresh()->target_programs);
    }

    // ---------------------------------------------------------------
    // Event::appliesTo
    // ---------------------------------------------------------------

    public function test_event_without_target_programs_applies_to_everyone(): void
    {
        $event = new Event(['target_programs' => null]);

        $this->assertTrue($event->appliesTo($this->makeStudent('BSIT')));
        $this->assertTrue($event->appliesTo(User::factory()->make(['program' => null])));
    }

    public function test_targeted_event_matches_programs_ignoring_case_and_spacing(): void
    {
        $event = new Event(['target_programs' => ['BSIT']]);

        $this->assertTrue($event->appliesTo(User::factory()->make(['program' => ' bsit '])));
        $this->assertFalse($event->appliesTo(User::factory()->make(['program' => 'BSED'])));
        $this->assertFalse($event->appliesTo(User::factory()->make(['program' => null])));
    }

    // ---------------------------------------------------------------
    // Fine issuance
    // ---------------------------------------------------------------

    public function test_parade_morning_uses_the_parade_fine_for_time_in_and_the_regular_rule_for_time_out(): void
    {
        $session = $this->makeClosedSession([], [
            'is_parade'     => true,
            'timein_start'  => now()->subHours(2),
            'timein_end'    => now()->subHour(),
            'timeout_start' => now()->subMinutes(30),
            'timeout_end'   => now()->subMinutes(10),
        ]);
        $event = $session->eventDay->event;
        $this->setFineRule($event, 'missed_parade', 250);
        $this->setFineRule($event, 'missed_morning_timein', 25);
        $this->setFineRule($event, 'missed_morning_timeout', 25);
        $student = $this->makeStudent();

        IssueSessionFinesJob::dispatchSync($session->id);

        $this->assertDatabaseHas('fines', ['user_id' => $student->id, 'violation_type' => 'missed_parade', 'amount' => 250]);
        $this->assertDatabaseHas('fines', ['user_id' => $student->id, 'violation_type' => 'missed_morning_timeout', 'amount' => 25]);
        $this->assertDatabaseMissing('fines', ['user_id' => $student->id, 'violation_type' => 'missed_morning_timein']);
    }

    public function test_all_approved_students_are_fined_not_only_organization_members(): void
    {
        $session = $this->makeClosedSession();
        $this->setFineRule($session->eventDay->event, 'missed_morning_timein', 25);

        $approved = $this->makeStudent();               // no OrganizationMember row at all
        $pending  = $this->makeStudent('BSIT', false);  // not approved yet

        IssueSessionFinesJob::dispatchSync($session->id);

        $this->assertDatabaseHas('fines', ['user_id' => $approved->id, 'event_session_id' => $session->id]);
        $this->assertDatabaseMissing('fines', ['user_id' => $pending->id]);
        $this->assertDatabaseMissing('fines', ['user_id' => $this->admin->id]);
    }

    public function test_only_students_in_the_required_programs_are_fined(): void
    {
        $session = $this->makeClosedSession(['target_programs' => ['BSIT']]);
        $this->setFineRule($session->eventDay->event, 'missed_morning_timein', 25);

        $inProgram  = $this->makeStudent('BSIT');
        $outProgram = $this->makeStudent('BSED');

        IssueSessionFinesJob::dispatchSync($session->id);

        $this->assertDatabaseHas('fines', ['user_id' => $inProgram->id]);
        $this->assertDatabaseMissing('fines', ['user_id' => $outProgram->id]);
    }

    public function test_draft_events_issue_no_fines(): void
    {
        $session = $this->makeClosedSession(['is_published' => false]);
        $this->setFineRule($session->eventDay->event, 'missed_morning_timein', 25);
        $this->makeStudent();

        IssueSessionFinesJob::dispatchSync($session->id, true);

        $this->assertSame(0, \App\Models\Fine::count());
    }

    public function test_unset_zero_amount_rules_issue_no_fines(): void
    {
        $session = $this->makeClosedSession();
        $this->setFineRule($session->eventDay->event, 'missed_morning_timein', 0);
        $this->makeStudent();

        IssueSessionFinesJob::dispatchSync($session->id);

        $this->assertSame(0, \App\Models\Fine::count());
    }

    public function test_session_without_a_timeout_window_issues_no_timeout_fine_even_when_forced(): void
    {
        $session = $this->makeClosedSession(); // timeout_start / timeout_end are null
        $event = $session->eventDay->event;
        $this->setFineRule($event, 'missed_morning_timein', 25);
        $this->setFineRule($event, 'missed_morning_timeout', 25);
        $student = $this->makeStudent();

        IssueSessionFinesJob::dispatchSync($session->id, true);

        $this->assertDatabaseHas('fines', ['user_id' => $student->id, 'violation_type' => 'missed_morning_timein']);
        $this->assertDatabaseMissing('fines', ['user_id' => $student->id, 'violation_type' => 'missed_morning_timeout']);
    }

    // ---------------------------------------------------------------
    // Scan guard (web endpoint)
    // ---------------------------------------------------------------

    public function test_web_scan_rejects_students_outside_the_required_programs(): void
    {
        $officer = $this->makeScanningOfficer();
        $session = $this->makeClosedSession(['target_programs' => ['BSIT']], [
            'timein_start' => now()->subMinutes(5),
            'timein_end'   => now()->addMinutes(30), // window open
        ]);
        $outProgram = $this->makeStudent('BSED');
        $token = QrTokenService::current($outProgram)['token'];

        $response = $this->actingAs($officer)->postJson('/officer/attendance/scan', [
            'session_id' => $session->id,
            'token'      => $outProgram->id . ':' . $token,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'rejected', 'reason' => 'Not required for this event']);
        $this->assertDatabaseMissing('event_attendance', ['user_id' => $outProgram->id]);
    }

    public function test_web_scan_accepts_students_in_the_required_programs(): void
    {
        $officer = $this->makeScanningOfficer();
        $session = $this->makeClosedSession(['target_programs' => ['BSIT']], [
            'timein_start' => now()->subMinutes(5),
            'timein_end'   => now()->addMinutes(30),
        ]);
        $inProgram = $this->makeStudent('BSIT');
        $token = QrTokenService::current($inProgram)['token'];

        $response = $this->actingAs($officer)->postJson('/officer/attendance/scan', [
            'session_id' => $session->id,
            'token'      => $inProgram->id . ':' . $token,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'present']);
    }

    // ---------------------------------------------------------------
    // Attendance setup: short event / multiple activities
    // ---------------------------------------------------------------

    public function test_short_event_creates_one_attendance_session_per_day(): void
    {
        $event = $this->createEvent([
            'date_end'         => '2026-10-11',
            'attendance_setup' => 'short',
            'custom_sessions'  => [[
                'name' => 'ignored', 'timein_start' => '14:00', 'timein_end' => '14:30',
                'timeout_start' => '15:30', 'timeout_end' => '16:00',
            ]],
        ]);

        $this->assertSame(2, EventSession::count()); // 2 days x 1 session

        $session = EventSession::first();
        $this->assertSame('custom', $session->session_type);
        $this->assertSame('Attendance', $session->name);
        $this->assertSame('14:00', $session->timein_start->format('H:i'));
        $this->assertSame('16:00', $session->timeout_end->format('H:i'));
        $this->assertSame(0, EventSession::where('is_parade', true)->count());

        $this->assertDatabaseHas('event_fine_rules', ['event_id' => $event->id, 'violation_type' => 'missed_custom_timein']);
        $this->assertDatabaseHas('event_fine_rules', ['event_id' => $event->id, 'violation_type' => 'missed_custom_timeout']);
        $this->assertDatabaseMissing('event_fine_rules', ['event_id' => $event->id, 'violation_type' => 'missed_morning_timein']);
    }

    public function test_multiple_activities_create_named_sessions_in_order_on_every_day(): void
    {
        $event = $this->createEvent([
            'date_end'         => '2026-10-11',
            'attendance_setup' => 'custom',
            'custom_sessions'  => [
                ['name' => 'Opening Program', 'timein_start' => '08:00', 'timein_end' => '08:30'],
                ['name' => '', 'timein_start' => '09:00', 'timein_end' => '09:30', 'timeout_start' => '11:00', 'timeout_end' => '11:30'],
            ],
        ]);

        $this->assertSame(4, EventSession::count()); // 2 days x 2 sessions

        $firstDay = $event->eventDays()->orderBy('date')->first()->sessions()->orderBy('sort_order')->get();
        $this->assertSame(['Opening Program', 'Session 2'], $firstDay->pluck('name')->all());
        $this->assertEquals([1, 2], $firstDay->pluck('sort_order')->all());
        $this->assertNull($firstDay[0]->timeout_start);
        $this->assertNotNull($firstDay[1]->timeout_start);
    }

    public function test_custom_event_without_any_timeout_has_no_timeout_fine_rule(): void
    {
        $event = $this->createEvent([
            'date_end'         => '2026-10-10',
            'attendance_setup' => 'custom',
            'custom_sessions'  => [['name' => 'Talk', 'timein_start' => '09:00', 'timein_end' => '09:30']],
        ]);

        $this->assertDatabaseHas('event_fine_rules', ['event_id' => $event->id, 'violation_type' => 'missed_custom_timein']);
        $this->assertDatabaseMissing('event_fine_rules', ['event_id' => $event->id, 'violation_type' => 'missed_custom_timeout']);
    }

    public function test_custom_session_times_can_be_edited_for_every_day(): void
    {
        $event = $this->createEvent([
            'date_end'         => '2026-10-11',
            'attendance_setup' => 'custom',
            'custom_sessions'  => [['name' => 'Seminar', 'timein_start' => '09:00', 'timein_end' => '09:30']],
        ]);

        $error = EventSetupService::updateSchedule($event, ['custom_sessions' => [
            1 => ['name' => 'Seminar', 'timein_start' => '10:00', 'timein_end' => '10:30', 'timeout_start' => '', 'timeout_end' => ''],
        ]]);

        $this->assertNull($error);
        $this->assertSame(2, EventSession::count());
        foreach (EventSession::all() as $session) {
            $this->assertSame('10:00', $session->timein_start->format('H:i'));
        }
    }

    public function test_custom_session_edits_are_blocked_once_attendance_exists(): void
    {
        $event = $this->createEvent([
            'date_end'         => '2026-10-10',
            'attendance_setup' => 'custom',
            'custom_sessions'  => [['name' => 'Seminar', 'timein_start' => '09:00', 'timein_end' => '09:30']],
        ]);
        $session = EventSession::first();
        $student = $this->makeStudent();

        EventAttendance::create([
            'event_id' => $event->id,
            'event_day_id' => $session->event_day_id,
            'event_session_id' => $session->id,
            'user_id' => $student->id,
            'scan_type' => 'time_in',
            'scanned_at' => now(),
            'status' => 'present',
        ]);

        $error = EventSetupService::updateSchedule($event, ['custom_sessions' => [
            1 => ['name' => 'Seminar', 'timein_start' => '10:00', 'timein_end' => '10:30', 'timeout_start' => '', 'timeout_end' => ''],
        ]]);

        $this->assertNotNull($error);
        $this->assertSame('09:00', $session->fresh()->timein_start->format('H:i'));
    }

    public function test_missing_a_custom_session_is_fined_with_the_session_name(): void
    {
        $session = $this->makeClosedSession([], ['session_type' => 'custom', 'name' => 'Seminar', 'sort_order' => 1]);
        $this->setFineRule($session->eventDay->event, 'missed_custom_timein', 30);
        $student = $this->makeStudent();

        IssueSessionFinesJob::dispatchSync($session->id);

        $this->assertDatabaseHas('fines', [
            'user_id'        => $student->id,
            'violation_type' => 'missed_custom_timein',
            'amount'         => 30,
            'reason'         => 'Missed Seminar time-in',
        ]);
    }

    // ---------------------------------------------------------------
    // Time-out scanning
    // ---------------------------------------------------------------

    protected function makeTimeoutOpenSession(): EventSession
    {
        return $this->makeClosedSession([], [
            'timein_start'  => now()->subHours(3),
            'timein_end'    => now()->subHours(2),
            'timeout_start' => now()->subMinutes(5),
            'timeout_end'   => now()->addMinutes(30),
        ]);
    }

    public function test_web_scan_records_a_time_out_inside_the_time_out_window(): void
    {
        $officer = $this->makeScanningOfficer();
        $session = $this->makeTimeoutOpenSession();
        $student = $this->makeStudent();
        $token = QrTokenService::current($student)['token'];

        $response = $this->actingAs($officer)->postJson('/officer/attendance/scan', [
            'session_id' => $session->id,
            'token'      => $student->id . ':' . $token,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'present', 'scan_type' => 'time_out']);
        $this->assertDatabaseHas('event_attendance', [
            'event_session_id' => $session->id,
            'user_id'          => $student->id,
            'scan_type'        => 'time_out',
            'status'           => 'present',
        ]);
    }

    public function test_scanning_time_out_twice_returns_already_marked(): void
    {
        $officer = $this->makeScanningOfficer();
        $session = $this->makeTimeoutOpenSession();
        $student = $this->makeStudent();
        $token = QrTokenService::current($student)['token'];
        $payload = ['session_id' => $session->id, 'token' => $student->id . ':' . $token];

        $this->actingAs($officer)->postJson('/officer/attendance/scan', $payload);
        $second = $this->actingAs($officer)->postJson('/officer/attendance/scan', $payload);

        $second->assertJson(['status' => 'already_marked']);
        $this->assertSame(1, EventAttendance::where('event_session_id', $session->id)->where('user_id', $student->id)->count());
    }

    public function test_web_scan_is_rejected_between_the_time_in_and_time_out_windows(): void
    {
        $officer = $this->makeScanningOfficer();
        $session = $this->makeClosedSession([], [
            'timein_start'  => now()->subHours(3),
            'timein_end'    => now()->subHours(2),
            'timeout_start' => now()->addHour(),
            'timeout_end'   => now()->addHours(2),
        ]);
        $student = $this->makeStudent();
        $token = QrTokenService::current($student)['token'];

        $response = $this->actingAs($officer)->postJson('/officer/attendance/scan', [
            'session_id' => $session->id,
            'token'      => $student->id . ':' . $token,
        ]);

        $response->assertJson(['status' => 'rejected', 'reason' => 'Outside scan window']);
        $this->assertSame(0, EventAttendance::count());
    }
}