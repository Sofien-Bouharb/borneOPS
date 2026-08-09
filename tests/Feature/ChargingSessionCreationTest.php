<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingSessionCreationTest extends TestCase
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

    private function eligibleStationAndConnector(array $stationOverrides = [], array $connectorOverrides = []): array
    {
        $station = ChargingStation::factory()->create(array_merge([
            'administrative_status' => 'active',
            'operational_status' => 'available',
            'last_heartbeat_at' => now(),
        ], $stationOverrides));

        $connector = Connector::factory()->create(array_merge([
            'charging_station_id' => $station->id,
            'administrative_status' => 'enabled',
            'operational_status' => 'available',
        ], $connectorOverrides));

        return [$station, $connector];
    }

    public function test_valid_creation_succeeds(): void
    {
        [$station, $connector] = $this->eligibleStationAndConnector();
        $this->authenticatedAsFullAccessUser();

        $this->postJson('/api/charging-sessions', [
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_charging_station_id_is_required(): void
    {
        $this->authenticatedAsFullAccessUser();

        $this->postJson('/api/charging-sessions', ['connector_id' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['charging_station_id']);
    }

    public function test_connector_id_is_required(): void
    {
        $this->authenticatedAsFullAccessUser();

        $this->postJson('/api/charging-sessions', ['charging_station_id' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['connector_id']);
    }

    public function test_creation_rejected_when_station_not_active(): void
    {
        [$station, $connector] = $this->eligibleStationAndConnector(['administrative_status' => 'commissioning']);
        $this->authenticatedAsFullAccessUser();

        $this->postJson('/api/charging-sessions', [
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
        ])->assertStatus(409);
    }

    public function test_creation_rejected_when_station_not_connected(): void
    {
        [$station, $connector] = $this->eligibleStationAndConnector(['last_heartbeat_at' => null]);
        $this->authenticatedAsFullAccessUser();

        $this->postJson('/api/charging-sessions', [
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
        ])->assertStatus(409);
    }

    public function test_creation_rejected_when_connector_not_enabled(): void
    {
        [$station, $connector] = $this->eligibleStationAndConnector([], ['administrative_status' => 'disabled']);
        $this->authenticatedAsFullAccessUser();

        $this->postJson('/api/charging-sessions', [
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
        ])->assertStatus(409);
    }

    public function test_creation_rejected_when_connector_not_available(): void
    {
        [$station, $connector] = $this->eligibleStationAndConnector([], ['operational_status' => 'out_of_service']);
        $this->authenticatedAsFullAccessUser();

        $this->postJson('/api/charging-sessions', [
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
        ])->assertStatus(409);
    }

    public function test_creation_rejected_when_connector_already_has_open_session(): void
    {
        [$station, $connector] = $this->eligibleStationAndConnector();
        ChargingSession::factory()->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'status' => 'pending',
        ]);
        $this->authenticatedAsFullAccessUser();

        $this->postJson('/api/charging-sessions', [
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
        ])->assertStatus(409);
    }
}
