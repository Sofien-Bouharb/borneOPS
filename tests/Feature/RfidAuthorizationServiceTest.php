<?php

namespace Tests\Feature;

use App\Enums\RfidAuthorizationReason;
use App\Models\ChargingStation;
use App\Models\Organization;
use App\Models\RfidBadge;
use App\Models\Site;
use App\Models\User;
use App\Services\RfidAuthorizationService;
use App\Services\RfidBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfidAuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RfidAuthorizationService $authService;
    private RfidBadgeService $badgeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->authService = app(RfidAuthorizationService::class);
        $this->badgeService = app(RfidBadgeService::class);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole('Client');
        return $user;
    }

    private function stationForOrganization(Organization $organization): ChargingStation
    {
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        return ChargingStation::factory()->create(['site_id' => $site->id]);
    }

    public function test_unknown_credential_is_rejected(): void
    {
        $decision = $this->authService->authorize('UNKNOWN-RAW-TOKEN');

        $this->assertFalse($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::BADGE_NOT_FOUND, $decision->reason);
        $this->assertNull($decision->badge);
        $this->assertNull($decision->user);
    }

    public function test_pending_badge_is_rejected(): void
    {
        $client = $this->clientUser();
        $this->badgeService->create(['user_id' => $client->id], 'PENDING-RAW');

        $decision = $this->authService->authorize('PENDING-RAW');

        $this->assertFalse($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::BADGE_PENDING, $decision->reason);
    }

    public function test_active_badge_with_no_station_context_is_accepted(): void
    {
        $client = $this->clientUser();
        $organization = Organization::factory()->create();
        $client->organizations()->attach($organization->id);
        $badge = $this->badgeService->create(['user_id' => $client->id], 'ACTIVE-RAW');
        $this->badgeService->activate($badge);

        $decision = $this->authService->authorize('ACTIVE-RAW');

        $this->assertTrue($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::ACCEPTED, $decision->reason);
        $this->assertSame($client->id, $decision->user->id);
        $this->assertSame($badge->id, $decision->badge->id);
    }

    public function test_blocked_badge_is_rejected(): void
    {
        $client = $this->clientUser();
        $badge = $this->badgeService->create(['user_id' => $client->id], 'BLOCKED-RAW');
        $this->badgeService->activate($badge);
        $this->badgeService->block($badge);

        $decision = $this->authService->authorize('BLOCKED-RAW');

        $this->assertFalse($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::BADGE_BLOCKED, $decision->reason);
    }

    public function test_expired_badge_is_rejected(): void
    {
        $client = $this->clientUser();
        $badge = $this->badgeService->create(['user_id' => $client->id], 'EXPIRED-RAW');
        $this->badgeService->activate($badge);
        $this->badgeService->updateExpiration($badge, now()->subDay());

        $decision = $this->authService->authorize('EXPIRED-RAW');

        $this->assertFalse($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::BADGE_EXPIRED, $decision->reason);
    }

    public function test_blocked_takes_priority_over_expired(): void
    {
        $client = $this->clientUser();
        $badge = $this->badgeService->create(['user_id' => $client->id], 'BLOCKED-EXPIRED-RAW');
        $this->badgeService->activate($badge);
        $this->badgeService->updateExpiration($badge, now()->subDay());
        $badge->refresh();
        $this->badgeService->block($badge);

        $decision = $this->authService->authorize('BLOCKED-EXPIRED-RAW');

        $this->assertFalse($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::BADGE_BLOCKED, $decision->reason);
    }

    public function test_expiration_exactly_at_now_is_rejected_as_expired(): void
    {
        $now = now();
        \Illuminate\Support\Carbon::setTestNow($now);

        $client = $this->clientUser();
        $badge = $this->badgeService->create(['user_id' => $client->id], 'BOUNDARY-RAW');
        $this->badgeService->activate($badge);
        $this->badgeService->updateExpiration($badge, $now);

        $decision = $this->authService->authorize('BOUNDARY-RAW');

        $this->assertFalse($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::BADGE_EXPIRED, $decision->reason);

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_disabled_account_is_rejected(): void
    {
        $client = $this->clientUser();
        $badge = $this->badgeService->create(['user_id' => $client->id], 'DISABLED-RAW');
        $this->badgeService->activate($badge);
        $client->account_status = 'disabled';
        $client->save();

        $decision = $this->authService->authorize('DISABLED-RAW');

        $this->assertFalse($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::USER_INACTIVE, $decision->reason);
    }

    public function test_non_client_owner_is_rejected(): void
    {
        $staff = User::factory()->create(['account_status' => 'active']);
        $staff->assignRole('Technicien');
        $badge = $this->badgeService->create(['user_id' => $staff->id], 'STAFF-OWNED-RAW');
        $this->badgeService->activate($badge);

        $decision = $this->authService->authorize('STAFF-OWNED-RAW');

        $this->assertFalse($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::USER_INACTIVE, $decision->reason);
    }

    public function test_client_with_no_organization_is_rejected(): void
    {
        $client = $this->clientUser();
        $badge = $this->badgeService->create(['user_id' => $client->id], 'NO-ORG-RAW');
        $this->badgeService->activate($badge);

        $decision = $this->authService->authorize('NO-ORG-RAW');

        $this->assertFalse($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::ORGANIZATION_MISMATCH, $decision->reason);
    }

    public function test_station_outside_owner_organization_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $client = $this->clientUser();
        $client->organizations()->attach($organization->id);
        $badge = $this->badgeService->create(['user_id' => $client->id], 'WRONG-STATION-RAW');
        $this->badgeService->activate($badge);
        $foreignStation = $this->stationForOrganization($otherOrganization);

        $decision = $this->authService->authorize('WRONG-STATION-RAW', $foreignStation);

        $this->assertFalse($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::STATION_INELIGIBLE, $decision->reason);
    }

    public function test_station_within_owner_organization_is_accepted(): void
    {
        $organization = Organization::factory()->create();
        $client = $this->clientUser();
        $client->organizations()->attach($organization->id);
        $badge = $this->badgeService->create(['user_id' => $client->id], 'RIGHT-STATION-RAW');
        $this->badgeService->activate($badge);
        $ownStation = $this->stationForOrganization($organization);

        $decision = $this->authService->authorize('RIGHT-STATION-RAW', $ownStation);

        $this->assertTrue($decision->accepted);
        $this->assertSame(RfidAuthorizationReason::ACCEPTED, $decision->reason);
        $this->assertSame($client->id, $decision->user->id);
    }

    public function test_credential_normalization_trims_whitespace_for_lookup(): void
    {
        $client = $this->clientUser();
        $client->organizations()->attach(Organization::factory()->create()->id);
        $badge = $this->badgeService->create(['user_id' => $client->id], '  SPACED-RAW  ');
        $this->badgeService->activate($badge);

        $decision = $this->authService->authorize('SPACED-RAW');

        $this->assertTrue($decision->accepted);
    }
}
