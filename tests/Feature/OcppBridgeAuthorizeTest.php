<?php

namespace Tests\Feature;

use App\Models\ChargingStation;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\RfidBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OcppBridgeAuthorizeTest extends TestCase
{
    use RefreshDatabase;

    private RfidBadgeService $badgeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->badgeService = app(RfidBadgeService::class);
    }

    protected function bridgeHeaders(): array
    {
        return [
            'X-OCPP-Bridge-Token' => config('services.ocpp_bridge.token'),
        ];
    }

    private function clientUser(): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole('Client');
        return $user;
    }

    public function test_authorize_accepts_valid_credential_for_station_in_owner_organization(): void
    {
        $organization = Organization::factory()->create();
        $client = $this->clientUser();
        $client->organizations()->attach($organization->id);
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        $station = ChargingStation::factory()->create([
            'site_id' => $site->id,
            'ocpp_identifier' => 'CP-AUTHZ-001',
        ]);
        $badge = $this->badgeService->create(['user_id' => $client->id], 'VALID-RAW-TOKEN');
        $this->badgeService->activate($badge);

        $response = $this->postJson('/api/internal/ocpp/authorize', [
            'ocpp_identifier' => 'CP-AUTHZ-001',
            'identifier' => 'VALID-RAW-TOKEN',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $response->assertJson(['accepted' => true, 'reason' => 'accepted']);
    }

    public function test_authorize_rejects_unknown_credential(): void
    {
        ChargingStation::factory()->create(['ocpp_identifier' => 'CP-AUTHZ-002']);

        $response = $this->postJson('/api/internal/ocpp/authorize', [
            'ocpp_identifier' => 'CP-AUTHZ-002',
            'identifier' => 'NEVER-REGISTERED',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $response->assertJson(['accepted' => false, 'reason' => 'badge_not_found']);
    }

    public function test_authorize_rejects_blocked_credential(): void
    {
        $client = $this->clientUser();
        ChargingStation::factory()->create(['ocpp_identifier' => 'CP-AUTHZ-003']);
        $badge = $this->badgeService->create(['user_id' => $client->id], 'BLOCKED-RAW-TOKEN');
        $this->badgeService->activate($badge);
        $this->badgeService->block($badge);

        $response = $this->postJson('/api/internal/ocpp/authorize', [
            'ocpp_identifier' => 'CP-AUTHZ-003',
            'identifier' => 'BLOCKED-RAW-TOKEN',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $response->assertJson(['accepted' => false, 'reason' => 'badge_blocked']);
    }

    public function test_authorize_rejects_credential_for_station_outside_owner_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $client = $this->clientUser();
        $client->organizations()->attach($organization->id);
        $foreignSite = Site::factory()->create(['organization_id' => $otherOrganization->id]);
        ChargingStation::factory()->create([
            'site_id' => $foreignSite->id,
            'ocpp_identifier' => 'CP-AUTHZ-004',
        ]);
        $badge = $this->badgeService->create(['user_id' => $client->id], 'WRONG-ORG-RAW-TOKEN');
        $this->badgeService->activate($badge);

        $response = $this->postJson('/api/internal/ocpp/authorize', [
            'ocpp_identifier' => 'CP-AUTHZ-004',
            'identifier' => 'WRONG-ORG-RAW-TOKEN',
        ], $this->bridgeHeaders());

        $response->assertStatus(200);
        $response->assertJson(['accepted' => false, 'reason' => 'station_ineligible']);
    }

    public function test_authorize_for_unknown_station_returns_404(): void
    {
        $response = $this->postJson('/api/internal/ocpp/authorize', [
            'ocpp_identifier' => 'CP-DOES-NOT-EXIST',
            'identifier' => 'ANYTHING',
        ], $this->bridgeHeaders());

        $response->assertStatus(404);
    }

    public function test_authorize_does_not_create_any_charging_session(): void
    {
        $client = $this->clientUser();
        ChargingStation::factory()->create(['ocpp_identifier' => 'CP-AUTHZ-005']);
        $badge = $this->badgeService->create(['user_id' => $client->id], 'NO-SESSION-RAW-TOKEN');
        $this->badgeService->activate($badge);

        $this->postJson('/api/internal/ocpp/authorize', [
            'ocpp_identifier' => 'CP-AUTHZ-005',
            'identifier' => 'NO-SESSION-RAW-TOKEN',
        ], $this->bridgeHeaders());

        $this->assertDatabaseCount('charging_sessions', 0);
    }

    public function test_authorize_requires_all_fields(): void
    {
        $response = $this->postJson('/api/internal/ocpp/authorize', [], $this->bridgeHeaders());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ocpp_identifier', 'identifier']);
    }

    public function test_authorize_rejects_requests_without_bridge_token(): void
    {
        ChargingStation::factory()->create(['ocpp_identifier' => 'CP-AUTHZ-006']);

        $response = $this->postJson('/api/internal/ocpp/authorize', [
            'ocpp_identifier' => 'CP-AUTHZ-006',
            'identifier' => 'ANYTHING',
        ]);

        $response->assertStatus(401);
    }
}
