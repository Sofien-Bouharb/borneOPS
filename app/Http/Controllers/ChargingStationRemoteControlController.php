<?php

namespace App\Http\Controllers;

use App\Http\Requests\RemoteStartCommandRequest;
use App\Http\Requests\ResetCommandRequest;
use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Services\OcppGatewayClient;
use Illuminate\Http\JsonResponse;

class ChargingStationRemoteControlController extends Controller
{
    public function __construct(
        protected OcppGatewayClient $gatewayClient,
    ) {
    }

    public function remoteStart(RemoteStartCommandRequest $request, ChargingStation $station): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->gatewayClient->remoteStart($station, $validated['id_tag']);

        return response()->json($result);
    }

    public function remoteStop(ChargingStation $station, ChargingSession $session): JsonResponse
    {
        if ($session->charging_station_id !== $station->id) {
            abort(404);
        }

        if ($session->ocpp_transaction_id === null) {
            return response()->json([
                'message' => "Cette session n'a pas d'identifiant de transaction OCPP.",
            ], 409);
        }

        $result = $this->gatewayClient->remoteStop($station, $session->ocpp_transaction_id);

        return response()->json($result);
    }

    public function reset(ResetCommandRequest $request, ChargingStation $station): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->gatewayClient->reset($station, $validated['type']);

        return response()->json($result);
    }

    public function unlockConnector(ChargingStation $station, Connector $connector): JsonResponse
    {
        if ($connector->charging_station_id !== $station->id) {
            abort(404);
        }

        $result = $this->gatewayClient->unlockConnector(
            $station,
            $connector->connector_number,
            $connector->ocpp_evse_id,
            $connector->ocpp_connector_id,
        );

        return response()->json($result);
    }
}
