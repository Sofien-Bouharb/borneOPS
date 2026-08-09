<?php

namespace Database\Factories;

use App\Models\ChargingStation;
use App\Models\Connector;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChargingSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'charging_station_id' => ChargingStation::factory(),
            'connector_id' => Connector::factory(),
            'status' => 'pending',
        ];
    }
}
