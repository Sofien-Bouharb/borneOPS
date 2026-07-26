<?php

namespace App\Services;

use App\Models\ChargingStation;
use App\Models\ChargingStationHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Exceptions\InvalidStateTransitionException;

class ChargingStationService
{
    public function create(array $data, ?User $performedBy): ChargingStation
    {
        return DB::transaction(function () use ($data, $performedBy) {
            $station = ChargingStation::create([
                ...$data,
                'operational_status' => 'disconnected',
                'administrative_status' => 'commissioning',
            ]);

            ChargingStationHistory::create([
                'charging_station_id' => $station->id,
                'event_type' => 'created',
                'old_values' => null,
                'new_values' => $station->only([
                    'name', 'reference', 'serial_number', 'model', 'manufacturer',
                    'address', 'latitude', 'longitude', 'firmware_version',
                    'ocpp_version', 'ocpp_identifier', 'declared_connector_count',
                    'power_kw', 'operational_status', 'administrative_status', 'site_id',
                ]),
                'reason' => null,
                'comment' => null,
                'source' => 'user',
                'performed_by' => $performedBy?->id,
            ]);

            return $station;
        });
    }

    public function update(ChargingStation $station, array $data, ?User $performedBy): ChargingStation
{
    return DB::transaction(function () use ($station, $data, $performedBy) {
        $reason = $data['reason'] ?? null;
        $comment = $data['comment'] ?? null;

        $station->fill($data);

        if (!$station->isDirty()) {
            return $station;
        }

        $oldValues = [];
        $newValues = [];
        foreach ($station->getDirty() as $field => $newValue) {
            $oldValues[$field] = $station->getOriginal($field);
            $newValues[$field] = $newValue;
        }

        $station->save();

        ChargingStationHistory::create([
            'charging_station_id' => $station->id,
            'event_type' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'comment' => $comment,
            'source' => 'user',
            'performed_by' => $performedBy?->id,
        ]);

        return $station;
    });
}
public function updateOperationalStatus(
    ChargingStation $station,
    string $operationalStatus,
    string $reason,
    ?string $comment,
    ?User $performedBy
): ChargingStation {
    if ($station->trashed()) {
        throw new InvalidStateTransitionException(
            'A soft-deleted station cannot have its operational_status changed.'
        );
    }

    $oldStatus = $station->operational_status;

    return DB::transaction(function () use ($station, $oldStatus, $operationalStatus, $reason, $comment, $performedBy) {
        $station->operational_status = $operationalStatus;
        $station->save();

        ChargingStationHistory::create([
            'charging_station_id' => $station->id,
            'event_type' => 'state_changed',
            'old_values' => ['operational_status' => $oldStatus],
            'new_values' => ['operational_status' => $operationalStatus],
            'reason' => $reason,
            'comment' => $comment,
            'source' => 'user',
            'performed_by' => $performedBy?->id,
        ]);

        return $station;
    });
}
public function assign(
    ChargingStation $station,
    ?int $siteId,
    ?string $reason,
    ?string $comment,
    ?User $performedBy
): ChargingStation {
    if ($station->trashed()) {
        throw new InvalidStateTransitionException(
            'A soft-deleted station cannot be reassigned.'
        );
    }

    $oldSiteId = $station->site_id;

    return DB::transaction(function () use ($station, $oldSiteId, $siteId, $reason, $comment, $performedBy) {
        $station->site_id = $siteId;

        // Per §5: the site's address becomes authoritative once assigned;
        // the station's own address fallback is cleared, and never restored on unassignment.
        $station->address = null;

        $station->save();

        ChargingStationHistory::create([
            'charging_station_id' => $station->id,
            'event_type' => 'assigned',
            'old_values' => ['site_id' => $oldSiteId],
            'new_values' => ['site_id' => $siteId],
            'reason' => $reason,
            'comment' => $comment,
            'source' => 'user',
            'performed_by' => $performedBy?->id,
        ]);

        return $station;
    });
}


}
