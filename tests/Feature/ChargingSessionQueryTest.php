<?php

namespace Tests\Feature;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingSessionQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function authenticatedAsFullAccessUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Administrator');

        $this->actingAs($user, 'api');

        return $user;
    }

    public function test_index_returns_paginated_results(): void
    {
        ChargingSession::factory()->count(3)->create();
        $this->authenticatedAsFullAccessUser();

        $this->getJson('/api/charging-sessions')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_filter_by_status(): void
    {
        ChargingSession::factory()->create(['status' => 'pending']);
        ChargingSession::factory()->create(['status' => 'completed']);
        $this->authenticatedAsFullAccessUser();

        $response = $this->getJson('/api/charging-sessions?status=completed');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'completed');
    }

    public function test_filter_by_charging_station_id(): void
    {
        $station = ChargingStation::factory()->create();
        $connector = Connector::factory()->create(['charging_station_id' => $station->id]);
        ChargingSession::factory()->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
        ]);
        ChargingSession::factory()->create();
        $this->authenticatedAsFullAccessUser();

        $this->getJson("/api/charging-sessions?charging_station_id={$station->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_filter_by_connector_id(): void
    {
        $connector = Connector::factory()->create();
        ChargingSession::factory()->create([
            'charging_station_id' => $connector->charging_station_id,
            'connector_id' => $connector->id,
        ]);
        ChargingSession::factory()->create();
        $this->authenticatedAsFullAccessUser();

        $this->getJson("/api/charging-sessions?connector_id={$connector->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_show_returns_session_with_nested_relations(): void
    {
        $session = ChargingSession::factory()->create();
        $this->authenticatedAsFullAccessUser();

        $this->getJson("/api/charging-sessions/{$session->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $session->id)
            ->assertJsonStructure(['data' => ['charging_station', 'connector']]);
    }

    public function test_show_returns_404_for_nonexistent_session(): void
    {
        $this->authenticatedAsFullAccessUser();

        $this->getJson('/api/charging-sessions/999999')->assertStatus(404);
    }
}
