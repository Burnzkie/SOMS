<?php
// tests/Feature/AnalyticsTest.php

namespace Tests\Feature;

use App\Models\AnalyticsDailyHit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers TrackPageView + RecordPageViewJob (the first-party analytics
 * added Sep 2026) and the admin-only Analytics page that reads from it.
 * QUEUE_CONNECTION=sync in phpunit.xml means RecordPageViewJob runs
 * inline within the same request here -- no queue worker needed for
 * these assertions to see the result immediately.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_a_web_route_records_a_web_hit(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/dashboard');

        $this->assertDatabaseHas('analytics_daily_hits', [
            'date' => Carbon::today()->toDateString(),
            'route_name' => 'admin.dashboard',
            'platform' => 'web',
            'hits' => 1,
        ]);
    }

    public function test_visiting_an_api_route_records_a_mobile_hit(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student, ['*']);

        $this->getJson('/api/v1/academic-programs');

        $this->assertDatabaseHas('analytics_daily_hits', [
            'date' => Carbon::today()->toDateString(),
            'platform' => 'mobile',
            'hits' => 1,
        ]);
    }

    /**
     * The bug this test guards against: every route in routes/api.php
     * (and api_admin/api_officer/api_student.php) is unnamed. A design
     * that only ever read $route->getName() would store null for every
     * single mobile request and then exclude all of them (see
     * TrackPageView's docblock) -- silently zeroing out mobile usage
     * data entirely. Confirms the URI-pattern fallback actually fires
     * and produces a stable, low-cardinality identifier.
     */
    public function test_an_unnamed_api_route_falls_back_to_its_uri_pattern_as_the_identifier(): void
    {
        $this->getJson('/api/v1/academic-programs');

        $this->assertDatabaseHas('analytics_daily_hits', [
            'route_name' => 'api/v1/academic-programs',
            'platform' => 'mobile',
        ]);
    }

    public function test_repeated_visits_the_same_day_increment_the_same_row_not_new_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/dashboard');
        $this->actingAs($admin)->get('/admin/dashboard');
        $this->actingAs($admin)->get('/admin/dashboard');

        $this->assertSame(1, AnalyticsDailyHit::where('route_name', 'admin.dashboard')->count());
        $this->assertDatabaseHas('analytics_daily_hits', [
            'route_name' => 'admin.dashboard',
            'hits' => 3,
        ]);
    }

    /**
     * app-version is polled by the Flutter app on every launch and
     * update check -- tracking it would drown every other route in
     * noise rather than reflect actual usage, so TrackPageView excludes
     * it by name.
     */
    public function test_the_app_version_poll_endpoint_is_not_tracked(): void
    {
        $this->getJson('/api/v1/app-version');

        // This route is unnamed like every other API route (see
        // TrackPageView's docblock), so if it weren't excluded it would
        // fall back to its URI pattern -- confirm that exact identifier
        // never got a row, not a name it never had in the first place.
        $this->assertDatabaseMissing('analytics_daily_hits', [
            'route_name' => 'api/v1/app-version',
        ]);
    }

    public function test_no_personal_data_columns_exist_on_the_analytics_table(): void
    {
        // Structural guarantee, not just a convention -- if a future edit
        // ever adds a user_id, ip, or user_agent column to this table,
        // this test fails and forces a conscious decision about it.
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('analytics_daily_hits');

        foreach (['user_id', 'ip', 'ip_address', 'user_agent', 'email', 'student_id'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
    }

    public function test_admin_can_view_the_analytics_page(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/analytics');

        $response->assertOk();
        $response->assertSee('Analytics');
    }

    public function test_a_student_cannot_view_the_analytics_page(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get('/admin/analytics');

        $response->assertForbidden();
    }
}
