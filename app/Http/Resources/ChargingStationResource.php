<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChargingStationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'reference' => $this->reference,
            'serial_number' => $this->serial_number,
            'model' => $this->model,
            'manufacturer' => $this->manufacturer,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'firmware_version' => $this->firmware_version,
            'ocpp_version' => $this->ocpp_version,
            'ocpp_identifier' => $this->ocpp_identifier,
            'declared_connector_count' => $this->declared_connector_count,
            'power_kw' => $this->power_kw,
            'operational_status' => $this->operational_status,
            'administrative_status' => $this->administrative_status,
            'site_id' => $this->site_id,
            'site' => new SiteResource($this->whenLoaded('site')),
            'histories' => ChargingStationHistoryResource::collection($this->whenLoaded('histories')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
