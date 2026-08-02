<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Creates a charging station in 'commissioning', which is required for
     * create/update/delete endpoints to succeed at the service layer.
     */
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

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $station = $this->createStationInCommissioning();

        $this->getJson("/api/charging-stations/{$station->id}/connectors")
            ->assertStatus(401);
    }

    public function test_client_role_is_forbidden_from_viewing_connectors(): void
    {
        $station = $this->createStationInCommissioning();
        $user = $this->userWithRole('Client');

        $this->actingAs($user, 'api')
            ->getJson("/api/charging-stations/{$station->id}/connectors")
            ->assertStatus(403);
    }

    public function test_client_role_is_forbidden_from_creating_connector(): void
    {
        $station = $this->createStationInCommissioning();
        $user = $this->userWithRole('Client');

        $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/connectors", [
                'connector_number' => 1,
                'standard' => 'type2',
                'max_power_kw' => 10,
            ])
            ->assertStatus(403);
    }

    public function test_super_administrator_can_view_connectors(): void
    {
        $station = $this->createStationInCommissioning();
        $this->createConnectorFor($station);
        $user = $this->userWithRole('Super Administrator');

        $this->actingAs($user, 'api')
            ->getJson("/api/charging-stations/{$station->id}/connectors")
            ->assertStatus(200);
    }

    public function test_super_administrator_can_create_connector(): void
    {
        $station = $this->createStationInCommissioning();
        $user = $this->userWithRole('Super Administrator');

        $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/connectors", [
                'connector_number' => 1,
                'standard' => 'type2',
                'max_power_kw' => 10,
            ])
            ->assertStatus(201);
    }

    public function test_exploitant_can_delete_connector(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station);
        $user = $this->userWithRole('Exploitant');

        $this->actingAs($user, 'api')
            ->deleteJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}")
            ->assertStatus(204);
    }

    public function test_operateur_can_view_connectors(): void
    {
        $station = $this->createStationInCommissioning();
        $this->createConnectorFor($station);
        $user = $this->userWithRole('Opérateur');

        $this->actingAs($user, 'api')
            ->getJson("/api/charging-stations/{$station->id}/connectors")
            ->assertStatus(200);
    }

    public function test_operateur_can_update_connector_state(): void
    {
        $station = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station);
        $user = $this->userWithRole('Opérateur');

        $this->actingAs($user, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}/state", [
                'operational_status' => 'available',
            ])
            ->assertStatus(200);
    }

    public function test_operateur_is_forbidden_from_creating_connector(): void
    {
        $station = $this->createStationInCommissioning();
        $user = $this->userWithRole('Opérateur');

        $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/connectors", [
                'connector_number' => 1,
                'standard' => 'type2',
                'max_power_kw' => 10,
            ])
            ->assertStatus(403);
    }

    /**
     * Deliberate asymmetry documented in the Module 3 roadmap: Opérateur has
     * connectors.state.update but NOT connectors.availability.update.
     */
    public function test_operateur_is_forbidden_from_updating_connector_availability(): void
    {
        $station = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station);
        $user = $this->userWithRole('Opérateur');

        $this->actingAs($user, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}/availability", [
                'administrative_status' => 'disabled',
            ])
            ->assertStatus(403);
    }

    public function test_technicien_can_update_connector_state(): void
    {
        $station = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station);
        $user = $this->userWithRole('Technicien');

        $this->actingAs($user, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}/state", [
                'operational_status' => 'available',
            ])
            ->assertStatus(200);
    }

    public function test_technicien_is_forbidden_from_deleting_connector(): void
    {
        $station = $this->createStationInCommissioning();
        $connector = $this->createConnectorFor($station);
        $user = $this->userWithRole('Technicien');

        $this->actingAs($user, 'api')
            ->deleteJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}")
            ->assertStatus(403);
    }

    public function test_service_client_is_forbidden_from_updating_connector_state(): void
    {
        $station = $this->createStationInCommissioning(['administrative_status' => 'active']);
        $connector = $this->createConnectorFor($station);
        $user = $this->userWithRole('Service Client');

        $this->actingAs($user, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}/state", [
                'operational_status' => 'available',
            ])
            ->assertStatus(403);
    }

    public function test_finance_is_forbidden_from_creating_connector(): void
    {
        $station = $this->createStationInCommissioning();
        $user = $this->userWithRole('Finance');

        $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/connectors", [
                'connector_number' => 1,
                'standard' => 'type2',
                'max_power_kw' => 10,
            ])
            ->assertStatus(403);
    }
}
