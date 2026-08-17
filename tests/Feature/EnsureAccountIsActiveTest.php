<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureAccountIsActiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_disabled_account_is_rejected_on_a_protected_route(): void
    {
        $user = User::factory()->create(['account_status' => 'disabled']);

        $this->actingAs($user, 'api')
            ->getJson('/api/me')
            ->assertStatus(403)
            ->assertJson(['message' => 'Ce compte a été désactivé.']);
    }

    public function test_active_account_is_not_affected(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $this->actingAs($user, 'api')
            ->getJson('/api/me')
            ->assertStatus(200);
    }

    public function test_disabled_account_is_rejected_on_a_business_route_too(): void
    {
        $user = User::factory()->create(['account_status' => 'disabled']);
        $user->assignRole('Super Administrator');

        $this->actingAs($user, 'api')
            ->getJson('/api/charging-stations')
            ->assertStatus(403)
            ->assertJson(['message' => 'Ce compte a été désactivé.']);
    }

    public function test_disabling_takes_effect_immediately_even_mid_session(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $this->actingAs($user, 'api')
            ->getJson('/api/me')
            ->assertStatus(200);

        $user->account_status = 'disabled';
        $user->save();

        $this->actingAs($user->fresh(), 'api')
            ->getJson('/api/me')
            ->assertStatus(403);
    }
}
