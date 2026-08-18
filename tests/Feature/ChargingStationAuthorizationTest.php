<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\ChargingStation;
use App\Models\Site;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
class ChargingStationAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Administrator');
        return $user;
    }
    private function clientUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        return $user;
    }
    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/charging-stations')->assertStatus(401);
    }
    public function test_client_role_with_no_organization_sees_empty_stations(): void
    {
        ChargingStation::factory()->create();

        $this->actingAs($this->clientUser(), 'api')
            ->getJson('/api/charging-stations')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
    public function test_client_role_sees_only_stations_for_own_organization(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $station = ChargingStation::factory()->create(['site_id' => $site->id]);

        $otherOrganization = Organization::factory()->create();
        $otherSite = Site::factory()->create(['organization_id' => $otherOrganization->id]);
        ChargingStation::factory()->create(['site_id' => $otherSite->id]);

        $client = $this->clientUser();
        $client->organizations()->attach($organization->id);

        $this->actingAs($client, 'api')
            ->getJson('/api/charging-stations')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $station->id);
    }
    public function test_client_role_cannot_create_station(): void
    {
        $this->actingAs($this->clientUser(), 'api')
            ->postJson('/api/charging-stations', [])
            ->assertStatus(403);
    }
    public function test_client_role_cannot_disable_station(): void
    {
        $station = ChargingStation::factory()->create();
        $this->actingAs($this->clientUser(), 'api')
            ->patchJson("/api/charging-stations/{$station->id}/disable", ['reason' => 'test'])
            ->assertStatus(403);
    }
    public function test_admin_can_view_stations(): void
    {
        $this->actingAs($this->adminUser(), 'api')
            ->getJson('/api/charging-stations')
            ->assertStatus(200);
    }
    public function test_admin_can_create_station(): void
    {
        $site = Site::factory()->create();
        $this->actingAs($this->adminUser(), 'api')
            ->postJson('/api/charging-stations', [
                'name' => 'Auth Test Station',
                'reference' => 'REF-AUTH-001',
                'serial_number' => 'SN-AUTH-001',
                'model' => 'TestModel',
                'manufacturer' => 'TestMfg',
                'latitude' => 36.8,
                'longitude' => 10.18,
                'ocpp_version' => '1.6',
                'declared_connector_count' => 2,
                'power_kw' => 22,
                'site_id' => $site->id,
            ])
            ->assertStatus(201);
    }
    public function test_operateur_can_view_but_not_create(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Opérateur');
        $this->actingAs($user, 'api')
            ->getJson('/api/charging-stations')
            ->assertStatus(200);
        $this->actingAs($user, 'api')
            ->postJson('/api/charging-stations', [])
            ->assertStatus(403);
    }
}
