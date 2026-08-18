<?php
namespace App\Http\Controllers\Ocpp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ocpp\AuthorizeEventRequest;
use App\Http\Requests\Ocpp\BootNotificationEventRequest;
use App\Http\Requests\Ocpp\HeartbeatEventRequest;
use App\Http\Requests\Ocpp\StartTransactionEventRequest;
use App\Http\Requests\Ocpp\StatusNotificationEventRequest;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Services\ChargingSessionService;
use App\Services\RfidAuthorizationService;
use App\Services\StationMonitoringService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Ocpp\MeterValuesEventRequest;
use App\Http\Requests\Ocpp\StopTransactionEventRequest;
use App\Http\Requests\Ocpp\StartTransactionExternalEventRequest;
use App\Http\Requests\Ocpp\VerifyStationCredentialRequest;
class OcppBridgeController extends Controller
{
    public function __construct(
        protected StationMonitoringService $monitoringService,
        protected ChargingSessionService $sessionService,
        protected RfidAuthorizationService $authorizationService,
    ) {
    }

public function verifyStationCredential(VerifyStationCredentialRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $station = ChargingStation::where('ocpp_identifier', $validated['ocpp_identifier'])->first();

        if ($station === null) {
            return response()->json(['authorized' => false, 'reason' => 'unknown_station'], 200);
        }

        if ($station->administrative_status === 'decommissioned') {
            return response()->json(['authorized' => false, 'reason' => 'station_decommissioned'], 200);
        }

        if ($station->ocpp_auth_password_hash === null) {
            return response()->json(['authorized' => false, 'reason' => 'no_credential_configured'], 200);
        }

        if (!\Illuminate\Support\Facades\Hash::check($validated['password'], $station->ocpp_auth_password_hash)) {
            return response()->json(['authorized' => false, 'reason' => 'invalid_credential'], 200);
        }

        if ($station->ocpp_version !== $validated['negotiated_version']) {
            return response()->json(['authorized' => false, 'reason' => 'version_mismatch'], 200);
        }

        return response()->json(['authorized' => true], 200);
    }

    public function authorize(AuthorizeEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $station = ChargingStation::where('ocpp_identifier', $validated['ocpp_identifier'])->first();

        if ($station === null) {
            return response()->json([
                'message' => "No station found with ocpp_identifier '{$validated['ocpp_identifier']}'.",
            ], 404);
        }

        $decision = $this->authorizationService->authorize($validated['identifier'], $station);

        return response()->json([
            'accepted' => $decision->accepted,
            'reason' => $decision->reason->value,
        ], 200);
    }

    public function heartbeat(HeartbeatEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $station = ChargingStation::where('ocpp_identifier', $validated['ocpp_identifier'])->first();

        if ($station === null) {
            return response()->json([
                'message' => "No station found with ocpp_identifier '{$validated['ocpp_identifier']}'.",
            ], 404);
        }

        $this->monitoringService->recordHeartbeat($station);

        return response()->json(['message' => 'Heartbeat recorded.']);
    }

    public function bootNotification(BootNotificationEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $station = ChargingStation::where('ocpp_identifier', $validated['ocpp_identifier'])->first();

        if ($station === null) {
            return response()->json([
                'message' => "No station found with ocpp_identifier '{$validated['ocpp_identifier']}'.",
            ], 404);
        }

        $this->monitoringService->recordSeen($station);

        return response()->json(['message' => 'Boot notification recorded.']);
    }

