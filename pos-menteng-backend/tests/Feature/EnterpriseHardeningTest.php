<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnterpriseHardeningTest extends TestCase
{
    public function test_readiness_endpoint_reports_database_health(): void
    {
        $response=$this->getJson('/api/ready');

        $response->assertOk()
            ->assertJsonPath('status','ok')
            ->assertJsonPath('checks.database.status','ok');
    }

    public function test_readiness_endpoint_has_security_headers(): void
    {
        $response=$this->get('/api/ready');

        $response->assertHeader('X-Content-Type-Options','nosniff')
            ->assertHeader('X-Frame-Options','DENY')
            ->assertHeader('Referrer-Policy','strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy','camera=(), microphone=(), geolocation=()');
    }
}
