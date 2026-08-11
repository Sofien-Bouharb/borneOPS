<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Services\ChargingSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OcppBridgeMeterValuesTest extends TestCase
{
    use RefreshDatabase;

    protected function bridgeHeaders(): array
    {
        return [
            'X-OCPP-Bridge-Token' => config('services.ocpp_bridge.token'),
        ];
    }

    protected function createActiveSession(string $ocppIdentifier): ChargingSession
    {
        $station = ChargingStation::factory()->create([
            'ocpp_identifier' => $ocppIdentifier,
            'administrative_status' => 'active',
            'operational_status' => 'available',
            'last_heartbeat_at' => now(),
            'disconnected_at' => null,
        ]);

        $connector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'administrative_status' => 'enabled',
            'operational_status' => 'available',
        ]);

        return app(ChargingSessionService::class)->bindOcppTransactionId($station, $connector, 5000);
    }

    public function test_meter_values_updates_latest_meter_wh(): void
    {
        $session = $this->createActiveSession('CP-METER-001');

        $response = $this->postJson('/api/internal/ocpp/transactions/meter-values', [
            'ocpp_identifier' => 'CP-METER-001',
            'ocpp_transaction_id' => $session->ocpp_transaction_id,
            'meter_value_wh' => 5500,
        ], $this->bridgeHeaders());

        $response->assertStatus(200);

        $session->refresh();
        $this->assertSame(5500, $session->latest_meter_wh);
    }

    public function test_meter_values_rejects_decreasing_reading(): void
    {
        $session = $this->createActiveSession('CP-METER-002');

        $response = $this->postJson('/api/internal/ocpp/transactions/meter-values', [
            'ocpp_identifier' => 'CP-METER-002',
            'ocpp_transaction_id' => $session->ocpp_transaction_id,
            'meter_value_wh' => 4000,
        ], $this->bridgeHeaders());

        $response->assertStatus(409);
    }

    public function test_meter_values_is_silent_no_op_for_equal_reading(): void
    {
        $session = $this->createActiveSession('CP-METER-003');

        $response = $this->postJson('/api/internal/ocpp/transactions/meter-values', [
            'ocpp_identifier' => 'CP-METER-003',
            'ocpp_transaction_id' => $session->ocpp_transaction_id,
            'meter_value_wh' => 5000,
        ], $this->bridgeHeaders());

        $response->assertStatus(200);

        $session->refresh();
        $this->assertSame(5000, $session->latest_meter_wh);
    }

    public function test_meter_values_for_unknown_station_returns_404(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/meter-values', [
            'ocpp_identifier' => 'CP-DOES-NOT-EXIST',
            'ocpp_transaction_id' => '1',
            'meter_value_wh' => 5000,
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_meter_values_for_unknown_transaction_returns_404(): void
    {
        ChargingStation::factory()->create(['ocpp_identifier' => 'CP-METER-004']);

        $response = $this->postJson('/api/internal/ocpp/transactions/meter-values', [
            'ocpp_identifier' => 'CP-METER-004',
            'ocpp_transaction_id' => '999999',
            'meter_value_wh' => 5000,
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_meter_values_requires_all_fields(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/meter-values', [], $this->bridgeHeaders());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ocpp_identifier', 'ocpp_transaction_id', 'meter_value_wh']);
    }

    public function test_meter_values_rejects_requests_without_bridge_token(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/meter-values', [
            'ocpp_identifier' => 'CP-METER-005',
            'ocpp_transaction_id' => '1',
            'meter_value_wh' => 5000,
        ]);

        $response->assertStatus(401);
    }
}
