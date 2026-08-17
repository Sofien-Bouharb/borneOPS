<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserService
{
    public const ASSIGNABLE_ROLES = [
        'Opérateur',
        'Technicien',
        'Service Client',
        'Client',
    ];

    public function __construct(
        protected AuthenticationService $authenticationService,
    ) {
    }

    public function create(array $data, ?User $performedBy): User
    {
        $role = $data['role'] ?? null;
        $organizationIds = $data['organization_ids'] ?? [];

        if ($role !== null && !in_array($role, self::ASSIGNABLE_ROLES, true)) {
            throw new InvalidStateTransitionException(
                "Le rôle « {$role} » ne peut pas être attribué via ce parcours."
            );
        }

        if ($role === 'Client' && empty($organizationIds)) {
            throw new InvalidStateTransitionException(
                'Un utilisateur Client doit appartenir à au moins une organisation.'
            );
        }

        $user = DB::transaction(function () use ($data, $role, $organizationIds, $performedBy) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(40),
            ]);

            $user->account_status = 'active';
            $user->save();

            if ($role !== null) {
                $user->syncRoles([$role]);
            }

            if (!empty($organizationIds)) {
                $user->organizations()->sync($organizationIds);
            }

            UserHistory::create([
                'user_id' => $user->id,
                'old_values' => null,
                'new_values' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'account_status' => $user->account_status,
                    'role' => $role,
                    'organization_ids' => $organizationIds,
                ],
                'source' => 'user',
                'performed_by' => $performedBy?->id,
            ]);

            return $user;
        });

        $this->dispatchPasswordSetupLink($user);

        return $user;
    }

    public function update(User $user, array $data, ?User $performedBy): User
    {
        $user->fill([
            'name' => $data['name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
        ]);

        if (!$user->isDirty()) {
            return $user;
        }

        $oldValues = [];
        $newValues = [];
        foreach ($user->getDirty() as $field => $newValue) {
            $oldValues[$field] = $user->getOriginal($field);
            $newValues[$field] = $newValue;
        }

        return DB::transaction(function () use ($user, $oldValues, $newValues, $performedBy) {
            $user->save();

            UserHistory::create([
                'user_id' => $user->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'source' => 'user',
                'performed_by' => $performedBy?->id,
            ]);

            return $user;
        });
    }

    public function updateAccountStatus(User $user, string $status, ?User $performedBy): User
    {
        if (!in_array($status, ['active', 'disabled'], true)) {
            throw new InvalidStateTransitionException(
                "Le statut « {$status} » n'est pas valide."
            );
        }

        if ($this->authenticationService->isPrivilegedRole($user) && $status === 'disabled') {
            throw new InvalidStateTransitionException(
                'Un compte à rôle privilégié ne peut pas être désactivé via ce parcours.'
            );
        }

        if ($user->account_status === $status) {
            throw new InvalidStateTransitionException(
                "Le compte a déjà le statut « {$status} »."
            );
        }

        $oldStatus = $user->account_status;

        return DB::transaction(function () use ($user, $oldStatus, $status, $performedBy) {
            $user->account_status = $status;

            if ($status === 'disabled') {
                $user->session_version++;
            }

            $user->save();

            UserHistory::create([
                'user_id' => $user->id,
                'old_values' => ['account_status' => $oldStatus],
                'new_values' => ['account_status' => $status],
                'source' => 'user',
                'performed_by' => $performedBy?->id,
            ]);

            return $user;
        });
    }

    public function assignRole(User $user, string $role, ?User $performedBy): User
    {
        if ($this->authenticationService->isPrivilegedRole($user)) {
            throw new InvalidStateTransitionException(
                'Le rôle d\'un compte privilégié ne peut pas être modifié via ce parcours.'
            );
        }

        if (!in_array($role, self::ASSIGNABLE_ROLES, true)) {
            throw new InvalidStateTransitionException(
                "Le rôle « {$role} » ne peut pas être attribué via ce parcours."
            );
        }

        if ($role === 'Client' && $user->organizations()->count() === 0) {
            throw new InvalidStateTransitionException(
                'Un utilisateur Client doit appartenir à au moins une organisation avant de recevoir ce rôle.'
            );
        }

        $oldRoles = $user->getRoleNames()->all();

        return DB::transaction(function () use ($user, $oldRoles, $role, $performedBy) {
            $user->syncRoles([$role]);

            UserHistory::create([
                'user_id' => $user->id,
                'old_values' => ['roles' => $oldRoles],
                'new_values' => ['roles' => [$role]],
                'source' => 'user',
                'performed_by' => $performedBy?->id,
            ]);

            return $user;
        });
    }

    public function syncOrganizations(User $user, array $organizationIds, ?User $performedBy): User
    {
        if ($user->hasRole('Client') && empty($organizationIds)) {
            throw new InvalidStateTransitionException(
                'Un utilisateur Client doit conserver au moins une organisation.'
            );
        }

        $organizationIds = Organization::whereIn('id', $organizationIds)
            ->pluck('id')
            ->all();

        $oldOrganizationIds = $user->organizations()->pluck('organizations.id')->all();

        return DB::transaction(function () use ($user, $oldOrganizationIds, $organizationIds, $performedBy) {
            $user->organizations()->sync($organizationIds);

            UserHistory::create([
                'user_id' => $user->id,
                'old_values' => ['organization_ids' => $oldOrganizationIds],
                'new_values' => ['organization_ids' => $organizationIds],
                'source' => 'user',
                'performed_by' => $performedBy?->id,
            ]);

            return $user;
        });
    }

    public function resendPasswordSetupLink(User $user, ?User $performedBy): void
    {
        $this->dispatchPasswordSetupLink($user);

        UserHistory::create([
            'user_id' => $user->id,
            'old_values' => null,
            'new_values' => ['password_setup_link_resent' => true],
            'source' => 'user',
            'performed_by' => $performedBy?->id,
        ]);
    }

    protected function dispatchPasswordSetupLink(User $user): void
    {
        try {
            Password::sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