    public function statusNotification(StatusNotificationEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $station = ChargingStation::where('ocpp_identifier', $validated['ocpp_identifier'])->first();

        if ($station === null) {
            return response()->json([
                'message' => "No station found with ocpp_identifier '{$validated['ocpp_identifier']}'.",
            ], 404);
        }

        if ($validated['connector_number'] ?? null) {
            $connector = Connector::where('charging_station_id', $station->id)
                ->where('connector_number', $validated['connector_number'])
                ->first();

            if ($connector === null) {
                return response()->json([
                    'message' => "No connector number {$validated['connector_number']} found on station '{$validated['ocpp_identifier']}'.",
                ], 404);
            }

            $this->monitoringService->recordConnectorOperationalStatus($connector, $validated['operational_status']);

            return response()->json(['message' => 'Connector status notification recorded.']);
        }

        $this->monitoringService->recordOperationalStatus($station, $validated['operational_status']);

        return response()->json(['message' => 'Station status notification recorded.']);
    }

public function startTransaction(StartTransactionEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $station = ChargingStation::where('ocpp_identifier', $validated['ocpp_identifier'])->first();

        if ($station === null) {
            return response()->json([
                'message' => "No station found with ocpp_identifier '{$validated['ocpp_identifier']}'.",
            ], 404);
        }

        $connector = Connector::where('charging_station_id', $station->id)
            ->where('connector_number', $validated['connector_number'])
            ->first();

        if ($connector === null) {
            return response()->json([
                'message' => "No connector number {$validated['connector_number']} found on station '{$validated['ocpp_identifier']}'.",
            ], 404);
        }

        $session = $this->sessionService->bindOcppTransactionId(
            $station,
            $connector,
            $validated['meter_start_wh'],
            $validated['identifier'] ?? null,
        );

        return response()->json([
            'message' => 'Transaction started.',
            'session_id' => $session->id,
            'ocpp_transaction_id' => $session->ocpp_transaction_id,
        ]);
    }


public function meterValues(MeterValuesEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $station = ChargingStation::where('ocpp_identifier', $validated['ocpp_identifier'])->first();

        if ($station === null) {
            return response()->json([
                'message' => "No station found with ocpp_identifier '{$validated['ocpp_identifier']}'.",
            ], 404);
        }

        $session = \App\Models\ChargingSession::where('charging_station_id', $station->id)
            ->where('ocpp_transaction_id', $validated['ocpp_transaction_id'])
            ->first();

        if ($session === null) {
            return response()->json([
                'message' => "No session found for transaction '{$validated['ocpp_transaction_id']}' on station '{$validated['ocpp_identifier']}'.",
            ], 404);
        }

        $this->sessionService->recordMeterValue($session, $validated['meter_value_wh']);

        return response()->json(['message' => 'Meter value recorded.']);
    }


public function stopTransaction(StopTransactionEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $station = ChargingStation::where('ocpp_identifier', $validated['ocpp_identifier'])->first();

        if ($station === null) {
            return response()->json([
                'message' => "No station found with ocpp_identifier '{$validated['ocpp_identifier']}'.",
            ], 404);
        }

        $session = \App\Models\ChargingSession::where('charging_station_id', $station->id)
            ->where('ocpp_transaction_id', $validated['ocpp_transaction_id'])
            ->first();

        if ($session === null) {
            \Illuminate\Support\Facades\Log::warning('orphan_stop_transaction', [
                'ocpp_identifier' => $validated['ocpp_identifier'],
                'ocpp_transaction_id' => $validated['ocpp_transaction_id'],
                'meter_stop_wh' => $validated['meter_stop_wh'],
                'reason_code' => $validated['reason_code'],
            ]);

            return response()->json([
                'message' => 'No matching session found for this transaction. Acknowledged, no action taken.',
            ]);
        }

        if ($session->status === 'completed') {
            return response()->json([
                'message' => 'Transaction already completed.',
                'session_id' => $session->id,
            ]);
        }

        $session = $this->sessionService->complete(
            $session,
            $validated['meter_stop_wh'],
            $validated['reason_code'],
            null,
            null,
            'ocpp',
        );

        return response()->json([
            'message' => 'Transaction stopped.',
            'session_id' => $session->id,
        ]);
    }



public function startTransactionExternal(StartTransactionExternalEventRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $station = ChargingStation::where('ocpp_identifier', $validated['ocpp_identifier'])->first();

        if ($station === null) {
            return response()->json([
                'message' => "No station found with ocpp_identifier '{$validated['ocpp_identifier']}'.",
            ], 404);
        }

        $connectorsAtEvse = Connector::where('charging_station_id', $station->id)
            ->where('ocpp_evse_id', $validated['evse_id'])
            ->get();

        $connectorId = $validated['connector_id'] ?? null;

        if ($connectorId !== null) {
            $connector = $connectorsAtEvse->firstWhere('ocpp_connector_id', $connectorId);

            if ($connector === null) {
                return response()->json([
                    'message' => "No connector found for EVSE {$validated['evse_id']} / connector {$connectorId} on station '{$validated['ocpp_identifier']}'.",
                ], 404);
            }
        } else {
            if ($connectorsAtEvse->isEmpty()) {
                return response()->json([
                    'message' => "No connector found for EVSE {$validated['evse_id']} on station '{$validated['ocpp_identifier']}'.",
                ], 404);
            }

            if ($connectorsAtEvse->count() > 1) {
                return response()->json([
                    'message' => "EVSE {$validated['evse_id']} on station '{$validated['ocpp_identifier']}' has multiple connectors and no connector_id was supplied. Addressing is ambiguous.",
                ], 409);
            }

            $connector = $connectorsAtEvse->first();
        }

        $session = $this->sessionService->bindExternalTransactionId(
            $station,
            $connector,
            $validated['external_transaction_id'],
            $validated['meter_start_wh'],
            $validated['identifier'] ?? null,
        );

        return response()->json([
            'message' => 'Transaction started.',
            'session_id' => $session->id,
            'ocpp_transaction_id' => $session->ocpp_transaction_id,
        ]);
    }




}
