<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ChargingStation;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingStationAssignmentTest extends TestCase
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

    public function test_assign_to_site_succeeds(): void
    {
        $site = Site::factory()->create();
        $station = ChargingStation::factory()->create(['site_id' => null, 'address' => '123 Fallback']);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/assignment", [
                'site_id' => $site->id,
                'reason' => 'initial assignment',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.site_id', $site->id)
            ->assertJsonPath('data.address', null);
    }

    public function test_move_between_sites_succeeds(): void
    {
        $site1 = Site::factory()->create();
        $site2 = Site::factory()->create();
        $station = ChargingStation::factory()->create([
            'administrative_status' => 'active',
            'ocpp_identifier' => 'OCPP-MOVE-001',
            'site_id' => $site1->id,
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/assignment", [
                'site_id' => $site2->id,
                'reason' => 'relocating',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.site_id', $site2->id);
    }

    public function test_unassign_commissioning_station_succeeds(): void
    {
        $site = Site::factory()->create();
        $station = ChargingStation::factory()->create([
            'administrative_status' => 'commissioning',
            'site_id' => $site->id,
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/assignment", [
                'site_id' => null,
                'reason' => 'removing',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.site_id', null);
    }

    public function test_unassign_active_station_fails(): void
    {
        $site = Site::factory()->create();
        $station = ChargingStation::factory()->create([
            'administrative_status' => 'active',
            'ocpp_identifier' => 'OCPP-UNASSIGN-001',
            'site_id' => $site->id,
        ]);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/assignment", [
                'site_id' => null,
                'reason' => 'test',
            ])
            ->assertStatus(409);
    }

    public function test_same_site_reassignment_returns_409(): void
    {
        $site = Site::factory()->create();
        $station = ChargingStation::factory()->create(['site_id' => $site->id]);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/assignment", [
                'site_id' => $site->id,
                'reason' => 'dup',
            ])
            ->assertStatus(409);
    }

    public function test_assignment_to_nonexistent_site_fails(): void
    {
        $station = ChargingStation::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}/assignment", [
                'site_id' => 9999,
                'reason' => 'bad site',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('site_id');
    }
}
