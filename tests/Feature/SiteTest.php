<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Administrator');
        $this->organization = Organization::factory()->create();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/sites')->assertStatus(401);
    }

    public function test_client_role_with_no_organizations_sees_empty_sites(): void
    {
        Site::factory()->create(['organization_id' => $this->organization->id]);

        $client = User::factory()->create();
        $client->assignRole('Client');

        $this->actingAs($client, 'api')
            ->getJson('/api/sites')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_client_role_sees_only_sites_for_own_organization(): void
    {
        $site = Site::factory()->create(['organization_id' => $this->organization->id]);

        $otherOrganization = Organization::factory()->create();
        Site::factory()->create(['organization_id' => $otherOrganization->id]);

        $client = User::factory()->create();
        $client->assignRole('Client');
        $client->organizations()->attach($this->organization->id);

        $this->actingAs($client, 'api')
            ->getJson('/api/sites')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $site->id);
    }

    public function test_admin_can_list_sites(): void
    {
        Site::factory()->count(3)->create();

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/sites')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_site(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/sites', [
                'organization_id' => $this->organization->id,
                'name' => 'Test Site',
                'address' => '123 Test Street',
                'latitude' => 36.8,
                'longitude' => 10.18,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Site');
    }

    public function test_create_with_nonexistent_organization_fails(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/sites', [
                'organization_id' => 9999,
                'name' => 'Test Site',
                'address' => '123 Test Street',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('organization_id');
    }

    public function test_create_requires_organization_name_and_address(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/sites', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['organization_id', 'name', 'address']);
    }

    public function test_invalid_latitude_fails(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/sites', [
                'organization_id' => $this->organization->id,
                'name' => 'Test Site',
                'address' => '123',
                'latitude' => 95,
                'longitude' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('latitude');
    }

    public function test_admin_can_show_site(): void
    {
        $site = Site::factory()->create(['name' => 'Existing Site']);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/sites/{$site->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Existing Site');
    }

    public function test_admin_can_update_site(): void
    {
        $site = Site::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/sites/{$site->id}", ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }
}
