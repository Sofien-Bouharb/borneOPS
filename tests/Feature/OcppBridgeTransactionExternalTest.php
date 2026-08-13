<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Connector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OcppBridgeTransactionExternalTest extends TestCase
{
    use RefreshDatabase;

    protected function bridgeHeaders(): array
    {
        return [
            'X-OCPP-Bridge-Token' => config('services.ocpp_bridge.token'),
        ];
    }

    protected function createEligibleStationAndConnector(
        string $ocppIdentifier,
        int $evseId = 1,
        int $connectorId = 1
    ): array {
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
            'ocpp_evse_id' => $evseId,
            'ocpp_connector_id' => $connectorId,
        ]);

        return [$station, $connector];
    }

    public function test_start_transaction_external_preserves_charger_assigned_id(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP201-001');

        $response = $this->postJson('/api/internal/ocpp/transactions/start-external', [
            'ocpp_identifier' => 'CP201-001',
            'evse_id' => 1,
            'connector_id' => 1,
            'external_transaction_id' => 'CHARGER-TXN-XYZ',
            'meter_start_wh' => 2000,
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $response->assertJson(['ocpp_transaction_id' => 'CHARGER-TXN-XYZ']);

        $session = ChargingSession::find($response->json('session_id'));
        $this->assertSame('active', $session->status);
        $this->assertSame('CHARGER-TXN-XYZ', $session->ocpp_transaction_id);
        $this->assertNull($session->customer_user_id);
    }

    public function test_start_transaction_external_binds_to_existing_pending_session(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP201-002');

        $pending = ChargingSession::factory()->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'customer_user_id' => null,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/internal/ocpp/transactions/start-external', [
            'ocpp_identifier' => 'CP201-002',
            'evse_id' => 1,
            'connector_id' => 1,
            'external_transaction_id' => 'CHARGER-TXN-ABC',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $this->assertSame($pending->id, $response->json('session_id'));
    }

    public function test_start_transaction_external_is_idempotent_for_retried_transaction_id(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP201-003');

        $first = $this->postJson('/api/internal/ocpp/transactions/start-external', [
            'ocpp_identifier' => 'CP201-003',
            'evse_id' => 1,
            'connector_id' => 1,
            'external_transaction_id' => 'CHARGER-TXN-REPEAT',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $second = $this->postJson('/api/internal/ocpp/transactions/start-external', [
            'ocpp_identifier' => 'CP201-003',
            'evse_id' => 1,
            'connector_id' => 1,
            'external_transaction_id' => 'CHARGER-TXN-REPEAT',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $first->assertStatus(200);
        $second->assertStatus(200);
        $this->assertSame($first->json('session_id'), $second->json('session_id'));
        $this->assertSame(1, ChargingSession::where('ocpp_transaction_id', 'CHARGER-TXN-REPEAT')->count());
    }

    public function test_start_transaction_external_resolves_connector_when_connector_id_omitted_and_evse_has_one_connector(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP201-SOLO', evseId: 5, connectorId: 1);

        $response = $this->postJson('/api/internal/ocpp/transactions/start-external', [
            'ocpp_identifier' => 'CP201-SOLO',
            'evse_id' => 5,
            'external_transaction_id' => 'CHARGER-TXN-SOLO',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $response->assertStatus(200);

        $session = ChargingSession::find($response->json('session_id'));
        $this->assertSame($connector->id, $session->connector_id);
    }

    public function test_start_transaction_external_rejects_ambiguous_evse_when_connector_id_omitted_and_multiple_connectors_exist(): void
    {
        $station = ChargingStation::factory()->create([
            'ocpp_identifier' => 'CP201-AMBIGUOUS',
            'administrative_status' => 'active',
            'operational_status' => 'available',
            'last_heartbeat_at' => now(),
            'disconnected_at' => null,
        ]);

        Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'administrative_status' => 'enabled',
            'operational_status' => 'available',
            'ocpp_evse_id' => 2,
            'ocpp_connector_id' => 1,
        ]);

        Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 2,
            'administrative_status' => 'enabled',
            'operational_status' => 'available',
            'ocpp_evse_id' => 2,
            'ocpp_connector_id' => 2,
        ]);

        $response = $this->postJson('/api/internal/ocpp/transactions/start-external', [
            'ocpp_identifier' => 'CP201-AMBIGUOUS',
            'evse_id' => 2,
            'external_transaction_id' => 'CHARGER-TXN-AMBIGUOUS',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $response->assertStatus(409);
        $this->assertSame(0, ChargingSession::where('ocpp_transaction_id', 'CHARGER-TXN-AMBIGUOUS')->count());
    }

    public function test_start_transaction_external_for_unknown_station_returns_404(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/start-external', [
            'ocpp_identifier' => 'CP201-DOES-NOT-EXIST',
            'evse_id' => 1,
            'connector_id' => 1,
            'external_transaction_id' => 'CHARGER-TXN-1',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_start_transaction_external_for_unknown_evse_returns_404(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP201-004');

        $response = $this->postJson('/api/internal/ocpp/transactions/start-external', [
            'ocpp_identifier' => 'CP201-004',
            'evse_id' => 999,
            'external_transaction_id' => 'CHARGER-TXN-2',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_start_transaction_external_for_unknown_connector_id_at_known_evse_returns_404(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP201-006', evseId: 1, connectorId: 1);

        $response = $this->postJson('/api/internal/ocpp/transactions/start-external', [
            'ocpp_identifier' => 'CP201-006',
            'evse_id' => 1,
            'connector_id' => 999,
            'external_transaction_id' => 'CHARGER-TXN-BAD-CONNECTOR',
            'meter_start_wh' => 1000,
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_start_transaction_external_requires_all_fields(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/start-external', [], $this->bridgeHeaders());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ocpp_identifier', 'evse_id', 'external_transaction_id', 'meter_start_wh']);
    }

    public function test_start_transaction_external_rejects_requests_without_bridge_token(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/start-external', [
            'ocpp_identifier' => 'CP201-005',
            'evse_id' => 1,
            'connector_id' => 1,
            'external_transaction_id' => 'CHARGER-TXN-3',
            'meter_start_wh' => 1000,
        ]);

        $response->assertStatus(401);
    }
}
