<?php
namespace Tests\Feature;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\ChargingSessionService;
use App\Services\RfidBadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChargingSessionRfidIdentityTest extends TestCase
{
    use RefreshDatabase;

    private ChargingSessionService $sessionService;
    private RfidBadgeService $badgeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->sessionService = app(ChargingSessionService::class);
        $this->badgeService = app(RfidBadgeService::class);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole('Client');
        return $user;
    }

    private function createEligibleStationAndConnector(string $ocppIdentifier, ?Site $site = null): array
    {
        $station = ChargingStation::factory()->create([
            'ocpp_identifier' => $ocppIdentifier,
            'administrative_status' => 'active',
            'operational_status' => 'available',
            'last_heartbeat_at' => now(),
            'site_id' => $site?->id,
        ]);
        $connector = Connector::factory()->create([
            'charging_station_id' => $station->id,
            'connector_number' => 1,
            'administrative_status' => 'enabled',
            'operational_status' => 'available',
        ]);
        return [$station, $connector];
    }

    public function test_bind_ocpp_transaction_id_hard_rejects_conflicting_customer_on_pending_session(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-RFID-CONFLICT-001', $site);

        $clientA = $this->clientUser();
        $clientA->organizations()->attach($organization->id);
        $clientB = $this->clientUser();
        $clientB->organizations()->attach($organization->id);

        $badgeB = $this->badgeService->create(['user_id' => $clientB->id], 'TOKEN-B-CONFLICT');
        $this->badgeService->activate($badgeB);

        $this->sessionService->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'customer_user_id' => $clientA->id,
        ], null, 'user');

        $this->expectException(InvalidStateTransitionException::class);

        $this->sessionService->bindOcppTransactionId($station, $connector, 1000, 'TOKEN-B-CONFLICT');
    }

    public function test_bind_ocpp_transaction_id_conflict_leaves_pending_session_untouched(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-RFID-CONFLICT-002', $site);

        $clientA = $this->clientUser();
        $clientA->organizations()->attach($organization->id);
        $clientB = $this->clientUser();
        $clientB->organizations()->attach($organization->id);

        $badgeB = $this->badgeService->create(['user_id' => $clientB->id], 'TOKEN-B-UNTOUCHED');
        $this->badgeService->activate($badgeB);

        $prebooked = $this->sessionService->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'customer_user_id' => $clientA->id,
        ], null, 'user');

        try {
            $this->sessionService->bindOcppTransactionId($station, $connector, 1000, 'TOKEN-B-UNTOUCHED');
        } catch (InvalidStateTransitionException $e) {
            // expected
        }

        $prebooked->refresh();
        $this->assertSame('pending', $prebooked->status);
        $this->assertSame($clientA->id, $prebooked->customer_user_id);
    }

    public function test_bind_ocpp_transaction_id_allows_same_customer_credential(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-RFID-SAME-001', $site);

        $clientA = $this->clientUser();
        $clientA->organizations()->attach($organization->id);

        $badgeA = $this->badgeService->create(['user_id' => $clientA->id], 'TOKEN-A-SAME');
        $this->badgeService->activate($badgeA);

        $this->sessionService->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'customer_user_id' => $clientA->id,
        ], null, 'user');

        $session = $this->sessionService->bindOcppTransactionId($station, $connector, 1000, 'TOKEN-A-SAME');

        $this->assertSame('active', $session->status);
        $this->assertSame($clientA->id, $session->customer_user_id);
    }

    public function test_bind_ocpp_transaction_id_with_no_credential_preserves_anonymous_path(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-RFID-ANON-001');

        $session = $this->sessionService->bindOcppTransactionId($station, $connector, 1000, null);

        $this->assertSame('active', $session->status);
        $this->assertNull($session->customer_user_id);
    }

    public function test_bind_ocpp_transaction_id_with_invalid_credential_starts_without_customer(): void
    {
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-RFID-INVALID-001');

        $session = $this->sessionService->bindOcppTransactionId($station, $connector, 1000, 'NEVER-REGISTERED-TOKEN');

        $this->assertSame('active', $session->status);
        $this->assertNull($session->customer_user_id);
    }

    public function test_bind_ocpp_transaction_id_assigns_customer_to_previously_anonymous_pending_session(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-RFID-UPGRADE-001', $site);

        $clientA = $this->clientUser();
        $clientA->organizations()->attach($organization->id);
        $badgeA = $this->badgeService->create(['user_id' => $clientA->id], 'TOKEN-A-UPGRADE');
        $this->badgeService->activate($badgeA);

        $anonymousPending = $this->sessionService->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'customer_user_id' => null,
        ], null, 'ocpp');

        $session = $this->sessionService->bindOcppTransactionId($station, $connector, 1000, 'TOKEN-A-UPGRADE');

        $this->assertSame($clientA->id, $session->customer_user_id);
        $this->assertSame($anonymousPending->id, $session->id);
    }

    public function test_bind_external_transaction_id_hard_rejects_conflicting_customer_on_pending_session(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-RFID-EXT-CONFLICT-001', $site);

        $clientA = $this->clientUser();
        $clientA->organizations()->attach($organization->id);
        $clientB = $this->clientUser();
        $clientB->organizations()->attach($organization->id);

        $badgeB = $this->badgeService->create(['user_id' => $clientB->id], 'TOKEN-B-EXT-CONFLICT');
        $this->badgeService->activate($badgeB);

        $this->sessionService->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'customer_user_id' => $clientA->id,
        ], null, 'user');

        $this->expectException(InvalidStateTransitionException::class);

        $this->sessionService->bindExternalTransactionId($station, $connector, 'EXT-TXN-001', 1000, 'TOKEN-B-EXT-CONFLICT');
    }

    public function test_bind_external_transaction_id_allows_same_customer_credential(): void
    {
        $organization = Organization::factory()->create();
        $site = Site::factory()->create(['organization_id' => $organization->id]);
        [$station, $connector] = $this->createEligibleStationAndConnector('CP-RFID-EXT-SAME-001', $site);

        $clientA = $this->clientUser();
        $clientA->organizations()->attach($organization->id);
        $badgeA = $this->badgeService->create(['user_id' => $clientA->id], 'TOKEN-A-EXT-SAME');
        $this->badgeService->activate($badgeA);

        $this->sessionService->create([
            'charging_station_id' => $station->id,
            'connector_id' => $connector->id,
            'customer_user_id' => $clientA->id,
        ], null, 'user');

        $session = $this->sessionService->bindExternalTransactionId($station, $connector, 'EXT-TXN-002', 1000, 'TOKEN-A-EXT-SAME');

        $this->assertSame('active', $session->status);
        $this->assertSame($clientA->id, $session->customer_user_id);
    }
}
