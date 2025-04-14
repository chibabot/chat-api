<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_that_application_is_healthy(): void
    {
        $response = $this->get('/api/status');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'database',
                'timestamp'
            ])
            ->assertJson([
                'status' => 'OK',
                'database' => 'Connected'
            ]);
    }
}
