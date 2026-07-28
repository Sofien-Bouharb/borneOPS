<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ChargingStation;
use App\Models\Site;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingStationQueryTest extends TestCase
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

    public function test_index_returns_paginated_results(): void
    {
        ChargingStation::factory()->count(20)->create();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/charging-stations')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertEquals(15, count($response->json('data')));
        $this->assertEquals(20, $response->json('meta.total'));
    }

    public function test_filter_by_administrative_status(): void
    {
        ChargingStation::factory()->count(3)->create(['administrative_status' => 'commissioning']);
        ChargingStation::factory()->count(2)->create(['administrative_status' => 'active', 'ocpp_identifier' => fn () => 'OCPP-' . uniqid()]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/charging-stations?administrative_status=active')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_filter_by_site_id(): void
    {
        $site1 = Site::factory()->create();
        $site2 = Site::factory()->create();
        ChargingStation::factory()->count(4)->create(['site_id' => $site1->id]);
        ChargingStation::factory()->count(2)->create(['site_id' => $site2->id]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/charging-stations?site_id={$site1->id}")
            ->assertStatus(200);

        $this->assertEquals(4, $response->json('meta.total'));
    }

    public function test_filter_by_organization_id(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        $site1 = Site::factory()->create(['organization_id' => $org1->id]);
        $site2 = Site::factory()->create(['organization_id' => $org2->id]);
        ChargingStation::factory()->count(5)->create(['site_id' => $site1->id]);
        ChargingStation::factory()->count(3)->create(['site_id' => $site2->id]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/charging-stations?organization_id={$org1->id}")
            ->assertStatus(200);

        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_search_by_name(): void
    {
        ChargingStation::factory()->create(['name' => 'Alpha Station']);
        ChargingStation::factory()->create(['name' => 'Beta Unit']);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/charging-stations?search=Alpha')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_combined_filters(): void
    {
        $site = Site::factory()->create();
        ChargingStation::factory()->create(['site_id' => $site->id, 'manufacturer' => 'Acme']);
        ChargingStation::factory()->create(['site_id' => $site->id, 'manufacturer' => 'Other']);
        ChargingStation::factory()->create(['manufacturer' => 'Acme']);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson("/api/charging-stations?site_id={$site->id}&manufacturer=Acme")
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_soft_deleted_stations_excluded(): void
    {
        $station = ChargingStation::factory()->create();
        $station->delete();

        ChargingStation::factory()->count(2)->create();

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/charging-stations')
            ->assertStatus(200);

        $this->assertEquals(2, $response->json('meta.total'));
    }
}
