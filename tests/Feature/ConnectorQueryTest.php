<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorQueryTest extends TestCase
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

    public function test_index_only_returns_connectors_for_the_given_station(): void
    {
        $stationA = $this->createStationInCommissioning();
        $stationB = $this->createStationInCommissioning();
        $this->createConnectorFor($stationA, ['connector_number' => 1]);
        $this->createConnectorFor($stationA, ['connector_number' => 2]);
        $this->createConnectorFor($stationB, ['connector_number' => 1]);
        $this->authenticatedAsFullAccessUser();

        $response = $this->getJson("/api/charging-stations/{$stationA->id}/connectors");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('charging_station_id')->unique();

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals([$stationA->id], $ids->values()->all());
    }

    public function test_index_excludes_soft_deleted_connectors(): void
    {
        $station = $this->createStationInCommissioning();
        $activeConnector = $this->createConnectorFor($station, ['connector_number' => 1]);
        $deletedConnector = $this->createConnectorFor($station, ['connector_number' => 2]);
        $deletedConnector->delete();
        $this->authenticatedAsFullAccessUser();

        $response = $this->getJson("/api/charging-stations/{$station->id}/connectors");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($ids->contains($activeConnector->id));
        $this->assertFalse($ids->contains($deletedConnector->id));
    }

    public function test_index_includes_nested_charging_station_block(): void
    {
        $station = $this->createStationInCommissioning();
        $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $response = $this->getJson("/api/charging-stations/{$station->id}/connectors");

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.charging_station.id', $station->id);
    }

    public function test_show_includes_nested_charging_station_block(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $response = $this->getJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.charging_station.id', $station->id);
    }

    public function test_state_update_response_does_not_include_nested_charging_station_block(): void
    {
        $station = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $response = $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/state",
            ['operational_status' => 'available']
        );

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('charging_station', $response->json('data'));
    }

    public function test_availability_update_response_does_not_include_nested_charging_station_block(): void
    {
        $station = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station, ['administrative_status' => 'enabled']);
        $this->authenticatedAsFullAccessUser();

        $response = $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}/availability",
            ['administrative_status' => 'disabled']
        );

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('charging_station', $response->json('data'));
    }

    public function test_station_show_returns_actual_connector_count_and_matches_true(): void
    {
        $station = $this->createStationInCommissioning(['declared_connector_count' => 2]);
        $this->createConnectorFor($station, ['connector_number' => 1]);
        $this->createConnectorFor($station, ['connector_number' => 2]);
        $this->authenticatedAsFullAccessUser();

        $response = $this->getJson("/api/charging-stations/{$station->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.actual_connector_count', 2);
        $response->assertJsonPath('data.connector_count_matches', true);
    }

    public function test_station_show_returns_connector_count_matches_false_when_mismatched(): void
    {
        $station = $this->createStationInCommissioning(['declared_connector_count' => 3]);
        $this->createConnectorFor($station, ['connector_number' => 1]);
        $this->createConnectorFor($station, ['connector_number' => 2]);
        $this->authenticatedAsFullAccessUser();

        $response = $this->getJson("/api/charging-stations/{$station->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.actual_connector_count', 2);
        $response->assertJsonPath('data.connector_count_matches', false);
    }

    public function test_station_show_returns_full_connectors_array(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $response = $this->getJson("/api/charging-stations/{$station->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.connectors.0.id', $connector->id);
    }

    public function test_station_index_returns_connector_count_but_not_full_connectors_array(): void
    {
        $station = $this->createStationInCommissioning();
        $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $response = $this->getJson('/api/charging-stations');

        $response->assertStatus(200);
        $matched = collect($response->json('data'))->firstWhere('id', $station->id);

        $this->assertNotNull($matched);
        $this->assertArrayHasKey('actual_connector_count', $matched);
        $this->assertArrayNotHasKey('connectors', $matched);
    }
}
