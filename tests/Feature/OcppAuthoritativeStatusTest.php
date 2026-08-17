<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use App\Services\ChargingStationService;
use App\Services\ConnectorService;
use App\Services\StationMonitoringService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OcppAuthoritativeStatusTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Administrator');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function connectedStation(array $overrides = []): ChargingStation
    {
        return ChargingStation::factory()->create(array_merge([
            'administrative_status' => 'active',
            'ocpp_identifier' => 'OCPP-GATE-' . uniqid(),
            'last_heartbeat_at' => now(),
        ], $overrides));
    }

    private function disconnectedStation(array $overrides = []): ChargingStation
    {
        return ChargingStation::factory()->create(array_merge([
            'administrative_status' => 'active',
            'ocpp_identifier' => 'OCPP-GATE-' . uniqid(),
            'last_heartbeat_at' => null,
        ], $overrides));
    }

    // --- Station-level HTTP gate (controller always sends source='user') ---

    public function test_manual_station_operational_status_update_rejected_when_connected(): void
    {
        $station = $this->connectedStation();

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/state", [
                'operational_status' => 'available',
                'reason' => 'manual override attempt',
            ])
            ->assertStatus(409);
    }

    public function test_manual_station_operational_status_update_allowed_when_disconnected(): void
    {
        $station = $this->disconnectedStation();

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/state", [
                'operational_status' => 'maintenance',
                'reason' => 'manual maintenance',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.operational_status', 'maintenance');
    }

    public function test_heartbeat_exactly_at_timeout_cutoff_still_counts_as_connected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00'));

        $timeoutSeconds = (int) config('monitoring.heartbeat_timeout_seconds');

        $station = $this->connectedStation([
            'last_heartbeat_at' => now()->subSeconds($timeoutSeconds),
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/state", [
                'operational_status' => 'available',
                'reason' => 'boundary test',
            ])
            ->assertStatus(409);
    }

    public function test_heartbeat_one_second_past_timeout_cutoff_counts_as_disconnected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00'));

        $timeoutSeconds = (int) config('monitoring.heartbeat_timeout_seconds');

        $station = $this->connectedStation([
            'last_heartbeat_at' => now()->subSeconds($timeoutSeconds + 1),
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/state", [
                'operational_status' => 'available',
                'reason' => 'boundary test',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.operational_status', 'available');
    }

    // --- Station-level service gate for non-user sources ---

    public function test_ocpp_source_bypasses_station_gate_even_when_connected(): void
    {
        $station = $this->connectedStation();
        $service = app(ChargingStationService::class);

        $updated = $service->updateOperationalStatus(
            $station,
            'available',
            'status notification',
            null,
            null,
            'ocpp'
        );

        $this->assertSame('available', $updated->operational_status);
    }

    public function test_system_source_bypasses_station_gate_even_when_connected(): void
    {
        $station = $this->connectedStation();
        $service = app(ChargingStationService::class);

        $updated = $service->updateOperationalStatus(
            $station,
            'maintenance',
            'system-triggered change',
            null,
            null,
            'system'
        );

        $this->assertSame('maintenance', $updated->operational_status);
    }

    // --- Connector-level HTTP gate (controller always sends source='user') ---

    public function test_manual_connector_state_update_rejected_when_parent_station_connected(): void
    {
        $station = $this->connectedStation();
        $connector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'operational_status' => 'disconnected',
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson(
                "/api/charging-stations/{$station->id}/connectors/{$connector->id}/state",
                ['operational_status' => 'available']
            )
            ->assertStatus(409);
    }

    public function test_manual_connector_state_update_allowed_when_parent_station_disconnected(): void
    {
        $station = $this->disconnectedStation();
        $connector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'operational_status' => 'disconnected',
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson(
                "/api/charging-stations/{$station->id}/connectors/{$connector->id}/state",
                ['operational_status' => 'available']
            )
            ->assertStatus(200)
            ->assertJsonPath('data.operational_status', 'available');
    }

    /**
     * The connector's own operational_status is irrelevant to the gate —
     * only the parent station's connection_status matters. Two connectors
     * with different current states, same connected parent station, both
     * manual writes rejected identically.
     */
    public function test_connectors_own_operational_status_is_irrelevant_to_the_gate(): void
    {
        $station = $this->connectedStation();

        $availableConnector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'operational_status' => 'available',
        ]);

        $faultConnector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 2,
            'operational_status' => 'fault',
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson(
                "/api/charging-stations/{$station->id}/connectors/{$availableConnector->id}/state",
                ['operational_status' => 'occupied']
            )
            ->assertStatus(409);

        $this->actingAs($this->admin, 'api')
            ->patchJson(
                "/api/charging-stations/{$station->id}/connectors/{$faultConnector->id}/state",
                ['operational_status' => 'occupied']
            )
            ->assertStatus(409);
    }

    // --- Connector-level service gate for non-user sources ---

    public function test_ocpp_source_bypasses_connector_gate_even_when_parent_station_connected(): void
    {
        $station = $this->connectedStation();
        $connector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'operational_status' => 'disconnected',
        ]);
        $service = app(ConnectorService::class);

        $updated = $service->updateOperationalStatus($connector, 'available', 'ocpp');

        $this->assertSame('available', $updated->operational_status);
    }

    public function test_system_source_bypasses_connector_gate_even_when_parent_station_connected(): void
    {
        $station = $this->connectedStation();
        $connector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'operational_status' => 'disconnected',
        ]);
        $service = app(ConnectorService::class);

        $updated = $service->updateOperationalStatus($connector, 'occupied', 'system');

        $this->assertSame('occupied', $updated->operational_status);
    }

    /**
     * End-to-end confirmation that StationMonitoringService's monitoring
     * path (used by real OCPP StatusNotification traffic) always passes
     * source: 'ocpp' internally and is therefore never blocked by the gate,
     * regardless of the parent station's connection_status.
     */
    public function test_monitoring_service_connector_status_recording_bypasses_gate_when_connected(): void
    {
        $station = $this->connectedStation();
        $connector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'operational_status' => 'disconnected',
        ]);
        $monitoringService = app(StationMonitoringService::class);

        $updated = $monitoringService->recordConnectorOperationalStatus($connector, 'occupied');

        $this->assertSame('occupied', $updated->operational_status);
    }
}
