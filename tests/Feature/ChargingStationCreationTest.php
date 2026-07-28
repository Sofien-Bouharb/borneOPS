<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ChargingStation;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingStationCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Administrator');
        $this->site = Site::factory()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Station',
            'reference' => 'REF-CREATE-001',
            'serial_number' => 'SN-CREATE-001',
            'model' => 'ModelX',
            'manufacturer' => 'Acme',
            'latitude' => 36.8,
            'longitude' => 10.18,
            'ocpp_version' => '1.6',
            'declared_connector_count' => 2,
            'power_kw' => 22,
            'site_id' => $this->site->id,
        ], $overrides);
    }

    public function test_valid_creation_succeeds_with_correct_defaults(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/charging-stations', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.administrative_status', 'commissioning')
            ->assertJsonPath('data.operational_status', 'disconnected');
    }

    public function test_duplicate_reference_fails(): void
    {
        ChargingStation::factory()->create(['reference' => 'REF-DUP']);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/charging-stations', $this->validPayload(['reference' => 'REF-DUP']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('reference');
    }

    public function test_duplicate_serial_number_fails(): void
    {
        ChargingStation::factory()->create(['serial_number' => 'SN-DUP']);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/charging-stations', $this->validPayload(['serial_number' => 'SN-DUP']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('serial_number');
    }

    public function test_invalid_ocpp_version_fails(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/charging-stations', $this->validPayload(['ocpp_version' => '3.0']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('ocpp_version');
    }

    public function test_invalid_latitude_fails(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/charging-stations', $this->validPayload(['latitude' => 95]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('latitude');
    }

    public function test_power_kw_must_be_positive(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/charging-stations', $this->validPayload(['power_kw' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('power_kw');
    }

    public function test_declared_connector_count_minimum_is_one(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/charging-stations', $this->validPayload(['declared_connector_count' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('declared_connector_count');
    }

    public function test_required_fields_enforced(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/charging-stations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'reference', 'serial_number', 'model', 'manufacturer', 'latitude', 'longitude', 'ocpp_version', 'declared_connector_count', 'power_kw']);
    }

    public function test_site_id_and_address_cannot_coexist(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/charging-stations', $this->validPayload(['address' => '123 Test St']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('address');
    }
}
