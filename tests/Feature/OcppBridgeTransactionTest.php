<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Connector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OcppBridgeTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function bridgeHeaders(): array
    {
        return [
            'X-OCPP-Bridge-Token' => config('services.ocpp_bridge.token'),
        ];
    }

    protected function createEligibleStationAndConnector(string $ocppIdentifier): array
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

        return [$station, $connector];
    }

    public function test_start_transaction_creates_unsolicited_session(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-START-001');

        $response = $this->postJson('/api/internal/ocpp/transactions/start', [
            'ocpp_identifier' => 'CP-START-001',
            'connector_number' => 1,
            'ocpp_transaction_id' => 'TXN-001',
            'meter_start_wh' => 5000,
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $response->assertJsonStructure(['message', 'session_id']);

        $session = ChargingSession::find($response->json('session_id'));
        $this->assertSame('active', $session->status);
        $this->assertNull($session->customer_user_id);
        $this->assertSame('TXN-001', $session->ocpp_transaction_id);
        $this->assertSame(5000, $session->meter_start_wh);
    }

    public function test_start_transaction_binds_to_existing_pending_session(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-START-002');

        $pending = ChargingSession::factory()->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'customer_user_id' => null,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/internal/ocpp/transactions/start', [
            'ocpp_identifier' => 'CP-START-002',
            'connector_number' => 1,
            'ocpp_transaction_id' => 'TXN-002',
            'meter_start_wh' => 7000,
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $this->assertSame($pending->id, $response->json('session_id'));

        $pending->refresh();
        $this->assertSame('active', $pending->status);
        $this->assertSame('TXN-002', $pending->ocpp_transaction_id);
    }

    public function test_start_transaction_is_idempotent_for_retried_transaction_id(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-START-003');

        $first = $this->postJson('/api/internal/ocpp/transactions/start', [
            'ocpp_identifier' => 'CP-START-003',
            'connector_number' => 1,
            'ocpp_transaction_id' => 'TXN-003',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $second = $this->postJson('/api/internal/ocpp/transactions/start', [
            'ocpp_identifier' => 'CP-START-003',
            'connector_number' => 1,
            'ocpp_transaction_id' => 'TXN-003',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertSame($first->json('session_id'), $second->json('session_id'));
        $this->assertSame(1, ChargingSession::where('ocpp_transaction_id', 'TXN-003')->count());
    }

    public function test_start_transaction_rejects_when_connector_already_occupied(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-START-004');

        $connector->operational_status = 'occupied';
        $connector->save();

        $response = $this->postJson('/api/internal/ocpp/transactions/start', [
            'ocpp_identifier' => 'CP-START-004',
            'connector_number' => 1,
            'ocpp_transaction_id' => 'TXN-004',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $response->assertStatus(409);
    }

    public function test_start_transaction_for_unknown_station_returns_404(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/start', [
            'ocpp_identifier' => 'CP-DOES-NOT-EXIST',
            'connector_number' => 1,
            'ocpp_transaction_id' => 'TXN-005',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_start_transaction_for_unknown_connector_returns_404(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-START-005');

        $response = $this->postJson('/api/internal/ocpp/transactions/start', [
            'ocpp_identifier' => 'CP-START-005',
            'connector_number' => 999,
            'ocpp_transaction_id' => 'TXN-006',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_start_transaction_requires_all_fields(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/start', [], $this->bridgeHeaders());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ocpp_identifier', 'connector_number', 'ocpp_transaction_id', 'meter_start_wh']);
    }

    public function test_start_transaction_rejects_requests_without_bridge_token(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/start', [
            'ocpp_identifier' => 'CP-START-006',
            'connector_number' => 1,
            'ocpp_transaction_id' => 'TXN-007',
            'meter_start_wh' => 1000,
        ]);

        $response->assertStatus(401);
    }
}
