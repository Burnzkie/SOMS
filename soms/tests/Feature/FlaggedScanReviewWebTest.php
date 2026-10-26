<?php
// tests/Feature/FlaggedScanReviewWebTest.php

namespace Tests\Feature;

use App\Models\AttendanceDelegate;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventDay;
use App\Models\EventFineRule;
use App\Models\EventSession;
use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Web UI for reviewing offline scans flagged for clock drift: the panel on
 * the scan station page and the approve/reject form posts behind it.
 * (The mobile/API counterpart is covered in Api\ScanBatchTest.)
 */
class FlaggedScanReviewWebTest extends TestCase
{
    use RefreshDatabase;

    protected User $officer;
    protected EventSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name' => 'Student Government Organization',
            'department' => 'All Departments',
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);
        $admin = User::factory()->admin()->create();

        $this->officer = User::factory()->officer()->create();
        OfficerPosition::create([
            'user_id' => $this->officer->id,
            'organization_id' => $org->id,
            'position_title' => 'Secretary',
            'permissions' => ['manage_attendance'],
            'academic_year' => '2026-2027',
            'is_active' => true,
            'appointed_at' => now(),
        ]);

        $event = Event::create([
            'organization_id' => $org->id,
            'created_by' => $admin->id,
            'title' => 'Review UI Test Event',
            'type' => 'other',
            'date_start' => now()->toDateString(),
            'date_end' => now()->toDateString(),
            'is_published' => true,
        ]);
        $day = EventDay::create(['event_id' => $event->id, 'date' => now()->toDateString()]);

        $this->session = EventSession::create([
            'event_day_id' => $day->id,
            'session_type' => 'morning',
            'timein_start' => now()->subHour(),
            'timein_end' => now()->subMinutes(5), // window closed, so a rejection is fined
        ]);
    }

    protected function makeFlaggedScan(string $name = 'Flagged Student'): EventAttendance
    {
        $student = User::factory()->create(['is_approved' => true, 'name' => $name]);

        return EventAttendance::create([
            'event_id' => $this->session->eventDay->event_id,
            'event_day_id' => $this->session->event_day_id,
            'event_session_id' => $this->session->id,
            'user_id' => $student->id,
            'scan_type' => 'time_in',
            'scanned_at' => now(),
            'device_scanned_at' => now()->subMinutes(25),
            'status' => 'flagged_for_review',
            'synced_from_offline_queue' => true,
        ]);
    }

    /**
     * An officer-role user with no manage_attendance permission who is
     * assigned as this session's delegate: can open the station and scan,
     * but the policy must still deny review.
     */
    protected function makeDelegate(): User
    {
        $delegate = User::factory()->officer()->create(['is_approved' => true]);
        AttendanceDelegate::create([
            'event_session_id' => $this->session->id,
            'user_id' => $delegate->id,
            'assigned_by' => $this->officer->id,
        ]);

        return $delegate;
    }

    public function test_scan_station_lists_flagged_scans_for_an_officer(): void
    {
        $this->makeFlaggedScan('Maria Flagged');

        $this->actingAs($this->officer)
            ->get(route('officer.attendance.station', $this->session))
            ->assertOk()
            ->assertSee('Flagged scans')
            ->assertSee('Maria Flagged');
    }

    public function test_scan_station_hides_the_review_panel_when_nothing_is_flagged(): void
    {
        $this->actingAs($this->officer)
            ->get(route('officer.attendance.station', $this->session))
            ->assertOk()
            ->assertDontSee('Flagged scans');
    }

    public function test_a_delegate_can_scan_but_does_not_see_the_review_panel(): void
    {
        $this->makeFlaggedScan('Hidden From Delegate');
        $delegate = $this->makeDelegate();

        $this->actingAs($delegate)
            ->get(route('officer.attendance.station', $this->session))
            ->assertOk()
            ->assertDontSee('Hidden From Delegate');
    }

    public function test_approving_marks_the_student_present(): void
    {
        $scan = $this->makeFlaggedScan();

        $this->actingAs($this->officer)
            ->post(route('officer.attendance.review', $scan), ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertSame('present', $scan->fresh()->status);
        $this->assertDatabaseMissing('fines', ['user_id' => $scan->user_id]);
    }

    public function test_rejecting_marks_the_student_absent_and_fines_them(): void
    {
        EventFineRule::create([
            'event_id' => $this->session->eventDay->event_id,
            'violation_type' => 'missed_morning_timein',
            'amount' => 50,
        ]);
        $scan = $this->makeFlaggedScan();

        $this->actingAs($this->officer)
            ->post(route('officer.attendance.review', $scan), ['decision' => 'reject'])
            ->assertRedirect();

        $this->assertSame('absent', $scan->fresh()->status);
        $this->assertDatabaseHas('fines', [
            'user_id' => $scan->user_id,
            'violation_type' => 'missed_morning_timein',
            'status' => 'unpaid',
        ]);
    }

    public function test_a_delegate_cannot_review_a_scan(): void
    {
        $scan = $this->makeFlaggedScan();
        $delegate = $this->makeDelegate();

        $this->actingAs($delegate)
            ->post(route('officer.attendance.review', $scan), ['decision' => 'approve'])
            ->assertForbidden();

        $this->assertSame('flagged_for_review', $scan->fresh()->status);
    }

    public function test_reviewing_the_same_scan_twice_does_not_change_the_first_decision(): void
    {
        $scan = $this->makeFlaggedScan();

        $this->actingAs($this->officer)
            ->post(route('officer.attendance.review', $scan), ['decision' => 'approve']);
        $this->actingAs($this->officer)
            ->post(route('officer.attendance.review', $scan), ['decision' => 'reject'])
            ->assertSessionHasErrors('review');

        $this->assertSame('present', $scan->fresh()->status);
    }
}
