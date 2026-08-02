<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createStationInCommissioning(array $overrides = []): ChargingStation
    {
        return ChargingStation::factory()->create(array_merge([
            'administrative_status' => 'commissioning',
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

    public function test_show_returns_404_when_connector_belongs_to_a_different_station(): void
    {
        $stationA = $this->createStationInCommissioning();
        $stationB = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($stationB);
        $this->authenticatedAsFullAccessUser();

        $this->getJson("/api/charging-stations/{$stationA->id}/connectors/{$connector->id}")
            ->assertStatus(404);
    }

    public function test_update_returns_404_when_connector_belongs_to_a_different_station(): void
    {
        $stationA = $this->createStationInCommissioning();
        $stationB = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($stationB);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$stationA->id}/connectors/{$connector->id}",
            ['max_power_kw' => 5]
        )->assertStatus(404);
    }

    public function test_destroy_returns_404_when_connector_belongs_to_a_different_station(): void
    {
        $stationA = $this->createStationInCommissioning();
        $stationB = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($stationB);
        $this->authenticatedAsFullAccessUser();

        $this->deleteJson("/api/charging-stations/{$stationA->id}/connectors/{$connector->id}")
            ->assertStatus(404);
    }

    public function test_state_update_returns_404_when_connector_belongs_to_a_different_station(): void
    {
        $stationA = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $stationB = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($stationB);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$stationA->id}/connectors/{$connector->id}/state",
            ['operational_status' => 'available']
        )->assertStatus(404);
    }

    public function test_availability_update_returns_404_when_connector_belongs_to_a_different_station(): void
    {
        $stationA = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $stationB = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($stationB, ['administrative_status' => 'enabled']);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$stationA->id}/connectors/{$connector->id}/availability",
            ['administrative_status' => 'disabled']
        )->assertStatus(404);
    }

    public function test_delete_succeeds_while_station_is_commissioning(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $this->deleteJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}")
            ->assertStatus(204);
    }

    public function test_delete_is_rejected_when_station_is_not_commissioning(): void
    {
        $station = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $this->deleteJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}")
            ->assertStatus(409);
    }

    public function test_deleted_connector_is_soft_deleted_not_destroyed(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $this->deleteJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}")
            ->assertStatus(204);

        $trashed = Connector::withTrashed()->find($connector->id);

        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);
    }

    public function test_deleted_connector_is_no_longer_accessible_via_show(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $this->deleteJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}")
            ->assertStatus(204);

        $this->getJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}")
            ->assertStatus(404);
    }
}
