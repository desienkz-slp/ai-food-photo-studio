<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_reports_application_is_healthy(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'ai-food-photo-studio-backend',
            ])
            ->assertJsonStructure(['status', 'service', 'timestamp']);
    }
}
