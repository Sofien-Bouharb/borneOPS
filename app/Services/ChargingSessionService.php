<?php

namespace App\Services;

use App\Events\ChargingSessionUpdated;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\ChargingSession;
use App\Models\ChargingSessionEvent;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChargingSessionService
{
    /**
     * Reason codes accepted when completing a session (every code except
     * equipment_unavailable, which describes a session that never actually
     * started charging and is therefore cancellation-only). Module 5
     * roadmap §11.
     */
    protected const COMPLETION_REASON_CODES = [
        'user_requested',
        'operator_requested',
        'remote_stop',
        'vehicle_disconnected',
        'equipment_fault',
        'power_loss',
        'communication_loss',
        'other',
    ];

    /**
     * Reason codes accepted when cancelling a session — a narrower set than
     * completion, since a cancelled session was always 'pending' and never
     * actually drew any power. Module 5 roadmap §11.
     */
    protected const CANCELLATION_REASON_CODES = [
        'user_requested',
        'operator_requested',
        'equipment_unavailable',
        'other',
    ];

    public function __construct(
        protected StationMonitoringService $stationMonitoringService,
        protected ChargingStationService $chargingStationService,
        protected ConnectorService $connectorService,
    ) {
    }

    /**
     * Create a new session in 'pending' status.
     *
     * Runs the full eligibility predicate (§3.10) before ever touching the
     * database. Does not itself occupy the connector or station — that only
     * happens once the session is actually started.
     *
     * @param  array{charging_station_id: int, connector_id: int, customer_user_id?: int|null}  $data
     *
     * @throws InvalidStateTransitionException
     */
    public function create(array $data, ?User $performedBy, string $source = 'user'): ChargingSession
    {
        $station = ChargingStation::find($data['charging_station_id']);
        $connector = Connector::find($data['connector_id']);

        if ($station === null || $connector === null) {
            throw new InvalidStateTransitionException(
                'Borne ou connecteur introuvable.'
            );
        }

        $this->assertEligible($station, $connector);

        $session = DB::transaction(function () use ($station, $connector, $data, $performedBy, $source) {
            $session = ChargingSession::create([
                'charging_station_id' => $station->id,
                'connector_id' => $connector->id,
                'customer_user_id' => $data['customer_user_id'] ?? null,
                'status' => 'pending',
            ]);

            ChargingSessionEvent::create([
                'charging_session_id' => $session->id,
                'event_type' => 'created',
                'from_status' => null,
                'to_status' => 'pending',
                'source' => $source,
                'performed_by' => $performedBy?->id,
            ]);

            return $session;
        });

        $this->broadcastSession($session);

        return $session;
    }

    /**
     * Start a pending session: sets meter_start_wh, marks it active, and
     * occupies both the connector and the parent station.
     *
     * Repeats the exact same eligibility predicate used by create() (§3.10)
     * since time may have passed and the station/connector state may have
     * drifted since the session was first created.
     *
     * The connector/station occupied-status writes below always use
     * source: 'system' — they are an automatic side-effect of the session
     * lifecycle, never a human manually editing a dropdown, regardless of
     * whether the session itself was started by a person or by OCPP. Since
     * assertEligible() already requires the station to be OCPP-connected
     * before a session can start, a 'user'-sourced write here would always
     * be rejected by the OCPP-authoritative-status gate.
     *
     * @throws InvalidStateTransitionException
     */
public function start(
        ChargingSession $session,
        int $meterStartWh,
        ?User $performedBy,
        string $source = 'user',
        ?string $ocppTransactionId = null,
        ?\Carbon\CarbonInterface $occurredAt = null
    ): ChargingSession
    {
        $session = DB::transaction(function () use ($session, $meterStartWh, $performedBy, $source, $ocppTransactionId, $occurredAt) {
            $locked = ChargingSession::lockForUpdate()->find($session->id);
            if ($locked->status !== 'pending') {
                throw new InvalidStateTransitionException(
                    "Impossible de démarrer une session dans l'état '{$locked->status}'. Seule une session 'pending' peut être démarrée."
                );
            }
            $station = ChargingStation::find($locked->charging_station_id);
            $connector = Connector::find($locked->connector_id);
            $this->assertEligible($station, $connector, $locked->id);
            $oldStatus = $locked->status;
            $locked->status = 'active';
            $locked->meter_start_wh = $meterStartWh;
            $locked->latest_meter_wh = $meterStartWh;
            $locked->started_at = $occurredAt ?? now();
            if ($ocppTransactionId !== null) {
                $locked->ocpp_transaction_id = $ocppTransactionId;
            }
            $locked->save();
            ChargingSessionEvent::create([
                'charging_session_id' => $locked->id,
                'event_type' => 'started',
                'from_status' => $oldStatus,
                'to_status' => 'active',
                'meter_value_wh' => $meterStartWh,
                'source' => $source,
                'performed_by' => $performedBy?->id,
            ]);
            $this->connectorService->updateOperationalStatus($connector, 'occupied', 'system');
            $this->chargingStationService->updateOperationalStatus(
                $station,
                'occupied',
                "Démarrage d'une session de recharge sur le connecteur #{$connector->connector_number}.",
                null,
                $performedBy,
                'system'
            );
            return $locked;
        });
        $this->broadcastSession($session);
        return $session;
    }


/**
     * Bind an OCPP 1.6 transaction to a BorneOPS session, starting it in the
     * process. Per the frozen OCPP Integration Roadmap v1.1:
     *
     * OCPP 1.6's StartTransaction carries no transactionId from the charger
     * — the CSMS (this application) assigns one and returns it in the
     * response. Decision #9 fixes that assigned value to be the
     * ChargingSession's own id, so no separate id-generation scheme is
     * needed; the gateway simply relays session_id back to the charger as
     * the OCPP transactionId.
     *
     * Idempotency (§17): if the connector already has an active session,
     * this is treated as a retried StartTransaction and that session is
     * returned unchanged — the charger has no prior transactionId to send
     * back to us for a retried Start, so "already active on this connector"
     * is the only signal available to detect the retry.
     *
     * Amendment #10 (unsolicited sessions): if no pending BorneOPS session
     * exists on the connector, one is created here with
     * customer_user_id = null, source = 'ocpp'.
     *
     * @throws InvalidStateTransitionException
     */
    public function bindOcppTransactionId(
        ChargingStation $station,
        Connector $connector,
        int $meterStartWh,
        ?\Carbon\CarbonInterface $occurredAt = null
    ): ChargingSession {
        $activeExisting = ChargingSession::where('connector_id', $connector->id)
            ->where('status', 'active')
            ->first();

        if ($activeExisting !== null) {
            return $activeExisting;
        }

        $pending = ChargingSession::where('connector_id', $connector->id)
            ->where('status', 'pending')
            ->first();

        if ($pending === null) {
            $pending = $this->create([
                'charging_station_id' => $station->id,
                'connector_id' => $connector->id,
                'customer_user_id' => null,
            ], null, 'ocpp');
        }

        $session = $this->start($pending, $meterStartWh, null, 'ocpp', null, $occurredAt);

        $session->ocpp_transaction_id = (string) $session->id;
        $session->save();

        return $session;
    }



/**
     * Bind an OCPP 2.0.1 transaction to a BorneOPS session, starting it in
     * the process. Unlike OCPP 1.6, where the CSMS assigns the transaction
     * ID, OCPP 2.0.1's TransactionEvent (Started) carries a transaction ID
     * the CHARGER itself generated — we store it as-is rather than
     * self-assigning session.id, per decision #9's "separate adapter"
     * split.
     *
     * Idempotency: if a session already exists for this exact transaction
     * ID on this station (any status), it is returned unchanged — this
     * covers a retried Started event.
     *
     * Amendment #10 (unsolicited sessions) applies identically to this
     * pathway: if no pending BorneOPS session exists, one is created here.
     *
     * @throws InvalidStateTransitionException
     */
    public function bindExternalTransactionId(
        ChargingStation $station,
        Connector $connector,
        string $externalTransactionId,
        int $meterStartWh,
        ?\Carbon\CarbonInterface $occurredAt = null
    ): ChargingSession {
        $existing = ChargingSession::where('charging_station_id', $station->id)
            ->where('ocpp_transaction_id', $externalTransactionId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $pending = ChargingSession::where('connector_id', $connector->id)
            ->where('status', 'pending')
            ->first();

        if ($pending === null) {
            $pending = $this->create([
                'charging_station_id' => $station->id,
                'connector_id' => $connector->id,
                'customer_user_id' => null,
            ], null, 'ocpp');
        }

        return $this->start($pending, $meterStartWh, null, 'ocpp', $externalTransactionId, $occurredAt);
    }




    /**
     * Pause an active session. Does not touch connector/station status —
     * the equipment stays 'occupied' while paused, only the session's own
     * status changes.
     *
     * @throws InvalidStateTransitionException
     */
    public function pause(ChargingSession $session, ?User $performedBy, string $source = 'user'): ChargingSession
    {
        $session = DB::transaction(function () use ($session, $performedBy, $source) {
            $locked = ChargingSession::lockForUpdate()->find($session->id);

            if ($locked->status !== 'active') {
                throw new InvalidStateTransitionException(
                    "Impossible de mettre en pause une session dans l'état '{$locked->status}'. Seule une session 'active' peut être mise en pause."
                );
            }

            $oldStatus = $locked->status;

            $locked->status = 'paused';
            $locked->paused_at = now();
            $locked->save();

            ChargingSessionEvent::create([
                'charging_session_id' => $locked->id,
                'event_type' => 'paused',
                'from_status' => $oldStatus,
                'to_status' => 'paused',
                'source' => $source,
                'performed_by' => $performedBy?->id,
            ]);

            return $locked;
        });

        $this->broadcastSession($session);

        return $session;
    }

    /**
     * Resume a paused session. Folds the just-finished pause window into
     * total_paused_seconds before clearing paused_at.
     *
     * @throws InvalidStateTransitionException
     */
    public function resume(ChargingSession $session, ?User $performedBy, string $source = 'user'): ChargingSession
    {
        $session = DB::transaction(function () use ($session, $performedBy, $source) {
            $locked = ChargingSession::lockForUpdate()->find($session->id);

            if ($locked->status !== 'paused') {
                throw new InvalidStateTransitionException(
                    "Impossible de reprendre une session dans l'état '{$locked->status}'. Seule une session 'paused' peut être reprise."
                );
            }

            $oldStatus = $locked->status;

            $pausedDuration = $locked->paused_at !== null
                ? (int) $locked->paused_at->diffInSeconds(now())
                : 0;

            $locked->total_paused_seconds += $pausedDuration;
            $locked->status = 'active';
            $locked->paused_at = null;
            $locked->save();

            ChargingSessionEvent::create([
                'charging_session_id' => $locked->id,
                'event_type' => 'resumed',
                'from_status' => $oldStatus,
                'to_status' => 'active',
                'source' => $source,
                'performed_by' => $performedBy?->id,
            ]);

            return $locked;
        });

        $this->broadcastSession($session);

        return $session;
    }

    /**
     * Record a live meter reading for an active or paused session (§6).
     * Equal readings are a silent no-op (no save, no broadcast). Decreasing
     * readings are rejected. A strictly increasing reading is accepted even
     * while paused — the meter is authoritative over receipt time, and
     * receiving one does not resume the session.
     *
     * Deliberately does NOT create a charging_session_events row — live
     * telemetry would make that table grow unbounded (§12).
     *
     * @throws InvalidStateTransitionException
     */
    public function recordMeterValue(ChargingSession $session, int $meterWh): ChargingSession
    {
        $changed = false;

        $session = DB::transaction(function () use ($session, $meterWh, &$changed) {
            $locked = ChargingSession::lockForUpdate()->find($session->id);

            if (!in_array($locked->status, ['active', 'paused'], true)) {
                throw new InvalidStateTransitionException(
                    "Un relevé de compteur ne peut être enregistré que pour une session active ou en pause (état actuel : '{$locked->status}')."
                );
            }

            $currentReading = $locked->latest_meter_wh ?? $locked->meter_start_wh;

            if ($currentReading !== null && $meterWh < $currentReading) {
                throw new InvalidStateTransitionException(
                    'Le relevé du compteur ne peut pas diminuer.'
                );
            }

            if ($currentReading !== null && $meterWh === $currentReading) {
                return $locked;
            }

            $locked->latest_meter_wh = $meterWh;
            $locked->save();
            $changed = true;

            return $locked;
        });

        if ($changed) {
            $this->broadcastSession($session);
        }

        return $session;
    }

    /**
     * Complete an active or paused session. Releases the connector to
     * 'available' only if it is still 'occupied' (never overwrites fault /
     * maintenance / out_of_service), and returns the station to 'available'
     * only once no other connector on it still has an open session (§8).
     *
     * The connector release below uses source: 'system' for the same
     * reason as start() — this is an automatic side-effect of the session
     * ending, not a human manually editing the connector's dropdown.
     *
     * @throws InvalidStateTransitionException
     */
    public function complete(
        ChargingSession $session,
        int $meterStopWh,
        string $reasonCode,
        ?string $reasonDetail,
        ?User $performedBy,
        string $source = 'user'
    ): ChargingSession {
        if (!in_array($reasonCode, self::COMPLETION_REASON_CODES, true)) {
            throw new InvalidStateTransitionException(
                "Le motif '{$reasonCode}' n'est pas autorisé pour terminer une session."
            );
        }

        $session = DB::transaction(function () use ($session, $meterStopWh, $reasonCode, $reasonDetail, $performedBy, $source) {
            $locked = ChargingSession::lockForUpdate()->find($session->id);

            if (!in_array($locked->status, ['active', 'paused'], true)) {
                throw new InvalidStateTransitionException(
                    "Impossible de terminer une session dans l'état '{$locked->status}'."
                );
            }

            $latestKnown = $locked->latest_meter_wh ?? $locked->meter_start_wh;

            if ($latestKnown !== null && $meterStopWh < $latestKnown) {
                throw new InvalidStateTransitionException(
                    'Le relevé du compteur final ne peut pas être inférieur au dernier relevé connu.'
                );
            }

            $oldStatus = $locked->status;

            $totalPausedSeconds = $locked->total_paused_seconds;
            if ($locked->status === 'paused' && $locked->paused_at !== null) {
                $totalPausedSeconds += (int) $locked->paused_at->diffInSeconds(now());
            }

            $locked->status = 'completed';
            $locked->meter_stop_wh = $meterStopWh;
            $locked->latest_meter_wh = $meterStopWh;
            $locked->total_paused_seconds = $totalPausedSeconds;
            $locked->paused_at = null;
            $locked->completed_at = now();
            $locked->reason_code = $reasonCode;
            $locked->reason_detail = $reasonDetail;
            $locked->save();

            ChargingSessionEvent::create([
                'charging_session_id' => $locked->id,
                'event_type' => 'completed',
                'from_status' => $oldStatus,
                'to_status' => 'completed',
                'meter_value_wh' => $meterStopWh,
                'reason_code' => $reasonCode,
                'reason_detail' => $reasonDetail,
                'source' => $source,
                'performed_by' => $performedBy?->id,
            ]);

            $connector = Connector::find($locked->connector_id);
            if ($connector !== null && $connector->operational_status === 'occupied') {
                $this->connectorService->updateOperationalStatus($connector, 'available', 'system');
            }

            $station = ChargingStation::find($locked->charging_station_id);
            $otherOpenSessionExists = ChargingSession::where('charging_station_id', $station->id)
                ->where('id', '!=', $locked->id)
                ->whereIn('status', ['pending', 'active', 'paused'])
                ->exists();

            if (!$otherOpenSessionExists && $station->operational_status === 'occupied') {
                $this->chargingStationService->updateOperationalStatus(
                    $station,
                    'available',
                    'Fin de session de recharge : plus aucune session ouverte sur cette borne.',
                    null,
                    $performedBy,
                    'system'
                );
            }

            return $locked;
        });

        $this->broadcastSession($session);

        return $session;
    }

    /**
     * Cancel a pending session. Cancellation is only ever valid from
     * 'pending' (§1) — a session that has already started must always be
     * completed with a terminal reason instead, never relabelled cancelled.
     * Never touches connector/station status since a pending session never
     * occupied them in the first place.
     *
     * @throws InvalidStateTransitionException
     */
    public function cancel(
        ChargingSession $session,
        string $reasonCode,
        ?string $reasonDetail,
        ?User $performedBy,
        string $source = 'user'
    ): ChargingSession {
        if (!in_array($reasonCode, self::CANCELLATION_REASON_CODES, true)) {
            throw new InvalidStateTransitionException(
                "Le motif '{$reasonCode}' n'est pas autorisé pour annuler une session."
            );
        }

        $session = DB::transaction(function () use ($session, $reasonCode, $reasonDetail, $performedBy, $source) {
            $locked = ChargingSession::lockForUpdate()->find($session->id);

            if ($locked->status !== 'pending') {
                throw new InvalidStateTransitionException(
                    "Impossible d'annuler une session dans l'état '{$locked->status}'. Seule une session 'pending' peut être annulée."
                );
            }

            $locked->status = 'cancelled';
            $locked->cancelled_at = now();
            $locked->reason_code = $reasonCode;
            $locked->reason_detail = $reasonDetail;
            $locked->save();

            ChargingSessionEvent::create([
                'charging_session_id' => $locked->id,
                'event_type' => 'cancelled',
                'from_status' => 'pending',
                'to_status' => 'cancelled',
                'reason_code' => $reasonCode,
                'reason_detail' => $reasonDetail,
                'source' => $source,
                'performed_by' => $performedBy?->id,
            ]);

            return $locked;
        });

        $this->broadcastSession($session);

        return $session;
    }

    /**
     * The eligibility predicate (§3.10), shared identically by create() and
     * start(). $excludeSessionId lets start() ignore the very session it is
     * about to activate when checking for "no other open session" — without
     * it, a pending session would always appear to conflict with itself.
     *
     * @throws InvalidStateTransitionException
     */
    protected function assertEligible(ChargingStation $station, Connector $connector, ?int $excludeSessionId = null): void
    {
        if ($station->trashed()) {
            throw new InvalidStateTransitionException(
                'Une borne supprimée ne peut pas démarrer de session de recharge.'
            );
        }

        if ($connector->trashed()) {
            throw new InvalidStateTransitionException(
                'Un connecteur supprimé ne peut pas démarrer de session de recharge.'
            );
        }

        if ($connector->charging_station_id !== $station->id) {
            throw new InvalidStateTransitionException(
                "Ce connecteur n'appartient pas à cette borne."
            );
        }

        if ($station->administrative_status !== 'active') {
            throw new InvalidStateTransitionException(
                "Impossible de démarrer une session : la borne n'est pas active (état actuel : '{$station->administrative_status}')."
            );
        }

        if ($this->stationMonitoringService->connectionStatus($station) !== 'connected') {
            throw new InvalidStateTransitionException(
                "Impossible de démarrer une session : la borne n'est pas connectée."
            );
        }

        if (!in_array($station->operational_status, ['available', 'occupied'], true)) {
            throw new InvalidStateTransitionException(
                "Impossible de démarrer une session : la borne n'est pas disponible (état actuel : '{$station->operational_status}')."
            );
        }

        if ($connector->administrative_status !== 'enabled') {
            throw new InvalidStateTransitionException(
                "Impossible de démarrer une session : le connecteur n'est pas activé."
            );
        }

        if ($connector->operational_status !== 'available') {
            throw new InvalidStateTransitionException(
                "Impossible de démarrer une session : le connecteur n'est pas disponible (état actuel : '{$connector->operational_status}')."
            );
        }

        $openSessionExists = ChargingSession::where('connector_id', $connector->id)
            ->whereIn('status', ['pending', 'active', 'paused'])
            ->when($excludeSessionId !== null, fn ($query) => $query->where('id', '!=', $excludeSessionId))
            ->exists();

        if ($openSessionExists) {
            throw new InvalidStateTransitionException(
                'Ce connecteur a déjà une session de recharge ouverte.'
            );
        }
    }

    /**
     * Reload the session fresh with its display relations and broadcast it.
     * Mirrors StationMonitoringService::broadcastStation() — the single
     * point every monitoring broadcast passes through, guaranteeing the
     * WebSocket event always carries the exact same ChargingSessionResource
     * shape as any REST response.
     */
    protected function broadcastSession(ChargingSession $session): void
    {
        $fresh = ChargingSession::with(['chargingStation', 'connector', 'customer'])->find($session->id);

        if ($fresh === null) {
            return;
        }

        broadcast(new ChargingSessionUpdated($fresh));
    }
}
