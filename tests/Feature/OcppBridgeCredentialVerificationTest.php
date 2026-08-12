<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OcppBridgeCredentialVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function bridgeHeaders(): array
    {
        return [
            'X-OCPP-Bridge-Token' => config('services.ocpp_bridge.token'),
        ];
    }

    public function test_verify_accepts_correct_credential(): void
    {
        $station = ChargingStation::factory()->create([
            'ocpp_identifier' => 'CP-AUTH-001',
            'ocpp_auth_password_hash' => Hash::make('correct-password'),
            'ocpp_auth_updated_at' => now(),
        ]);

        $response = $this->postJson('/api/internal/ocpp/verify-station-credential', [
            'ocpp_identifier' => 'CP-AUTH-001',
            'password' => 'correct-password',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $response->assertJson(['authorized' => true]);
    }

    public function test_verify_rejects_wrong_credential(): void
    {
        ChargingStation::factory()->create([
            'ocpp_identifier' => 'CP-AUTH-002',
            'ocpp_auth_password_hash' => Hash::make('correct-password'),
            'ocpp_auth_updated_at' => now(),
        ]);

        $response = $this->postJson('/api/internal/ocpp/verify-station-credential', [
            'ocpp_identifier' => 'CP-AUTH-002',
            'password' => 'wrong-password',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $response->assertJson(['authorized' => false, 'reason' => 'invalid_credential']);
    }

    public function test_verify_rejects_unknown_station(): void
    {
        $response = $this->postJson('/api/internal/ocpp/verify-station-credential', [
            'ocpp_identifier' => 'CP-DOES-NOT-EXIST',
            'password' => 'anything',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $response->assertJson(['authorized' => false, 'reason' => 'unknown_station']);
    }

    public function test_verify_rejects_station_with_no_credential_configured(): void
    {
        ChargingStation::factory()->create([
            'ocpp_identifier' => 'CP-AUTH-003',
            'ocpp_auth_password_hash' => null,
        ]);

        $response = $this->postJson('/api/internal/ocpp/verify-station-credential', [
            'ocpp_identifier' => 'CP-AUTH-003',
            'password' => 'anything',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $response->assertJson(['authorized' => false, 'reason' => 'no_credential_configured']);
    }

    public function test_verify_accepts_credential_after_rotation(): void
    {
        $station = ChargingStation::factory()->create([
            'ocpp_identifier' => 'CP-AUTH-004',
            'ocpp_auth_password_hash' => Hash::make('old-password'),
            'ocpp_auth_updated_at' => now()->subDays(30),
        ]);

        $station->ocpp_auth_password_hash = Hash::make('new-password');
        $station->ocpp_auth_updated_at = now();
        $station->save();

        $oldResponse = $this->postJson('/api/internal/ocpp/verify-station-credential', [
            'ocpp_identifier' => 'CP-AUTH-004',
            'password' => 'old-password',
        ], $this->bridgeHeaders());

        $newResponse = $this->postJson('/api/internal/ocpp/verify-station-credential', [
            'ocpp_identifier' => 'CP-AUTH-004',
            'password' => 'new-password',
        ], $this->bridgeHeaders());

        $oldResponse->assertJson(['authorized' => false]);
        $newResponse->assertJson(['authorized' => true]);
    }

    public function test_verify_requires_all_fields(): void
    {
        $response = $this->postJson('/api/internal/ocpp/verify-station-credential', [], $this->bridgeHeaders());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ocpp_identifier', 'password']);
    }

    public function test_verify_rejects_requests_without_bridge_token(): void
    {
        $response = $this->postJson('/api/internal/ocpp/verify-station-credential', [
            'ocpp_identifier' => 'CP-AUTH-005',
            'password' => 'anything',
        ]);

        $response->assertStatus(401);
    }
}
