<?php
// tests/Feature/EventFeatureCardTest.php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsAuditData;
use Tests\TestCase;

/**
 * The upcoming-event card on the dashboards. Its overlay used to be a blue
 * wash over the whole card, hiding the event photo; and an event with no
 * photo showed an empty grey box under that wash.
 */
class EventFeatureCardTest extends TestCase
{
    use RefreshDatabase, BuildsAuditData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAuditData();
    }

    // The stylesheet itself contains the text "feature-bg--plain" on every page,
    // so the tests look at the element's class attribute, not the bare word.
    private const PLAIN_CARD = 'class="feature-bg feature-bg--plain"';

    private function officerDashboard()
    {
        $officer = $this->makeOfficerWith('Secretary', ['view_dashboard']);

        return $this->actingAs($officer)->get(route('officer.dashboard'));
    }

    public function test_an_event_with_a_photo_shows_the_photo_and_not_the_brand_gradient(): void
    {
        Storage::fake('r2');
        $this->event->update(['image_path' => 'events/cover.jpg']);

        $this->officerDashboard()
            ->assertOk()
            ->assertSee('events/cover.jpg')
            ->assertDontSee(self::PLAIN_CARD, false);
    }

    public function test_an_event_without_a_photo_gets_the_brand_gradient_background(): void
    {
        $this->officerDashboard()
            ->assertOk()
            ->assertSee(self::PLAIN_CARD, false);
    }

    public function test_the_old_full_card_blue_wash_is_gone_from_the_page_styles(): void
    {
        $this->officerDashboard()
            ->assertOk()
            ->assertDontSee('rgba(19,60,180,.96)', false)   // the old wash
            ->assertSee('rgba(8,24,64,0)', false);           // the new scrim fades to clear
    }

    public function test_the_upcoming_event_tag_is_styled_to_stay_readable_on_the_photo(): void
    {
        $this->officerDashboard()
            ->assertOk()
            ->assertSee('.feature-card .dash-tag', false);
    }

    public function test_the_student_dashboard_uses_the_same_card_fix(): void
    {
        $student = $this->makeStudent();
        $student->forceFill(['is_approved' => true])->save();

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('rgba(19,60,180,.96)', false);
    }
}
