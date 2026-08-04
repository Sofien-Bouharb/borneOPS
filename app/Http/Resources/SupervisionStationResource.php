<?php

namespace App\Http\Resources;

use App\Services\StationMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupervisionStationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $connectionStatus = app(StationMonitoringService::class)->connectionStatus($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'reference' => $this->reference,
            'serial_number' => $this->serial_number,
            'model' => $this->model,
            'manufacturer' => $this->manufacturer,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'administrative_status' => $this->administrative_status,
            'operational_status' => $this->operational_status,
            'connection_status' => $connectionStatus,
            'last_heartbeat_at' => $this->last_heartbeat_at,
            'disconnected_at' => $this->disconnected_at,
            'declared_connector_count' => $this->declared_connector_count,
            'actual_connector_count' => $this->whenCounted('connectors'),
            'connector_count_matches' => $this->when(
                $this->connectors_count !== null,
                fn () => $this->declared_connector_count === $this->connectors_count
            ),
            'site' => $this->whenLoaded('site', fn () => [
                'id' => $this->site->id,
                'name' => $this->site->name,
                'address' => $this->site->address,
                'organization' => $this->site->relationLoaded('organization')
                    ? [
                        'id' => $this->site->organization->id,
                        'name' => $this->site->organization->name,
                    ]
                    : null,
            ]),
        ];
    }
}
