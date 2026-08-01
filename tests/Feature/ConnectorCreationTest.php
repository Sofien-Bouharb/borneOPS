<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorCreationTest extends TestCase
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

    private function authenticatedAsFullAccessUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Administrator');

        $this->actingAs($user, 'api');

        return $user;
    }

    public function test_valid_creation_succeeds_with_forced_defaults(): void
    {
        $station = $this->createStationInCommissioning();
        $this->authenticatedAsFullAccessUser();

        $response = $this->postJson("/api/charging-stations/{$station->id}/connectors", [
            'connector_number' => 1,
            'standard' => 'ccs',
            'max_power_kw' => 10,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.standard', 'ccs');
        $response->assertJsonPath('data.current_type', 'dc');
        $response->assertJsonPath('data.operational_status', 'disconnected');
        $response->assertJsonPath('data.administrative_status', 'enabled');
    }

    public function test_connector_number_is_required(): void
    {
        $station = $this->createStationInCommissioning();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-stations/{$station->id}/connectors", [
            'standard' => 'ccs',
            'max_power_kw' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['connector_number']);
    }

    public function test_standard_is_required(): void
    {
        $station = $this->createStationInCommissioning();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-stations/{$station->id}/connectors", [
            'connector_number' => 1,
            'max_power_kw' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['standard']);
    }

    public function test_standard_must_be_a_valid_enum_value(): void
    {
        $station = $this->createStationInCommissioning();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-stations/{$station->id}/connectors", [
            'connector_number' => 1,
            'standard' => 'not-a-real-standard',
            'max_power_kw' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['standard']);
    }

    public function test_max_power_kw_is_required(): void
    {
        $station = $this->createStationInCommissioning();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-stations/{$station->id}/connectors", [
            'connector_number' => 1,
            'standard' => 'ccs',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_power_kw']);
    }

    public function test_max_power_kw_must_be_greater_than_zero(): void
    {
        $station = $this->createStationInCommissioning();
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-stations/{$station->id}/connectors", [
            'connector_number' => 1,
            'standard' => 'ccs',
            'max_power_kw' => 0,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_power_kw']);
    }

    public function test_max_power_kw_cannot_exceed_station_power(): void
    {
        $station = $this->createStationInCommissioning(['power_kw' => 10]);
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-stations/{$station->id}/connectors", [
            'connector_number' => 1,
            'standard' => 'ccs',
            'max_power_kw' => 20,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_power_kw']);
    }

    public function test_connector_number_must_be_unique_within_the_same_station(): void
    {
        $station = $this->createStationInCommissioning();
        Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
        ]);
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-stations/{$station->id}/connectors", [
            'connector_number' => 1,
            'standard' => 'type2',
            'max_power_kw' => 10,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['connector_number']);
    }

    public function test_connector_number_can_repeat_across_different_stations(): void
    {
        $stationA = $this->createStationInCommissioning();
        $stationB = $this->createStationInCommissioning();
        Connector::factory()->create([
            'charging_station_id' => $stationA->id,
            'connector_number' => 1,
        ]);
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-stations/{$stationB->id}/connectors", [
            'connector_number' => 1,
            'standard' => 'type2',
            'max_power_kw' => 10,
        ])
            ->assertStatus(201);
    }

    public function test_creation_is_rejected_when_station_is_not_commissioning(): void
    {
        $station = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $this->authenticatedAsFullAccessUser();

        $this->postJson("/api/charging-stations/{$station->id}/connectors", [
            'connector_number' => 1,
            'standard' => 'ccs',
            'max_power_kw' => 10,
        ])
            ->assertStatus(409);
    }

    public function test_client_submitted_current_type_is_ignored_and_always_derived(): void
    {
        $station = $this->createStationInCommissioning();
        $this->authenticatedAsFullAccessUser();

        $response = $this->postJson("/api/charging-stations/{$station->id}/connectors", [
            'connector_number' => 1,
            'standard' => 'ccs',
            'max_power_kw' => 10,
            'current_type' => 'ac',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.current_type', 'dc');
    }

    public function test_client_submitted_status_fields_are_ignored_on_creation(): void
    {
        $station = $this->createStationInCommissioning();
        $this->authenticatedAsFullAccessUser();

        $response = $this->postJson("/api/charging-stations/{$station->id}/connectors", [
            'connector_number' => 1,
            'standard' => 'type2',
            'max_power_kw' => 10,
            'operational_status' => 'available',
            'administrative_status' => 'disabled',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.operational_status', 'disconnected');
        $response->assertJsonPath('data.administrative_status', 'enabled');
    }
}
