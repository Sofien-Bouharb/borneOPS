<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\ChargingStation;
use App\Models\ChargingStationHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChargingStationLifecycleService
{
    protected const TRANSITIONS = [
        'commissioning' => ['active', 'decommissioned'],
        'active' => ['disabled', 'decommissioned'],
        'disabled' => ['active', 'decommissioned'],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function decommission(
        ChargingStation $station,
        string $reason,
        ?string $comment,
        ?User $performedBy
    ): ChargingStation {
        if ($station->trashed()) {
            throw new InvalidStateTransitionException(
                'Une borne supprimée ne peut pas changer de statut administratif.'
            );
        }

        $oldStatus = $station->administrative_status;

        if (!$this->canTransition($oldStatus, 'decommissioned')) {
            throw new InvalidStateTransitionException(
                "Impossible de décommissionner une borne dans l'état '{$oldStatus}'."
            );
        }

        return DB::transaction(function () use ($station, $oldStatus, $reason, $comment, $performedBy) {
            $station->administrative_status = 'decommissioned';
            $station->save();

            ChargingStationHistory::create([
                'charging_station_id' => $station->id,
                'event_type' => 'decommissioned',
                'old_values' => ['administrative_status' => $oldStatus],
                'new_values' => ['administrative_status' => 'decommissioned'],
                'reason' => $reason,
                'comment' => $comment,
                'source' => 'user',
                'performed_by' => $performedBy?->id,
            ]);

            return $station;
        });
    }

    public function disable(
    ChargingStation $station,
    string $reason,
    ?string $comment,
    ?User $performedBy
): ChargingStation {
    if ($station->trashed()) {
        throw new InvalidStateTransitionException(
            'A soft-deleted station cannot transition administrative_status.'
        );
    }

    $oldStatus = $station->administrative_status;

    if (!$this->canTransition($oldStatus, 'disabled')) {
        throw new InvalidStateTransitionException(
            "Impossible de désactiver une borne dans l'état '{$oldStatus}'."
        );
    }

    return DB::transaction(function () use ($station, $oldStatus, $reason, $comment, $performedBy) {
        $station->administrative_status = 'disabled';
        $station->save();

        ChargingStationHistory::create([
            'charging_station_id' => $station->id,
            'event_type' => 'disabled',
            'old_values' => ['administrative_status' => $oldStatus],
            'new_values' => ['administrative_status' => 'disabled'],
            'reason' => $reason,
            'comment' => $comment,
            'source' => 'user',
            'performed_by' => $performedBy?->id,
        ]);

        return $station;
    });
}

public function reactivate(
    ChargingStation $station,
    ?string $reason,
    ?string $comment,
    ?User $performedBy
): ChargingStation {
    if ($station->trashed()) {
        throw new InvalidStateTransitionException(
            'A soft-deleted station cannot transition administrative_status.'
        );
    }

    $oldStatus = $station->administrative_status;

    if (!$this->canTransition($oldStatus, 'active')) {
        throw new InvalidStateTransitionException(
            "Impossible de réactiver une borne dans l'état '{$oldStatus}'."
        );
    }

    if (empty($station->ocpp_identifier)) {
        throw new InvalidStateTransitionException(
            'Une borne ne peut pas devenir active sans identifiant OCPP.'
        );
    }

    return DB::transaction(function () use ($station, $oldStatus, $reason, $comment, $performedBy) {
        $station->administrative_status = 'active';
        $station->save();

        ChargingStationHistory::create([
            'charging_station_id' => $station->id,
            'event_type' => 'reactivated',
            'old_values' => ['administrative_status' => $oldStatus],
            'new_values' => ['administrative_status' => 'active'],
            'reason' => $reason,
            'comment' => $comment,
            'source' => 'user',
            'performed_by' => $performedBy?->id,
        ]);

        return $station;
    });
}
}
