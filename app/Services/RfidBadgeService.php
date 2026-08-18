<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\RfidBadge;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RfidBadgeService
{
    public function create(array $data, string $rawIdentifier, ?User $performedBy = null, string $source = 'user'): RfidBadge
    {
        return DB::transaction(function () use ($data, $rawIdentifier, $performedBy, $source) {
            $badge = RfidBadge::create([
                'user_id' => $data['user_id'],
                'identifier_hash' => RfidBadge::hashIdentifier($rawIdentifier),
                'identifier_hint' => RfidBadge::hintFor($rawIdentifier),
                'label' => $data['label'] ?? null,
                'administrative_status' => 'pending',
                'expires_at' => $data['expires_at'] ?? null,
            ]);

            $this->writeHistory($badge, 'created', null, $badge->only([
                'user_id', 'label', 'administrative_status', 'expires_at',
            ]), $source, $performedBy);

            return $badge;
        });
    }

    public function update(RfidBadge $badge, array $data, ?User $performedBy = null, string $source = 'user'): RfidBadge
    {
        return DB::transaction(function () use ($badge, $data, $performedBy, $source) {
            $old = $badge->only(['label']);

            $badge->fill([
                'label' => $data['label'] ?? $badge->label,
            ]);

            if (!$badge->isDirty()) {
                return $badge;
            }

            $badge->save();

            $this->writeHistory($badge, 'metadata_updated', $old, $badge->only(['label']), $source, $performedBy);

            return $badge;
        });
    }

    public function reassign(RfidBadge $badge, int $newUserId, ?User $performedBy = null, string $source = 'user'): RfidBadge
    {
        if ($badge->administrative_status === 'active') {
            throw new InvalidStateTransitionException(
                "Impossible de réattribuer un badge actif. Bloquez-le d'abord."
            );
        }

        return DB::transaction(function () use ($badge, $newUserId, $performedBy, $source) {
            $old = $badge->only(['user_id']);

            $badge->user_id = $newUserId;
            $badge->save();

            $this->writeHistory($badge, 'reassigned', $old, $badge->only(['user_id']), $source, $performedBy);

            return $badge;
        });
    }

    public function activate(RfidBadge $badge, ?User $performedBy = null, string $source = 'user'): RfidBadge
    {
        if ($badge->administrative_status === 'active') {
            throw new InvalidStateTransitionException('Ce badge est déjà actif.');
        }

        if ($badge->expires_at !== null && $badge->expires_at->lessThanOrEqualTo(now())) {
            throw new InvalidStateTransitionException(
                "Impossible d'activer un badge expiré. Prolongez ou effacez la date d'expiration d'abord."
            );
        }

        return DB::transaction(function () use ($badge, $performedBy, $source) {
            $old = $badge->only(['administrative_status', 'activated_at']);

            $badge->administrative_status = 'active';
            $badge->activated_at = now();
            $badge->save();

            $this->writeHistory($badge, 'activated', $old, $badge->only(['administrative_status', 'activated_at']), $source, $performedBy);

            return $badge;
        });
    }

    public function block(RfidBadge $badge, ?User $performedBy = null, string $source = 'user'): RfidBadge
    {
        if ($badge->administrative_status === 'blocked') {
            throw new InvalidStateTransitionException('Ce badge est déjà bloqué.');
        }

        return DB::transaction(function () use ($badge, $performedBy, $source) {
            $old = $badge->only(['administrative_status', 'blocked_at']);

            $badge->administrative_status = 'blocked';
            $badge->blocked_at = now();
            $badge->save();

            $this->writeHistory($badge, 'blocked', $old, $badge->only(['administrative_status', 'blocked_at']), $source, $performedBy);

            return $badge;
        });
    }

    public function updateExpiration(RfidBadge $badge, ?\Carbon\CarbonInterface $expiresAt, ?User $performedBy = null, string $source = 'user'): RfidBadge
    {
        return DB::transaction(function () use ($badge, $expiresAt, $performedBy, $source) {
            $old = $badge->only(['expires_at']);

            $badge->expires_at = $expiresAt;

            if (!$badge->isDirty('expires_at')) {
                return $badge;
            }

            $badge->save();

            $this->writeHistory($badge, 'expiration_changed', $old, $badge->only(['expires_at']), $source, $performedBy);

            return $badge;
        });
    }

    private function writeHistory(RfidBadge $badge, string $eventType, ?array $oldValues, ?array $newValues, string $source, ?User $performedBy): void
    {
        $badge->histories()->create([
            'event_type' => $eventType,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'source' => $source,
            'performed_by' => $performedBy?->id,
        ]);
    }
}
