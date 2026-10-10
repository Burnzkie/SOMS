<?php
// tests/Feature/Api/ScanBatchTest.php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\EventDay;
use App\Models\EventSession;
use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\User;
use App\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers POST /api/v1/officer/attendance/scan-batch — the offline-first
 * sync path from the v3.4 addendum. This was flagged as a gap: the only
 * existing attendance coverage (FineIssuanceAndAttendanceTest) exercises
 * the live /officer/attendance/scan endpoint, never scan-batch, and this
 * is the path most likely to fail in the field (device clock drift,
 * duplicate submission after a retried sync).
 */
class ScanBatchTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $admin;
    protected User $officer;
    protected EventSession $session;

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

        $this->officer = User::factory()->officer()->create();
        OfficerPosition::create([
            'user_id' => $this->officer->id,
            'organization_id' => $this->org->id,
            'position_title' => 'Secretary',
            'permissions' => ['manage_attendance'],
            'academic_year' => '2026-2027',
            'is_active' => true,
            'appointed_at' => now(),
        ]);

        $event = Event::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'title' => 'Offline Scan Test Event',
            'type' => 'other',
            'date_start' => now()->toDateString(),
            'date_end' => now()->toDateString(),
            'is_published' => true,
        ]);
        $day = EventDay::create(['event_id' => $event->id, 'date' => now()->toDateString()]);

        $this->session = EventSession::create([
            'event_day_id' => $day->id,
            'session_type' => 'morning',
            'timein_start' => now()->subMinutes(30),
            'timein_end' => now()->addMinutes(30), // window open
        ]);

        Sanctum::actingAs($this->officer, ['*']);
    }

    /**
     * QrTokenService::current() always generates a token for the real
     * current instant. To test a scan whose device_scanned_at is far in
     * the past, the token itself has to be valid *for that past instant*
     * -- otherwise QrTokenService::verify() rejects it as an invalid
     * token before the scan ever reaches the clock-drift-flag logic
     * (verify()'s own ±180s tolerance is separate from, and much
     * tighter than, the 10-minute drift-flag threshold). generateForWindow()
     * is intentionally protected (see the class docblock -- app.key
     * never leaves the server), so this reaches it via reflection rather
     * than widening its visibility just for a test.
     */
    protected function tokenForTimestamp(User $user, int $timestamp): string
    {
        $window = QrTokenService::currentWindow($timestamp);
        $method = new \ReflectionMethod(QrTokenService::class, 'generateForWindow');
        $method->setAccessible(true);
        return $method->invoke(null, $user, $window);
    }

    public function test_a_scan_within_the_device_clock_drift_tolerance_is_marked_present(): void
    {
        $student = User::factory()->create(['is_approved' => true]);
        $token = QrTokenService::current($student)['token'];

        $response = $this->postJson('/api/v1/officer/attendance/scan-batch', [
            'scans' => [[
                'session_id' => $this->session->id,
                'user_id' => $student->id,
                'token' => $token,
                'device_scanned_at' => now()->subMinutes(2)->toIso8601String(), // within 10-min tolerance
            ]],
        ]);

        $response->assertOk();
        $response->assertJson(['data' => [['status' => 'present']]]);

        $this->assertDatabaseHas('event_attendance', [
            'event_session_id' => $this->session->id,
            'user_id' => $student->id,
            'synced_from_offline_queue' => true,
        ]);
    }

    /**
     * Core offline-sync behavior from the v3.4 addendum: a device clock
     * more than 10 minutes off from the server doesn't get silently
     * accepted or silently rejected — it's flagged for a human to review,
     * and the flag itself is written to the activity log.
     */
    public function test_a_scan_beyond_the_drift_tolerance_is_flagged_for_review_not_rejected(): void
    {
        $student = User::factory()->create(['is_approved' => true]);
        $deviceScannedAt = now()->subMinutes(25); // beyond the 10-min drift-flag threshold
        $token = $this->tokenForTimestamp($student, $deviceScannedAt->timestamp);

        $response = $this->postJson('/api/v1/officer/attendance/scan-batch', [
            'scans' => [[
                'session_id' => $this->session->id,
                'user_id' => $student->id,
                'token' => $token,
                'device_scanned_at' => $deviceScannedAt->toIso8601String(),
            ]],
        ]);

        $response->assertOk();
        $response->assertJson(['data' => [['status' => 'flagged_for_review']]]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'offline_scan_clock_drift_flagged',
        ]);
    }

    public function test_replaying_the_same_scan_in_a_later_batch_returns_already_marked(): void
    {
        $student = User::factory()->create(['is_approved' => true]);
        $token = QrTokenService::current($student)['token'];

        $payload = [
            'scans' => [[
                'session_id' => $this->session->id,
                'user_id' => $student->id,
                'token' => $token,
                'device_scanned_at' => now()->subMinutes(1)->toIso8601String(),
            ]],
        ];

        $this->postJson('/api/v1/officer/attendance/scan-batch', $payload);
        $second = $this->postJson('/api/v1/officer/attendance/scan-batch', $payload);

        $second->assertOk();
        $second->assertJson(['data' => [['status' => 'already_marked']]]);

        $this->assertSame(1, EventAttendance::where('event_session_id', $this->session->id)
            ->where('user_id', $student->id)
            ->count());
    }

    public function test_a_batch_processes_multiple_scans_independently(): void
    {
        $studentA = User::factory()->create(['is_approved' => true]);
        $studentB = User::factory()->create(['is_approved' => true]);

        $response = $this->postJson('/api/v1/officer/attendance/scan-batch', [
            'scans' => [
                [
                    'session_id' => $this->session->id,
                    'user_id' => $studentA->id,
                    'token' => QrTokenService::current($studentA)['token'],
                    'device_scanned_at' => now()->subMinute()->toIso8601String(),
                ],
                [
                    'session_id' => $this->session->id,
                    'user_id' => $studentB->id,
                    'token' => 'not-a-valid-token',
                    'device_scanned_at' => now()->subMinute()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame('present', $data[0]['status']);
        $this->assertSame('rejected', $data[1]['status']);
    }

    public function test_a_delegate_without_manage_attendance_permission_cannot_batch_scan(): void
    {
        $delegateStudent = User::factory()->create();
        Sanctum::actingAs($delegateStudent, ['*']);

        $target = User::factory()->create(['is_approved' => true]);

        $response = $this->postJson('/api/v1/officer/attendance/scan-batch', [
            'scans' => [[
                'session_id' => $this->session->id,
                'user_id' => $target->id,
                'token' => QrTokenService::current($target)['token'],
                'device_scanned_at' => now()->toIso8601String(),
            ]],
        ]);

        $response->assertForbidden();
    }
}
