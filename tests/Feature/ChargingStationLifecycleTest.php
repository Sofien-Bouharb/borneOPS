<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ChargingStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingStationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Administrator');
    }

    private function stationInStatus(string $status, array $overrides = []): ChargingStation
    {
        return ChargingStation::factory()->create(array_merge([
            'administrative_status' => $status,
            'ocpp_identifier' => 'OCPP-LIFE-' . uniqid(),
        ], $overrides));
    }

    // --- Allowed transitions ---

    public function test_commissioning_to_active(): void
    {
        $station = $this->stationInStatus('commissioning');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/reactivate", ['reason' => 'ready'])
            ->assertStatus(200)
            ->assertJsonPath('data.administrative_status', 'active');
    }

    public function test_commissioning_to_decommissioned(): void
    {
        $station = $this->stationInStatus('commissioning');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/decommission", ['reason' => 'cancelled'])
            ->assertStatus(200)
            ->assertJsonPath('data.administrative_status', 'decommissioned');
    }

    public function test_active_to_disabled(): void
    {
        $station = $this->stationInStatus('active');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/disable", ['reason' => 'maintenance'])
            ->assertStatus(200)
            ->assertJsonPath('data.administrative_status', 'disabled');
    }

    public function test_active_to_decommissioned(): void
    {
        $station = $this->stationInStatus('active');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/decommission", ['reason' => 'eol'])
            ->assertStatus(200)
            ->assertJsonPath('data.administrative_status', 'decommissioned');
    }

    public function test_disabled_to_active(): void
    {
        $station = $this->stationInStatus('disabled');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/reactivate", ['reason' => 'fixed'])
            ->assertStatus(200)
            ->assertJsonPath('data.administrative_status', 'active');
    }

    public function test_disabled_to_decommissioned(): void
    {
        $station = $this->stationInStatus('disabled');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/decommission", ['reason' => 'eol'])
            ->assertStatus(200)
            ->assertJsonPath('data.administrative_status', 'decommissioned');
    }

    // --- Disallowed transitions ---

    public function test_commissioning_cannot_disable_directly(): void
    {
        $station = $this->stationInStatus('commissioning');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/disable", ['reason' => 'test'])
            ->assertStatus(409);
    }

    public function test_disabling_already_disabled_returns_409(): void
    {
        $station = $this->stationInStatus('disabled');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/disable", ['reason' => 'test'])
            ->assertStatus(409);
    }

    public function test_reactivating_non_disabled_station_fails(): void
    {
        $station = $this->stationInStatus('active');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/reactivate", ['reason' => 'test'])
            ->assertStatus(409);
    }

    public function test_decommissioned_is_terminal(): void
    {
        $station = $this->stationInStatus('decommissioned');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/reactivate", ['reason' => 'test'])
            ->assertStatus(409);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/disable", ['reason' => 'test'])
            ->assertStatus(409);
    }

    // --- Field preconditions ---

    public function test_activating_without_ocpp_identifier_fails(): void
    {
        $station = ChargingStation::factory()->create([
            'administrative_status' => 'commissioning',
            'ocpp_identifier' => null,
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/reactivate", ['reason' => 'ready'])
            ->assertStatus(409);
    }

    public function test_disable_without_reason_returns_422(): void
    {
        $station = $this->stationInStatus('active');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/disable", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_decommission_without_reason_returns_422(): void
    {
        $station = $this->stationInStatus('active');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/decommission", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    // --- Operational status ---

    public function test_valid_operational_status_change(): void
    {
        $station = $this->stationInStatus('active');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/state", [
                'operational_status' => 'available',
                'reason' => 'online',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.operational_status', 'available');
    }

    public function test_invalid_operational_status_fails(): void
    {
        $station = $this->stationInStatus('active');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/state", [
                'operational_status' => 'exploded',
                'reason' => 'test',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('operational_status');
    }

    public function test_state_change_requires_reason(): void
    {
        $station = $this->stationInStatus('active');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/state", [
                'operational_status' => 'maintenance',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    // --- Sensitive field protection ---

    public function test_sensitive_fields_editable_during_commissioning(): void
    {
        $station = $this->stationInStatus('commissioning');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}", [
                'ocpp_identifier' => 'NEW-OCPP-ID',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.ocpp_identifier', 'NEW-OCPP-ID');
    }

    public function test_sensitive_fields_locked_after_commissioning(): void
    {
        $station = $this->stationInStatus('active');

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}", [
                'reference' => 'HACKED-REF',
            ])
            ->assertStatus(409);
    }

    public function test_reactivate_does_not_touch_operational_status(): void
    {
        $station = ChargingStation::factory()->create([
            'administrative_status' => 'disabled',
            'operational_status' => 'fault',
            'ocpp_identifier' => 'OCPP-REAC-' . uniqid(),
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/reactivate", ['reason' => 'fixed'])
            ->assertStatus(200)
            ->assertJsonPath('data.operational_status', 'fault');
    }
}
