<?php

namespace App\Services;

use App\Events\StationMonitoringUpdated;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StationMonitoringService
{
    public function __construct(
        protected ChargingStationService $chargingStationService,
        protected ConnectorService $connectorService,
        protected StationConnectionStatusService $connectionStatusService,
    ) {
    }

    /**
     * Record an incoming heartbeat for a station.
     *
     * Sets last_heartbeat_at and last_seen_at to now, and clears
     * disconnected_at if it was set — this is how a station automatically
     * "comes back online" the moment a heartbeat is received again, with no
     * separate reverse "mark connected" pass needed anywhere else.
     */
    public function recordHeartbeat(ChargingStation $station): ChargingStation
    {
        if ($station->trashed()) {
            throw new InvalidStateTransitionException(
                'Une borne supprimée ne peut pas recevoir de heartbeat.'
            );
        }

        DB::transaction(function () use ($station) {
            $station->last_heartbeat_at = now();
            $station->last_seen_at = now();
            $station->disconnected_at = null;
            $station->save();
        });

        $this->broadcastStation($station);

        return $station;
    }

    /**
     * Record that meaningful, non-heartbeat OCPP traffic was received from a
     * station — BootNotification, Authorize, StartTransaction, MeterValues,
     * StopTransaction, and similar messages. Updates last_seen_at only.
     *
     * This method deliberately never touches last_heartbeat_at or
     * disconnected_at. connection_status stays derived solely from
     * last_heartbeat_at freshness, and recordHeartbeat() remains the only
     * method that ever clears disconnected_at — this keeps "is the station
     * sending OCPP traffic at all" (last_seen_at) and "is the station within
     * its heartbeat contract" (connection_status) as two independent signals.
     */
    public function recordSeen(ChargingStation $station): ChargingStation
    {
        if ($station->trashed()) {
            throw new InvalidStateTransitionException(
                'Une borne supprimée ne peut pas recevoir de trafic OCPP.'
            );
        }

        DB::transaction(function () use ($station) {
            $station->last_seen_at = now();
            $station->save();
        });

        $this->broadcastStation($station);

        return $station;
    }

    /**
     * Derive a station's current connection status from last_heartbeat_at.
     *
     * Delegates to StationConnectionStatusService, the single source of
     * truth for connection status shared by ChargingStationService and
     * ConnectorService as well. Kept here as a thin backward-compatible
     * wrapper so existing callers (SimulateChargingSessions,
     * SupervisionStationResource, SupervisionDashboardService,
     * ChargingSessionService, and this class itself) are unaffected.
     */
    public function connectionStatus(ChargingStation $station): string
    {
        return $this->connectionStatusService->connectionStatus($station);
    }

    /**
     * Mark a station as disconnected.
     *
     * Idempotent: if disconnected_at is already set, this does nothing and
     * returns the station unchanged, with no new broadcast — disconnected_at
     * is set at most once per outage episode. recordHeartbeat() is the only
     * method that clears it.
     */
    public function markDisconnected(ChargingStation $station): ChargingStation
    {
        if ($station->disconnected_at !== null) {
            return $station;
        }

        DB::transaction(function () use ($station) {
            $station->disconnected_at = now();
            $station->save();
        });

        $this->broadcastStation($station);

        return $station;
    }

    /**
     * Record a station-level operational status change, delegating the
     * actual write and history logging to the existing Module 2
     * ChargingStationService, then broadcasting the update.
     *
     * If no $reason is supplied, a system-generated French reason is used
     * so the "reason is always required" history invariant still holds for
     * automatic, non-human-triggered status changes.
     */
    public function recordOperationalStatus(
        ChargingStation $station,
        string $operationalStatus,
        ?User $performedBy = null,
        string $source = 'ocpp',
        ?string $reason = null
    ): ChargingStation {
        $reason ??= "Mise à jour automatique du statut opérationnel (source : {$source}).";

        $station = $this->chargingStationService->updateOperationalStatus(
            $station,
            $operationalStatus,
            $reason,
            null,
            $performedBy,
            $source
        );

        $this->broadcastStation($station);

        return $station;
    }

    /**
     * Record a connector-level operational status change, delegating to the
     * existing Module 3 ConnectorService (which writes no history — Module 3
     * has no connector_histories table), then broadcasting the update.
     *
     * Broadcasts the parent station, not the connector, since
     * StationMonitoringUpdated / SupervisionStationResource is the single
     * canonical station shape shared by the dashboard and this event.
     *
     * Explicitly passes source: 'ocpp' since this write originates from
     * real OCPP StatusNotification traffic, not a human dropdown.
     */
    public function recordConnectorOperationalStatus(Connector $connector, string $operationalStatus): Connector
    {
        $connector = $this->connectorService->updateOperationalStatus(
            $connector,
            $operationalStatus,
            source: 'ocpp'
        );

        $station = ChargingStation::find($connector->charging_station_id);

        $this->broadcastStation($station);

        return $connector;
    }

    /**
     * Find every station whose last heartbeat has gone stale and mark each
     * one disconnected. Only considers stations that have actually had a
     * heartbeat before — a station that has never connected has nothing to
     * disconnect from. Decommissioned and soft-deleted stations are excluded,
     * since a decommissioned station is no longer meaningfully "online" or
     * "offline" in an operational sense.
     *
     * Called by the stations:detect-offline scheduled command.
     */
    public function refreshStaleStations(): int
    {
        $timeoutSeconds = (int) config('monitoring.heartbeat_timeout_seconds');
        $cutoff = now()->subSeconds($timeoutSeconds);

        $staleStations = ChargingStation::query()
            ->whereNull('deleted_at')
            ->where('administrative_status', '!=', 'decommissioned')
            ->whereNull('disconnected_at')
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '<', $cutoff)
            ->get();

        foreach ($staleStations as $station) {
            $this->markDisconnected($station);
        }

        return $staleStations->count();
    }

    /**
     * Reload the given station fresh with its connector count and broadcast
     * it. This is the single point through which every monitoring broadcast
     * passes, guaranteeing the WebSocket event always carries the exact same
     * SupervisionStationResource shape as the REST dashboard snapshot —
     * including actual_connector_count and connector_count_matches, which
     * require withCount('connectors') to be present.
     */
    protected function broadcastStation(ChargingStation $station): void
    {
        $freshStation = ChargingStation::withCount('connectors')->find($station->id);

        if ($freshStation === null) {
            return;
        }

        broadcast(new StationMonitoringUpdated($freshStation));
    }
}
