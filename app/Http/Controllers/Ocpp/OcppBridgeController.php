<?php

namespace App\Http\Controllers\Ocpp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ocpp\BootNotificationEventRequest;
use App\Http\Requests\Ocpp\HeartbeatEventRequest;
use App\Http\Requests\Ocpp\StartTransactionEventRequest;
use App\Http\Requests\Ocpp\StatusNotificationEventRequest;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Services\ChargingSessionService;
use App\Services\StationMonitoringService;
use Illuminate\Http\JsonResponse;

class OcppBridgeController extends Controller
{
    public function __construct(
        protected StationMonitoringService $monitoringService,
        protected ChargingSessionService $sessionService,
    ) {
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
            $validated['ocpp_transaction_id'],
            $validated['meter_start_wh'],
        );

        return response()->json([
            'message' => 'Transaction started.',
            'session_id' => $session->id,
        ]);
    }
}
