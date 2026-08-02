<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConnectorResource extends JsonResource
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
            'charging_station_id' => $this->charging_station_id,
            'connector_number' => $this->connector_number,
            'standard' => $this->standard,
            'current_type' => $this->current_type,
            'max_power_kw' => $this->max_power_kw,
            'operational_status' => $this->operational_status,
            'administrative_status' => $this->administrative_status,
            'charging_station' => $this->when(
                $this->relationLoaded('chargingStation'),
                fn () => [
                    'id' => $this->chargingStation->id,
                    'name' => $this->chargingStation->name,
                    'reference' => $this->chargingStation->reference,
                ]
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
