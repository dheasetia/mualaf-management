<?php

declare(strict_types=1);

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_site_root_redirects_to_the_center_panel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/center');
    }
}
