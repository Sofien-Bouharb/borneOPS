<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
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

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/organizations')->assertStatus(401);
    }

    public function test_client_role_with_no_organizations_sees_empty_list(): void
    {
        Organization::factory()->create();

        $client = User::factory()->create();
        $client->assignRole('Client');

        $this->actingAs($client, 'api')
            ->getJson('/api/organizations')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_client_role_sees_only_own_organizations(): void
    {
        $organization = Organization::factory()->create();
        Organization::factory()->create();

        $client = User::factory()->create();
        $client->assignRole('Client');
        $client->organizations()->attach($organization->id);

        $this->actingAs($client, 'api')
            ->getJson('/api/organizations')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $organization->id);
    }

    public function test_admin_can_list_organizations(): void
    {
        Organization::factory()->count(3)->create();

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/organizations')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_organization(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/organizations', [
                'name' => 'TestCorp SA',
                'type' => 'operator',
                'contact_email' => 'contact@testcorp.tn',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'TestCorp SA');
    }

    public function test_create_requires_name_and_type(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/organizations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'type']);
    }

    public function test_invalid_type_fails(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/organizations', ['name' => 'X', 'type' => 'invalid'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_admin_can_show_organization(): void
    {
        $org = Organization::factory()->create(['name' => 'Existing Org']);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/organizations/{$org->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Existing Org');
    }

    public function test_show_nonexistent_returns_404(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/organizations/9999')
            ->assertStatus(404);
    }

    public function test_admin_can_update_organization(): void
    {
        $org = Organization::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/organizations/{$org->id}", ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }
}
