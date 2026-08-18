<?php

namespace App\ValueObjects;

use App\Enums\RfidAuthorizationReason;
use App\Models\RfidBadge;
use App\Models\User;

final class RfidAuthorizationDecision
{
    private function __construct(
        public readonly bool $accepted,
        public readonly RfidAuthorizationReason $reason,
        public readonly ?User $user,
        public readonly ?RfidBadge $badge,
    ) {
    }

    public static function accept(User $user, RfidBadge $badge): self
    {
        return new self(true, RfidAuthorizationReason::ACCEPTED, $user, $badge);
    }

    public static function reject(RfidAuthorizationReason $reason, ?User $user = null, ?RfidBadge $badge = null): self
    {
        return new self(false, $reason, $user, $badge);
    }
}
