<?php

namespace App\Enums;

enum RfidAuthorizationReason: string
{
    case ACCEPTED = 'accepted';
    case BADGE_NOT_FOUND = 'badge_not_found';
    case BADGE_PENDING = 'badge_pending';
    case BADGE_BLOCKED = 'badge_blocked';
    case BADGE_EXPIRED = 'badge_expired';
    case USER_INACTIVE = 'user_inactive';
    case ORGANIZATION_MISMATCH = 'organization_mismatch';
    case STATION_INELIGIBLE = 'station_ineligible';
}
