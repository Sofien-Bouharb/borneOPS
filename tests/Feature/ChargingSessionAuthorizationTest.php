<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingSessionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actingAsRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

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

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/charging-sessions')->assertStatus(401);
    }

    public function test_client_role_cannot_view_sessions(): void
    {
        $this->actingAsRole('Client');

        $this->getJson('/api/charging-sessions')->assertStatus(403);
    }

    public function test_technicien_can_view_but_not_create(): void
    {
        [$station, $connector] = $this->eligibleStationAndConnector();
        $this->actingAsRole('Technicien');

        $this->getJson('/api/charging-sessions')->assertStatus(200);

        $this->postJson('/api/charging-sessions', [
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
        ])->assertStatus(403);
    }

    public function test_technicien_cannot_cancel(): void
    {
        [$station, $connector] = $this->eligibleStationAndConnector();
        $session = ChargingSession::factory()->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'status' => 'pending',
        ]);
        $this->actingAsRole('Technicien');

        $this->postJson("/api/charging-sessions/{$session->id}/cancel", [
            'reason_code' => 'user_requested',
        ])->assertStatus(403);
    }

    public function test_operateur_has_full_access(): void
    {
        [$station, $connector] = $this->eligibleStationAndConnector();
        $this->actingAsRole('Opérateur');

        $this->postJson('/api/charging-sessions', [
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
        ])->assertStatus(201);
    }

    public function test_finance_is_view_only(): void
    {
        [$station, $connector] = $this->eligibleStationAndConnector();
        $this->actingAsRole('Finance');

        $this->getJson('/api/charging-sessions')->assertStatus(200);

        $this->postJson('/api/charging-sessions', [
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
        ])->assertStatus(403);
    }
}
