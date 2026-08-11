<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OcppBridgeAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_without_token_is_rejected(): void
    {
        $response = $this->getJson('/api/internal/ocpp/ping');

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthorized. A valid OCPP bridge token is required.']);
    }

    public function test_request_with_wrong_token_is_rejected(): void
    {
        $response = $this->getJson('/api/internal/ocpp/ping', [
            'X-OCPP-Bridge-Token' => 'wrong-token-value',
        ]);

        $response->assertStatus(401);
    }

    public function test_request_with_correct_token_succeeds(): void
    {
        $token = config('services.ocpp_bridge.token');

        $response = $this->getJson('/api/internal/ocpp/ping', [
            'X-OCPP-Bridge-Token' => $token,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'OCPP bridge authenticated successfully.']);
    }

    public function test_request_with_empty_configured_token_is_always_rejected(): void
    {
        config(['services.ocpp_bridge.token' => null]);

        $response = $this->getJson('/api/internal/ocpp/ping', [
            'X-OCPP-Bridge-Token' => 'anything-at-all',
        ]);

        $response->assertStatus(401);
    }
}
