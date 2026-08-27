<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * SOMS has no public homepage -- '/' always redirects to /login.
     * See routes/web.php. This replaces Laravel's default boilerplate
     * assertion (which expected a 200 landing page that never existed
     * in this app).
     */
    public function test_the_application_redirects_root_to_login(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }
}