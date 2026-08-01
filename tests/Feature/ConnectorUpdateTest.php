<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorUpdateTest extends TestCase
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

    public function test_partial_update_of_max_power_kw_only_leaves_other_fields_untouched(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station, [
            'connector_number' => 1,
            'standard' => 'type2',
            'current_type' => 'ac',
            'max_power_kw' => 11,
        ]);
        $this->authenticatedAsFullAccessUser();

        $response = $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}",
            ['max_power_kw' => 22]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.max_power_kw', '22.00');
        $response->assertJsonPath('data.standard', 'type2');
    }

    public function test_updating_standard_re_derives_current_type(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station, [
            'connector_number' => 1,
            'standard' => 'type2',
            'current_type' => 'ac',
            'max_power_kw' => 11,
        ]);
        $this->authenticatedAsFullAccessUser();

        $response = $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}",
            ['standard' => 'ccs']
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.standard', 'ccs');
        $response->assertJsonPath('data.current_type', 'dc');
    }

    public function test_update_is_rejected_when_station_is_not_commissioning(): void
    {
        $station = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}",
            ['max_power_kw' => 30]
        )->assertStatus(409);
    }

    public function test_resubmitting_the_connectors_own_unchanged_connector_number_succeeds(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station, ['connector_number' => 3]);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}",
            ['connector_number' => 3]
        )->assertStatus(200);
    }

    public function test_connector_number_cannot_be_changed_to_one_already_used_on_the_same_station(): void
    {
        $station = $this->createStationInCommissioning();
        $this->createConnectorFor($station, ['connector_number' => 1]);
        $connector = $this->createConnectorFor($station, ['connector_number' => 2]);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}",
            ['connector_number' => 1]
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['connector_number']);
    }

    public function test_connector_number_can_be_changed_to_one_used_on_a_different_station(): void
    {
        $stationA = $this->createStationInCommissioning();
        $stationB = $this->createStationInCommissioning();
        $this->createConnectorFor($stationA, ['connector_number' => 1]);
        $connector = $this->createConnectorFor($stationB, ['connector_number' => 2]);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$stationB->id}/connectors/{$connector->id}",
            ['connector_number' => 1]
        )->assertStatus(200);
    }

    public function test_no_op_update_does_not_write_to_the_database(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station, [
            'connector_number' => 1,
            'standard' => 'ccs',
            'current_type' => 'dc',
            'max_power_kw' => 11,
        ]);
        $originalUpdatedAt = $connector->updated_at;
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}",
            ['standard' => 'ccs']
        )->assertStatus(200);

        $this->assertTrue($originalUpdatedAt->eq($connector->fresh()->updated_at));
    }

    public function test_max_power_kw_cannot_be_updated_to_exceed_station_power(): void
    {
        $station = $this->createStationInCommissioning(['power_kw' => 10]);
        $connector = $this->createConnectorFor($station, ['max_power_kw' => 7]);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}",
            ['max_power_kw' => 20]
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_power_kw']);
    }

    public function test_standard_must_be_a_valid_enum_value_on_update(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station);
        $this->authenticatedAsFullAccessUser();

        $this->patchJson(
            "/api/charging-stations/{$station->id}/connectors/{$connector->id}",
            ['standard' => 'not-a-real-standard']
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['standard']);
    }
}
