<?php

namespace Database\Factories;

use App\Models\ChargingStation;
use App\Models\Connector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connector>
 */
class ConnectorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $standard = $this->faker->randomElement(['ccs', 'type2', 'chademo']);

        return [
            'charging_station_id' => ChargingStation::factory(),
            'connector_number' => 1,
            'standard' => $standard,
            'current_type' => Connector::CURRENT_TYPE_BY_STANDARD[$standard],
            'max_power_kw' => $this->faker->randomElement([7.4, 11, 22, 50, 150]),
            'operational_status' => 'disconnected',
            'administrative_status' => 'enabled',
        ];
    }
}
