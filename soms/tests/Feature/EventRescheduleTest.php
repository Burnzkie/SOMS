<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\OfficerPosition;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOfficerWithPermissions(array $permissions): User
    {
        $org = Organization::create([
            'name' => 'Student Government Organization',
            'department' => 'All Departments',
            'academic_year' => '2026-2027',
            'is_active' => true,
        ]);

        $officer = User::factory()->officer()->create();

        OfficerPosition::create([
            'user_id' => $officer->id,
            'organization_id' => $org->id,
            'position_title' => 'President',
            'permissions' => $permissions,
            'academic_year' => '2026-2027',
            'is_active' => true,
            'appointed_at' => now(),
        ]);

        return $officer->fresh();
    }

    protected function createEventViaController(User $officer, string $dateStart, string $dateEnd): Event
    {
        $this->actingAs($officer)->post(route('officer.events.store'), [
            'title'      => 'Foundation Week',
            'type'       => 'other',
            'date_start' => $dateStart,
            'date_end'   => $dateEnd,
            'has_parade' => false,
        ]);

        return Event::firstOrFail();
    }

    public function test_officer_with_manage_events_can_drag_an_event_to_a_new_date(): void
    {
        $officer = $this->makeOfficerWithPermissions(['manage_events']);
        $event = $this->createEventViaController($officer, '2026-10-05', '2026-10-06');
        $firstDayBefore = $event->eventDays()->orderBy('date')->first();
        $firstSessionBefore = $firstDayBefore->sessions()->where('session_type', 'morning')->first();

        $response = $this->actingAs($officer)->patch(route('officer.events.reschedule', $event), [
            'date_start' => '2026-10-12',
        ]);

        $response->assertOk();
        $event->refresh();

        // Two-day span preserved, just shifted +7 days.
        $this->assertSame('2026-10-12', $event->date_start->toDateString());
        $this->assertSame('2026-10-13', $event->date_end->toDateString());

        $firstDayAfter = $event->eventDays()->orderBy('date')->first();
        $this->assertSame('2026-10-12', $firstDayAfter->date->toDateString());

        // Time-of-day on each session is unchanged, only the date shifted.
        $sessionAfter = $firstDayAfter->sessions()->where('session_type', 'morning')->first();
        $this->assertSame(
            $firstSessionBefore->timein_start->format('H:i'),
            $sessionAfter->timein_start->format('H:i')
        );
        $this->assertSame('2026-10-12', $sessionAfter->timein_start->toDateString());
    }

    public function test_dragging_an_event_with_recorded_attendance_is_rejected(): void
    {
        $officer = $this->makeOfficerWithPermissions(['manage_events']);
        $event = $this->createEventViaController($officer, '2026-10-05', '2026-10-05');
        $day = $event->eventDays()->firstOrFail();
        $session = $day->sessions()->firstOrFail();

        EventAttendance::create([
            'event_id'         => $event->id,
            'event_day_id'     => $day->id,
            'event_session_id' => $session->id,
            'user_id'          => User::factory()->create()->id,
            'scan_type'        => 'time_in',
            'scanned_at'       => now(),
            'status'           => 'present',
        ]);

        $response = $this->actingAs($officer)->patch(route('officer.events.reschedule', $event), [
            'date_start' => '2026-10-20',
        ]);

        $response->assertStatus(422);
        $this->assertSame('2026-10-05', $event->fresh()->date_start->toDateString());
    }

    public function test_officer_without_manage_events_cannot_reschedule(): void
    {
        $officer = $this->makeOfficerWithPermissions([]);
        $event = $this->createEventViaController(
            $this->makeOfficerWithPermissions(['manage_events']),
            '2026-10-05',
            '2026-10-05'
        );

        $response = $this->actingAs($officer)->patch(route('officer.events.reschedule', $event), [
            'date_start' => '2026-10-20',
        ]);

        $response->assertForbidden();
    }
}
