<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ChargingStation;
use App\Models\ChargingStationHistory;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingStationHistoryTest extends TestCase
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

    public function test_creation_produces_history_with_null_old_values(): void
    {
        $site = Site::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/charging-stations', [
                'name' => 'History Test',
                'reference' => 'REF-HIST-001',
                'serial_number' => 'SN-HIST-001',
                'model' => 'M1',
                'manufacturer' => 'Mfg',
                'latitude' => 36.8,
                'longitude' => 10.18,
                'ocpp_version' => '1.6',
                'declared_connector_count' => 1,
                'power_kw' => 7.4,
                'site_id' => $site->id,
            ])
            ->assertStatus(201);

        $history = ChargingStationHistory::where('event_type', 'created')->latest()->first();

        $this->assertNotNull($history);
        $this->assertNull($history->old_values);
        $this->assertNotNull($history->new_values);
        $this->assertEquals($this->admin->id, $history->performed_by);
    }

    public function test_update_records_only_changed_fields(): void
    {
        $station = ChargingStation::factory()->create(['name' => 'Original']);

        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/charging-stations/{$station->id}", ['name' => 'Changed'])
            ->assertStatus(200);

        $history = ChargingStationHistory::where('charging_station_id', $station->id)
            ->where('event_type', 'updated')
            ->latest()->first();

        $this->assertNotNull($history);
        $old = is_string($history->old_values) ? json_decode($history->old_values, true) : $history->old_values;
        $new = is_string($history->new_values) ? json_decode($history->new_values, true) : $history->new_values;
        $this->assertEquals(['name' => 'Original'], $old);
        $this->assertEquals(['name' => 'Changed'], $new);
    }

    public function test_history_endpoint_returns_paginated_results(): void
    {
        $station = ChargingStation::factory()->create();

        ChargingStationHistory::create([
            'charging_station_id' => $station->id,
            'event_type' => 'created',
            'old_values' => null,
            'new_values' => ['name' => 'test'],
            'source' => 'user',
            'performed_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/charging-stations/{$station->id}/history")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_history_includes_performed_by_details(): void
    {
        $station = ChargingStation::factory()->create();

        ChargingStationHistory::create([
            'charging_station_id' => $station->id,
            'event_type' => 'created',
            'old_values' => null,
            'new_values' => ['name' => 'test'],
            'source' => 'user',
            'performed_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/charging-stations/{$station->id}/history")
            ->assertStatus(200)
            ->assertJsonPath('data.0.performed_by.id', $this->admin->id)
            ->assertJsonPath('data.0.performed_by.name', $this->admin->name)
            ->assertJsonPath('data.0.performed_by.email', $this->admin->email);
    }
    public function test_history_insert_failure_rolls_back_station_update(): void
    {
        $station = \App\Models\ChargingStation::factory()->create([
            'name' => 'Original Name',
            'administrative_status' => 'commissioning',
        ]);

        // Build a User model in memory only (never persisted), with a fake ID
        // that guarantees a FK violation when the history row tries to reference it.
        $ghostUser = User::factory()->make();
        $ghostUser->id = 999999;

        try {
            app(\App\Services\ChargingStationService::class)->update(
                $station,
                ['name' => 'Would-Be Renamed'],
                $ghostUser,
            );
            $this->fail('Expected an exception due to invalid performed_by FK, but none was thrown.');
        } catch (\Throwable $e) {
            // Expected — the history insert violates the FK, the transaction rolls back.
        }

        // The station should be unchanged in the database, and no history row created.
        $station->refresh();
        $this->assertSame('Original Name', $station->name);
        $this->assertSame(0, $station->histories()->count());
    }
}
