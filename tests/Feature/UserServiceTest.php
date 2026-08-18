<?php

namespace Tests\Feature\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserHistory;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Notification::fake();
    }

    private function service(): UserService
    {
        return $this->app->make(UserService::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Administrator');

        return $user;
    }

    public function test_create_sets_active_account_status(): void
    {
        $user = $this->service()->create([
            'name' => 'New User',
            'email' => 'new.user@example.com',
        ], $this->admin());

        $this->assertSame('active', $user->fresh()->account_status);
    }

    public function test_create_assigns_the_requested_role(): void
    {
        $user = $this->service()->create([
            'name' => 'New Operateur',
            'email' => 'new.operateur@example.com',
            'role' => 'Opérateur',
        ], $this->admin());

        $this->assertTrue($user->fresh()->hasRole('Opérateur'));
    }

    public function test_create_writes_a_history_row(): void
    {
        $user = $this->service()->create([
            'name' => 'New User',
            'email' => 'new.user2@example.com',
        ], $this->admin());

        $this->assertSame(1, UserHistory::where('user_id', $user->id)->count());

        $history = UserHistory::where('user_id', $user->id)->first();
        $this->assertNull($history->old_values);
        $this->assertSame('user', $history->source);
    }

    public function test_create_rejects_a_privileged_role(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $this->service()->create([
            'name' => 'Sneaky Admin',
            'email' => 'sneaky@example.com',
            'role' => 'Super Administrator',
        ], $this->admin());
    }

    public function test_create_rejects_client_role_without_organization(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $this->service()->create([
            'name' => 'Bad Client',
            'email' => 'bad.client@example.com',
            'role' => 'Client',
        ], $this->admin());
    }

    public function test_create_client_with_organization_succeeds(): void
    {
        $organization = Organization::factory()->create();

        $user = $this->service()->create([
            'name' => 'Good Client',
            'email' => 'good.client@example.com',
            'role' => 'Client',
            'organization_ids' => [$organization->id],
        ], $this->admin());

        $this->assertTrue($user->fresh()->hasRole('Client'));
        $this->assertTrue($user->fresh()->organizations->contains($organization->id));
    }

    public function test_update_account_status_to_disabled_writes_history(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $this->service()->updateAccountStatus($user, 'disabled', $this->admin());

        $this->assertSame('disabled', $user->fresh()->account_status);
        $this->assertSame(1, UserHistory::where('user_id', $user->id)->count());
    }

    public function test_update_account_status_bumps_session_version_when_disabling(): void
    {
        $user = User::factory()->create(['account_status' => 'active', 'session_version' => 0]);

        $this->service()->updateAccountStatus($user, 'disabled', $this->admin());

        $this->assertSame(1, $user->fresh()->session_version);
    }

    public function test_update_account_status_refuses_to_disable_a_privileged_user(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);
        $user->assignRole('Exploitant');

        $this->expectException(InvalidStateTransitionException::class);

        $this->service()->updateAccountStatus($user, 'disabled', $this->admin());
    }

    public function test_update_account_status_rejects_resubmitting_the_same_status(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $this->expectException(InvalidStateTransitionException::class);

        $this->service()->updateAccountStatus($user, 'active', $this->admin());
    }

    public function test_assign_role_refuses_to_change_a_privileged_users_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Finance');

        $this->expectException(InvalidStateTransitionException::class);

        $this->service()->assignRole($user, 'Opérateur', $this->admin());
    }

    public function test_assign_role_rejects_a_role_outside_the_assignable_list(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidStateTransitionException::class);

        $this->service()->assignRole($user, 'Super Administrator', $this->admin());
    }

    public function test_assign_role_to_client_requires_existing_organization(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidStateTransitionException::class);

        $this->service()->assignRole($user, 'Client', $this->admin());
    }

    public function test_assign_role_replaces_previous_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Technicien');

        $this->service()->assignRole($user, 'Service Client', $this->admin());

        $user = $user->fresh();
        $this->assertTrue($user->hasRole('Service Client'));
        $this->assertFalse($user->hasRole('Technicien'));
    }

    public function test_sync_organizations_rejects_emptying_a_clients_memberships(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('Client');
        $user->organizations()->attach($organization->id);

        $this->expectException(InvalidStateTransitionException::class);

        $this->service()->syncOrganizations($user, [], $this->admin());
    }

    public function test_sync_organizations_allows_emptying_a_non_client_users_memberships(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->assignRole('Service Client');
        $user->organizations()->attach($organization->id);

        $this->service()->syncOrganizations($user, [], $this->admin());

        $this->assertCount(0, $user->fresh()->organizations);
    }

    public function test_sync_organizations_ignores_nonexistent_organization_ids(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $this->service()->syncOrganizations($user, [$organization->id, 99999], $this->admin());

        $ids = $user->fresh()->organizations->pluck('id')->all();
        $this->assertSame([$organization->id], $ids);
    }
}
