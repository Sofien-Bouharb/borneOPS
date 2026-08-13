<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use App\Models\ChargingStation;
use Illuminate\Support\Facades\Http;

class OcppGatewayClient
{
    public function remoteStart(ChargingStation $station, string $idTag, ?int $connectorNumber = null): array
    {
        return $this->post($station, 'remote-start', [
            'id_tag' => $idTag,
            'connector_number' => $connectorNumber,
        ]);
    }

    public function remoteStop(ChargingStation $station, string $ocppTransactionId): array
    {
        return $this->post($station, 'remote-stop', [
            'ocpp_transaction_id' => $ocppTransactionId,
        ]);
    }

    public function reset(ChargingStation $station, string $type = 'Soft'): array
    {
        return $this->post($station, 'reset', [
            'type' => $type,
        ]);
    }

    public function unlockConnector(
        ChargingStation $station,
        int $connectorNumber,
        ?int $evseId = null,
        ?int $ocppConnectorId = null
    ): array {
        return $this->post($station, 'unlock-connector', [
            'connector_number' => $connectorNumber,
            'evse_id' => $evseId,
            'connector_id' => $ocppConnectorId,
        ]);
    }

    protected function post(ChargingStation $station, string $suffix, array $payload): array
    {
        if ($station->ocpp_identifier === null) {
            throw new InvalidStateTransitionException(
                "Cette borne n'a pas d'identifiant OCPP configuré."
            );
        }

        $baseUrl = config('services.ocpp_gateway.base_url');
        $token = config('services.ocpp_bridge.token');
        $url = "{$baseUrl}/commands/{$station->ocpp_identifier}/{$suffix}";

        $response = Http::withHeaders([
            'X-OCPP-Bridge-Token' => $token,
        ])->post($url, $payload);

        if ($response->status() === 404) {
            throw new InvalidStateTransitionException(
                "La borne '{$station->ocpp_identifier}' n'est pas actuellement connectée à la passerelle OCPP."
            );
        }

        if ($response->status() === 422) {
            throw new InvalidStateTransitionException(
                $response->json('detail') ?? 'La commande OCPP a échoué : requête invalide.'
            );
        }

        if ($response->failed()) {
            throw new InvalidStateTransitionException(
                $response->json('detail') ?? 'La commande OCPP a échoué.'
            );
        }

        return $response->json();
    }
}
