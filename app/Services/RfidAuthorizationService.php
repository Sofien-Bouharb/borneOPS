<?php

namespace App\Services;

use App\Enums\RfidAuthorizationReason;
use App\Models\ChargingStation;
use App\Models\RfidBadge;
use App\ValueObjects\RfidAuthorizationDecision;

class RfidAuthorizationService
{
    public function __construct(
        protected OrganizationAccessService $organizationAccessService,
    ) {
    }

    public function authorize(string $rawIdentifier, ?ChargingStation $station = null): RfidAuthorizationDecision
    {
        $badge = RfidBadge::where('identifier_hash', RfidBadge::hashIdentifier($rawIdentifier))->first();

        if ($badge === null) {
            return RfidAuthorizationDecision::reject(RfidAuthorizationReason::BADGE_NOT_FOUND);
        }

        if ($badge->administrative_status === 'blocked') {
            return RfidAuthorizationDecision::reject(RfidAuthorizationReason::BADGE_BLOCKED, badge: $badge);
        }

        if ($badge->expires_at !== null && $badge->expires_at->lessThanOrEqualTo(now())) {
            return RfidAuthorizationDecision::reject(RfidAuthorizationReason::BADGE_EXPIRED, badge: $badge);
        }

        if ($badge->administrative_status === 'pending') {
            return RfidAuthorizationDecision::reject(RfidAuthorizationReason::BADGE_PENDING, badge: $badge);
        }

        $user = $badge->user;

        if ($user === null || !$user->hasRole('Client') || $user->account_status !== 'active') {
            return RfidAuthorizationDecision::reject(RfidAuthorizationReason::USER_INACTIVE, $user, $badge);
        }

        if (!$user->organizations()->exists()) {
            return RfidAuthorizationDecision::reject(RfidAuthorizationReason::ORGANIZATION_MISMATCH, $user, $badge);
        }

        if ($station !== null && !$this->organizationAccessService->canAccessStation($user, $station)) {
            return RfidAuthorizationDecision::reject(RfidAuthorizationReason::STATION_INELIGIBLE, $user, $badge);
        }

        return RfidAuthorizationDecision::accept($user, $badge);
    }
}
