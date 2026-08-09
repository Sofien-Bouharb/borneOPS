<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingSessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function authenticatedAsFullAccessUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Administrator');

        $this->actingAs($user, 'api');

        return $user;
    }

    private function eligibleStationAndConnector(): array
    {
        $station = ChargingStation::factory()->create([
            'administrative_status' => 'active',
            'operational_status' => 'available',
            'last_heartbeat_at' => now(),
        ]);

        $connector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'administrative_status' => 'enabled',
            'operational_status' => 'available',
        ]);

        return [$station, $connector];
    }

    private function pendingSession(): array
    {
        [$station, $connector] = $this->eligibleStationAndConnector();

        $session = ChargingSession::factory()->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'status' => 'pending',
        ]);

        return [$session, $station, $connector];
    }

    public function test_start_succeeds_and_occupies_connector_and_station(): void
    {
        [$session, $station, $connector] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-sessions/{$session->id}/start", ['meter_start_wh' => 1000])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.meter_start_wh', 1000);

        $this->assertEquals('occupied', $connector->fresh()->operational_status);
        $this->assertEquals('occupied', $station->fresh()->operational_status);
    }

    public function test_start_requires_meter_start_wh(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-sessions/{$session->id}/start", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['meter_start_wh']);
    }

    public function test_start_rejected_when_not_pending(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();
        $this->postJson("/api/charging-sessions/{$session->id}/start", ['meter_start_wh' => 1000]);

        $this->postJson("/api/charging-sessions/{$session->id}/start", ['meter_start_wh' => 2000])
            ->assertStatus(409);
    }

    public function test_pause_succeeds_from_active(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();
        $this->postJson("/api/charging-sessions/{$session->id}/start", ['meter_start_wh' => 1000]);

        $this->postJson("/api/charging-sessions/{$session->id}/pause")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'paused');
    }

    public function test_pause_rejected_when_not_active(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-sessions/{$session->id}/pause")
            ->assertStatus(409);
    }

    public function test_resume_succeeds_from_paused(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();
        $this->postJson("/api/charging-sessions/{$session->id}/start", ['meter_start_wh' => 1000]);
        $this->postJson("/api/charging-sessions/{$session->id}/pause");

        $this->postJson("/api/charging-sessions/{$session->id}/resume")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_resume_rejected_when_not_paused(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-sessions/{$session->id}/resume")
            ->assertStatus(409);
    }

    public function test_end_succeeds_and_releases_connector_and_station(): void
    {
        [$session, $station, $connector] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();
        $this->postJson("/api/charging-sessions/{$session->id}/start", ['meter_start_wh' => 1000]);

        $this->postJson("/api/charging-sessions/{$session->id}/end", [
            'meter_stop_wh' => 5000,
            'reason_code' => 'user_requested',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.energy_consumed_wh', 4000);

        $this->assertEquals('available', $connector->fresh()->operational_status);
        $this->assertEquals('available', $station->fresh()->operational_status);
    }

    public function test_end_rejects_equipment_unavailable_reason(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();
        $this->postJson("/api/charging-sessions/{$session->id}/start", ['meter_start_wh' => 1000]);

        $this->postJson("/api/charging-sessions/{$session->id}/end", [
            'meter_stop_wh' => 5000,
            'reason_code' => 'equipment_unavailable',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason_code']);
    }

    public function test_end_rejected_when_pending(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-sessions/{$session->id}/end", [
            'meter_stop_wh' => 5000,
            'reason_code' => 'user_requested',
        ])->assertStatus(409);
    }

    public function test_cancel_succeeds_from_pending(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-sessions/{$session->id}/cancel", [
            'reason_code' => 'user_requested',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancel_accepts_equipment_unavailable_reason(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-sessions/{$session->id}/cancel", [
            'reason_code' => 'equipment_unavailable',
        ])->assertStatus(200);
    }

    public function test_cancel_rejected_when_not_pending(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();
        $this->postJson("/api/charging-sessions/{$session->id}/start", ['meter_start_wh' => 1000]);

        $this->postJson("/api/charging-sessions/{$session->id}/cancel", [
            'reason_code' => 'user_requested',
        ])->assertStatus(409);
    }

    public function test_cancel_rejects_invalid_reason_code(): void
    {
        [$session] = $this->pendingSession();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-sessions/{$session->id}/cancel", [
            'reason_code' => 'remote_stop',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason_code']);
    }

    public function test_station_stays_occupied_when_another_connector_still_has_open_session(): void
    {
        $station = ChargingStation::factory()->create([
            'administrative_status' => 'active',
            'operational_status' => 'available',
            'last_heartbeat_at' => now(),
        ]);
        $connectorA = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'administrative_status' => 'enabled',
            'operational_status' => 'available',
        ]);
        $connectorB = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 2,
            'administrative_status' => 'enabled',
            'operational_status' => 'available',
        ]);
        $sessionA = ChargingSession::factory()->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connectorA->id,
            'status' => 'pending',
        ]);
        $sessionB = ChargingSession::factory()->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connectorB->id,
            'status' => 'pending',
        ]);
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-sessions/{$sessionA->id}/start", ['meter_start_wh' => 1000]);
        $this->postJson("/api/charging-sessions/{$sessionB->id}/start", ['meter_start_wh' => 1000]);

        $this->postJson("/api/charging-sessions/{$sessionA->id}/end", [
            'meter_stop_wh' => 2000,
            'reason_code' => 'user_requested',
        ])->assertStatus(200);

        $this->assertEquals('occupied', $station->fresh()->operational_status);
    }
}
