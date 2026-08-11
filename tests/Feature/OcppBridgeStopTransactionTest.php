<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Services\ChargingSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OcppBridgeStopTransactionTest extends TestCase
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

        return app(ChargingSessionService::class)->bindOcppTransactionId($station, $connector, 3000);
    }

    public function test_stop_transaction_completes_session(): void
    {
        $session = $this->createActiveSession('CP-STOP-001');

        $response = $this->postJson('/api/internal/ocpp/transactions/stop', [
            'ocpp_identifier' => 'CP-STOP-001',
            'ocpp_transaction_id' => $session->ocpp_transaction_id,
            'meter_stop_wh' => 3500,
            'reason_code' => 'vehicle_disconnected',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertSame(3500, $session->meter_stop_wh);
        $this->assertSame('vehicle_disconnected', $session->reason_code);
    }

    public function test_stop_transaction_is_idempotent_when_already_completed(): void
    {
        $session = $this->createActiveSession('CP-STOP-002');

        $this->postJson('/api/internal/ocpp/transactions/stop', [
            'ocpp_identifier' => 'CP-STOP-002',
            'ocpp_transaction_id' => $session->ocpp_transaction_id,
            'meter_stop_wh' => 3500,
            'reason_code' => 'vehicle_disconnected',
        ], $this->bridgeHeaders());

        $response = $this->postJson('/api/internal/ocpp/transactions/stop', [
            'ocpp_identifier' => 'CP-STOP-002',
            'ocpp_transaction_id' => $session->ocpp_transaction_id,
            'meter_stop_wh' => 3500,
            'reason_code' => 'vehicle_disconnected',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Transaction already completed.']);
    }

    public function test_stop_transaction_for_unknown_transaction_acknowledges_without_error(): void
    {
        Log::spy();

        ChargingStation::factory()->create(['ocpp_identifier' => 'CP-STOP-003']);

        $response = $this->postJson('/api/internal/ocpp/transactions/stop', [
            'ocpp_identifier' => 'CP-STOP-003',
            'ocpp_transaction_id' => '999999',
            'meter_stop_wh' => 5000,
            'reason_code' => 'other',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('orphan_stop_transaction', \Mockery::on(function ($context) {
                return $context['ocpp_transaction_id'] === '999999';
            }));
    }

    public function test_stop_transaction_does_not_create_any_session_for_orphan_transaction(): void
    {
        ChargingStation::factory()->create(['ocpp_identifier' => 'CP-STOP-004']);

        $this->postJson('/api/internal/ocpp/transactions/stop', [
            'ocpp_identifier' => 'CP-STOP-004',
            'ocpp_transaction_id' => '999999',
            'meter_stop_wh' => 5000,
            'reason_code' => 'other',
        ], $this->bridgeHeaders());

        $this->assertSame(0, ChargingSession::count());
    }

    public function test_stop_transaction_for_unknown_station_returns_404(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/stop', [
            'ocpp_identifier' => 'CP-DOES-NOT-EXIST',
            'ocpp_transaction_id' => '1',
            'meter_stop_wh' => 5000,
            'reason_code' => 'other',
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_stop_transaction_rejects_invalid_reason_code(): void
    {
        $session = $this->createActiveSession('CP-STOP-005');

        $response = $this->postJson('/api/internal/ocpp/transactions/stop', [
            'ocpp_identifier' => 'CP-STOP-005',
            'ocpp_transaction_id' => $session->ocpp_transaction_id,
            'meter_stop_wh' => 3500,
            'reason_code' => 'not_a_real_reason',
        ], $this->bridgeHeaders());

        $response->assertStatus(422);
    }

    public function test_stop_transaction_requires_all_fields(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/stop', [], $this->bridgeHeaders());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ocpp_identifier', 'ocpp_transaction_id', 'meter_stop_wh', 'reason_code']);
    }

    public function test_stop_transaction_rejects_requests_without_bridge_token(): void
    {
        $response = $this->postJson('/api/internal/ocpp/transactions/stop', [
            'ocpp_identifier' => 'CP-STOP-006',
            'ocpp_transaction_id' => '1',
            'meter_stop_wh' => 5000,
            'reason_code' => 'other',
        ]);

        $response->assertStatus(401);
    }
}
