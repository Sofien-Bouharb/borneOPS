<?php

namespace App\Services;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrganizationAccessService
{
    public function isRestricted(User $user): bool
    {
        return $user->hasRole('Client');
    }

    public function organizationIdsFor(User $user): array
    {
        if (!$this->isRestricted($user)) {
            return [];
        }

        return $user->organizations()->pluck('organizations.id')->all();
    }

    public function scopeOrganizations(Builder $query, User $user): Builder
    {
        if (!$this->isRestricted($user)) {
            return $query;
        }

        return $query->whereIn('id', $this->organizationIdsFor($user));
    }

    public function scopeSites(Builder $query, User $user): Builder
    {
        if (!$this->isRestricted($user)) {
            return $query;
        }

        return $query->whereIn('organization_id', $this->organizationIdsFor($user));
    }

    public function scopeStations(Builder $query, User $user): Builder
    {
        if (!$this->isRestricted($user)) {
            return $query;
        }

        $organizationIds = $this->organizationIdsFor($user);

        return $query->whereHas('site', function (Builder $siteQuery) use ($organizationIds) {
            $siteQuery->whereIn('organization_id', $organizationIds);
        });
    }

    public function scopeChargingSessions(Builder $query, User $user): Builder
    {
        if (!$this->isRestricted($user)) {
            return $query;
        }

        $organizationIds = $this->organizationIdsFor($user);

        return $query->whereHas('customer', function (Builder $customerQuery) use ($organizationIds) {
            $customerQuery->whereHas('organizations', function (Builder $orgQuery) use ($organizationIds) {
                $orgQuery->whereIn('organizations.id', $organizationIds);
            });
        });
    }

    public function canAccessOrganization(User $user, Organization $organization): bool
    {
        if (!$this->isRestricted($user)) {
            return true;
        }

        return in_array($organization->id, $this->organizationIdsFor($user), true);
    }

    public function canAccessSite(User $user, Site $site): bool
    {
        if (!$this->isRestricted($user)) {
            return true;
        }

        return in_array($site->organization_id, $this->organizationIdsFor($user), true);
    }

    public function canAccessStation(User $user, ChargingStation $station): bool
    {
        if (!$this->isRestricted($user)) {
            return true;
        }

        if ($station->site_id === null) {
            return false;
        }

        $organizationId = $station->site?->organization_id
            ?? $station->loadMissing('site')->site?->organization_id;

        if ($organizationId === null) {
            return false;
        }

        return in_array($organizationId, $this->organizationIdsFor($user), true);
    }

    public function canAccessChargingSession(User $user, ChargingSession $session): bool
    {
        if (!$this->isRestricted($user)) {
            return true;
        }

        if ($session->customer_user_id === null) {
            return false;
        }

        $customer = $session->customer ?? $session->loadMissing('customer')->customer;

        if ($customer === null) {
            return false;
        }

        $organizationIds = $this->organizationIdsFor($user);

        return $customer->organizations()
            ->whereIn('organizations.id', $organizationIds)
            ->exists();
    }
}
