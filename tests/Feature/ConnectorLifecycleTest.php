<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createStation(array $overrides = []): ChargingStation
    {
        return ChargingStation::factory()->create(array_merge([
            'administrative_status' => 'active',
            'power_kw' => 50,
        ], $overrides));
    }

    private function createConnectorFor(ChargingStation $station, array $overrides = []): Connector
    {
        return Connector::factory()->create(array_merge([
            'charging_station_id' => $station->id,
        ], $overrides));
    }

    private function authenticatedAsFullAccessUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Administrator');

        $this->actingAs($user, 'api');

        return $user;
    }

    public function test_operational_status_can_be_updated_while_station_is_active(): void
    {
        $station = $this->createStation(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station, ['operational_status' => 'disconnected']);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/state",
            ['operational_status' => 'available']
        )
            ->assertStatus(200)
            ->assertJsonPath('data.operational_status', 'available');
    }

    public function test_operational_status_can_be_updated_while_station_is_disabled(): void
    {
        $station = $this->createStation(['administrative_status' => 'disabled']);
        $connector = $this->createConnectorFor($station, ['operational_status' => 'disconnected']);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/state",
            ['operational_status' => 'maintenance']
        )
            ->assertStatus(200)
            ->assertJsonPath('data.operational_status', 'maintenance');
    }

    public function test_operational_status_update_is_blocked_while_station_is_decommissioned(): void
    {
        $station = $this->createStation(['administrative_status' => 'decommissioned']);
        $connector = $this->createConnectorFor($station, ['operational_status' => 'disconnected']);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/state",
            ['operational_status' => 'available']
        )->assertStatus(409);
    }

    public function test_resubmitting_the_same_operational_status_is_a_no_op(): void
    {
        $station = $this->createStation(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station, ['operational_status' => 'available']);
        $originalUpdatedAt = $connector->updated_at;
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/state",
            ['operational_status' => 'available']
        )->assertStatus(200);

        $this->assertTrue($originalUpdatedAt->eq($connector->fresh()->updated_at));
    }

    public function test_operational_status_must_be_a_valid_enum_value(): void
    {
        $station = $this->createStation();
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/state",
            ['operational_status' => 'not-a-real-status']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['operational_status']);
    }

    public function test_operational_status_is_required(): void
    {
        $station = $this->createStation();
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/state",
            []
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['operational_status']);
    }

    public function test_availability_can_be_updated_while_station_is_active(): void
    {
        $station = $this->createStation(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station, ['administrative_status' => 'enabled']);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/availability",
            ['administrative_status' => 'disabled']
        )
            ->assertStatus(200)
            ->assertJsonPath('data.administrative_status', 'disabled');
    }

    public function test_availability_can_be_updated_while_station_is_disabled(): void
    {
        $station = $this->createStation(['administrative_status' => 'disabled']);
        $connector = $this->createConnectorFor($station, ['administrative_status' => 'disabled']);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/availability",
            ['administrative_status' => 'enabled']
        )
            ->assertStatus(200)
            ->assertJsonPath('data.administrative_status', 'enabled');
    }

    public function test_availability_update_is_blocked_while_station_is_decommissioned(): void
    {
        $station = $this->createStation(['administrative_status' => 'decommissioned']);
        $connector = $this->createConnectorFor($station, ['administrative_status' => 'enabled']);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/availability",
            ['administrative_status' => 'disabled']
        )->assertStatus(409);
    }

    /**
     * Unlike operational_status, resubmitting the connector's current
     * administrative_status is NOT a silent no-op — it returns 409, per the
     * roadmap's explicit rule.
     */
    public function test_resubmitting_the_same_administrative_status_returns_409(): void
    {
        $station = $this->createStation(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station, ['administrative_status' => 'enabled']);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/availability",
            ['administrative_status' => 'enabled']
        )->assertStatus(409);
    }

    public function test_administrative_status_must_be_a_valid_enum_value(): void
    {
        $station = $this->createStation();
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/availability",
            ['administrative_status' => 'not-a-real-status']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['administrative_status']);
    }

    public function test_administrative_status_is_required(): void
    {
        $station = $this->createStation();
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/availability",
            []
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['administrative_status']);
    }
}
