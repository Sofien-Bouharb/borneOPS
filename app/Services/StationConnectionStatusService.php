<?php

namespace App\Services;

use App\Models\ChargingStation;

class StationConnectionStatusService
{
    /**
     * Derive a station's current connection status from last_heartbeat_at.
     *
     * This is the single source of truth for connection status. It is never
     * stored as a boolean column — it is always computed at read time.
     * A heartbeat exactly at the timeout boundary counts as connected.
     */
    public function connectionStatus(ChargingStation $station): string
    {
        if ($station->last_heartbeat_at === null) {
            return 'disconnected';
        }

        $timeoutSeconds = (int) config('monitoring.heartbeat_timeout_seconds');
        $cutoff = now()->subSeconds($timeoutSeconds);

        return $station->last_heartbeat_at->greaterThanOrEqualTo($cutoff)
            ? 'connected'
            : 'disconnected';
    }
}
