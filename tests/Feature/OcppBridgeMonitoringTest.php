<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use App\Models\Connector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OcppBridgeMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function bridgeHeaders(): array
    {
        return [
            'X-OCPP-Bridge-Token' => config('services.ocpp_bridge.token'),
        ];
    }

    public function test_heartbeat_updates_last_heartbeat_and_last_seen(): void
    {
        $station = ChargingStation::factory()->create([
            'ocpp_identifier' => 'CP-HEARTBEAT-001',
            'last_heartbeat_at' => null,
            'last_seen_at' => null,
        ]);

        $response = $this->postJson('/api/internal/ocpp/events/heartbeat', [
            'ocpp_identifier' => 'CP-HEARTBEAT-001',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);

        $station->refresh();
        $this->assertNotNull($station->last_heartbeat_at);
        $this->assertNotNull($station->last_seen_at);
    }

    public function test_heartbeat_for_unknown_station_returns_404(): void
    {
        $response = $this->postJson('/api/internal/ocpp/events/heartbeat', [
            'ocpp_identifier' => 'CP-DOES-NOT-EXIST',
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_heartbeat_requires_ocpp_identifier(): void
    {
        $response = $this->postJson('/api/internal/ocpp/events/heartbeat', [], $this->bridgeHeaders());

        $response->assertStatus(422);
    }

    public function test_boot_notification_updates_last_seen_but_not_last_heartbeat(): void
    {
        $station = ChargingStation::factory()->create([
            'ocpp_identifier' => 'CP-BOOT-001',
            'last_heartbeat_at' => null,
            'last_seen_at' => null,
        ]);

        $response = $this->postJson('/api/internal/ocpp/events/boot-notification', [
            'ocpp_identifier' => 'CP-BOOT-001',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);

        $station->refresh();
        $this->assertNotNull($station->last_seen_at);
        $this->assertNull($station->last_heartbeat_at);
    }

    public function test_boot_notification_for_unknown_station_returns_404(): void
    {
        $response = $this->postJson('/api/internal/ocpp/events/boot-notification', [
            'ocpp_identifier' => 'CP-DOES-NOT-EXIST',
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_station_level_status_notification_updates_station(): void
    {
        $station = ChargingStation::factory()->create([
            'ocpp_identifier' => 'CP-STATUS-001',
            'operational_status' => 'disconnected',
        ]);

        $response = $this->postJson('/api/internal/ocpp/events/status-notification', [
            'ocpp_identifier' => 'CP-STATUS-001',
            'operational_status' => 'available',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);

        $station->refresh();
        $this->assertSame('available', $station->operational_status);
    }

    public function test_station_level_status_notification_writes_ocpp_sourced_history(): void
    {
        $station = ChargingStation::factory()->create([
            'ocpp_identifier' => 'CP-STATUS-002',
            'operational_status' => 'disconnected',
        ]);

        $this->postJson('/api/internal/ocpp/events/status-notification', [
            'ocpp_identifier' => 'CP-STATUS-002',
            'operational_status' => 'fault',
        ], $this->bridgeHeaders());

        $history = $station->histories()->latest()->first();

        $this->assertNotNull($history);
        $this->assertSame('ocpp', $history->source);
        $this->assertNull($history->performed_by);
    }

    public function test_connector_level_status_notification_updates_connector_not_station(): void
    {
        $station = ChargingStation::factory()->create([
            'ocpp_identifier' => 'CP-STATUS-003',
            'operational_status' => 'disconnected',
        ]);

        $connector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'operational_status' => 'disconnected',
        ]);

        $response = $this->postJson('/api/internal/ocpp/events/status-notification', [
            'ocpp_identifier' => 'CP-STATUS-003',
            'connector_number' => 1,
            'operational_status' => 'available',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);

        $connector->refresh();
        $station->refresh();
        $this->assertSame('available', $connector->operational_status);
        $this->assertSame('disconnected', $station->operational_status);
    }

    public function test_status_notification_for_unknown_connector_returns_404(): void
    {
        $station = ChargingStation::factory()->create([
            'ocpp_identifier' => 'CP-STATUS-004',
        ]);

        $response = $this->postJson('/api/internal/ocpp/events/status-notification', [
            'ocpp_identifier' => 'CP-STATUS-004',
            'connector_number' => 999,
            'operational_status' => 'available',
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_status_notification_rejects_invalid_operational_status(): void
    {
        ChargingStation::factory()->create(['ocpp_identifier' => 'CP-STATUS-005']);

        $response = $this->postJson('/api/internal/ocpp/events/status-notification', [
            'ocpp_identifier' => 'CP-STATUS-005',
            'operational_status' => 'not_a_real_status',
        ], $this->bridgeHeaders());

        $response->assertStatus(422);
    }

    public function test_all_bridge_routes_reject_requests_without_token(): void
    {
        $this->postJson('/api/internal/ocpp/events/heartbeat', ['ocpp_identifier' => 'X'])
            ->assertStatus(401);

        $this->postJson('/api/internal/ocpp/events/boot-notification', ['ocpp_identifier' => 'X'])
            ->assertStatus(401);

        $this->postJson('/api/internal/ocpp/events/status-notification', ['ocpp_identifier' => 'X', 'operational_status' => 'available'])
            ->assertStatus(401);
    }
}
