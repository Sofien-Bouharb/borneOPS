<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChargingStationRemoteControlTest extends TestCase
{



protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
    use RefreshDatabase;

    protected function authorizedUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Administrator');
        return $user;
    }

    public function test_remote_start_calls_gateway_and_returns_status(): void
    {
        Http::fake([
            '*/commands/*/remote-start' => Http::response(['status' => 'Accepted'], 200),
        ]);

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-001']);
        $user = $this->authorizedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/remote-start", ['id_tag' => 'TAG-1']);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'Accepted']);
    }

    public function test_remote_start_requires_permission(): void
    {
        Http::fake();

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-002']);
        $user = User::factory()->create();
        $user->assignRole('Client');

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/remote-start", ['id_tag' => 'TAG-1']);

        $response->assertStatus(403);
    }

    public function test_remote_start_requires_id_tag(): void
    {
        Http::fake();

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-003']);
        $user = $this->authorizedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/remote-start", []);

        $response->assertStatus(422);
    }

    public function test_remote_start_surfaces_gateway_404_as_409(): void
    {
        Http::fake([
            '*/commands/*/remote-start' => Http::response(['detail' => "'CP-004' is not currently connected."], 404),
        ]);

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-004']);
        $user = $this->authorizedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/remote-start", ['id_tag' => 'TAG-1']);

        $response->assertStatus(409);
    }

    public function test_reset_calls_gateway_and_returns_status(): void
    {
        Http::fake([
            '*/commands/*/reset' => Http::response(['status' => 'Accepted'], 200),
        ]);

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-005']);
        $user = $this->authorizedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/reset", ['type' => 'Soft']);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'Accepted']);
    }

    public function test_reset_rejects_invalid_type(): void
    {
        Http::fake();

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-006']);
        $user = $this->authorizedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/reset", ['type' => 'NotReal']);

        $response->assertStatus(422);
    }

    public function test_unlock_connector_calls_gateway_and_returns_status(): void
    {
        Http::fake([
            '*/commands/*/unlock-connector' => Http::response(['status' => 'Unlocked'], 200),
        ]);

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-007']);
        $connector = Connector::factory()->create(['charging_station_id' => $station->id, 'connector_number' => 1]);
        $user = $this->authorizedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}/unlock");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'Unlocked']);
    }

    public function test_unlock_connector_forwards_evse_and_connector_id_when_present(): void
    {
        Http::fake([
            '*/commands/*/unlock-connector' => Http::response(['status' => 'Unlocked'], 200),
        ]);

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-013']);
        $connector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'ocpp_evse_id' => 2,
            'ocpp_connector_id' => 1,
        ]);
        $user = $this->authorizedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}/unlock");

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/unlock-connector')
                && $request['evse_id'] === 2
                && $request['connector_id'] === 1;
        });
    }

    public function test_unlock_connector_surfaces_gateway_422_as_409(): void
    {
        Http::fake([
            '*/commands/*/unlock-connector' => Http::response([
                'detail' => 'evse_id and connector_id are both required to unlock a connector on an OCPP 2.0.1 station.',
            ], 422),
        ]);

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-014']);
        $connector = Connector::factory()->create(['charging_station_id' => $station->id, 'connector_number' => 1]);
        $user = $this->authorizedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}/unlock");

        $response->assertStatus(409);
    }

    public function test_unlock_connector_returns_404_for_mismatched_station(): void
    {
        Http::fake();

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-008']);
        $otherStation = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-009']);
        $connector = Connector::factory()->create(['charging_station_id' => $otherStation->id, 'connector_number' => 1]);
        $user = $this->authorizedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/connectors/{$connector->id}/unlock");

        $response->assertStatus(404);
    }

    public function test_remote_stop_calls_gateway_with_transaction_id(): void
    {
        Http::fake([
            '*/commands/*/remote-stop' => Http::response(['status' => 'Accepted'], 200),
        ]);

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-010']);
        $connector = Connector::factory()->create(['charging_station_id' => $station->id, 'connector_number' => 1]);
        $session = ChargingSession::factory()->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'status' => 'active',
            'ocpp_transaction_id' => '99',
        ]);
        $user = $this->authorizedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/charging-sessions/{$session->id}/remote-stop");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'Accepted']);
    }

    public function test_remote_stop_returns_409_when_session_has_no_transaction_id(): void
    {
        Http::fake();

        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-011']);
        $connector = Connector::factory()->create(['charging_station_id' => $station->id, 'connector_number' => 1]);
        $session = ChargingSession::factory()->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'status' => 'pending',
            'ocpp_transaction_id' => null,
        ]);
        $user = $this->authorizedUser();

        $response = $this->actingAs($user, 'api')
            ->postJson("/api/charging-stations/{$station->id}/charging-sessions/{$session->id}/remote-stop");

        $response->assertStatus(409);
    }

    public function test_all_remote_control_routes_require_authentication(): void
    {
        $station = ChargingStation::factory()->create(['ocpp_identifier' => 'CP-012']);

        $this->postJson("/api/charging-stations/{$station->id}/remote-start", ['id_tag' => 'TAG-1'])->assertStatus(401);
        $this->postJson("/api/charging-stations/{$station->id}/reset", ['type' => 'Soft'])->assertStatus(401);
    }
}
