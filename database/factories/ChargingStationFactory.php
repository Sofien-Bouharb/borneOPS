<?php

namespace Database\Factories;

use App\Models\Site;
use Database\Factories\Concerns\HasTunisianAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChargingStationFactory extends Factory
{
    use HasTunisianAddress;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => 'Station ' . fake()->bothify('??-####'),
            'reference' => fake()->unique()->bothify('REF-????-####'),
            'serial_number' => fake()->unique()->bothify('SN-????-####'),
            'model' => fake()->randomElement(['ModelX', 'ModelY', 'ModelZ']),
            'manufacturer' => fake()->randomElement(['AcmeCharge', 'VoltWorks', 'ChargeTech']),
            'latitude' => $this->tunisianLatitude(),
            'longitude' => $this->tunisianLongitude(),
            'firmware_version' => 'v' . fake()->numberBetween(1, 5) . '.' . fake()->numberBetween(0, 9),
            'ocpp_version' => fake()->randomElement(['1.6', '2.0.1']),
            'ocpp_identifier' => null,
            'declared_connector_count' => fake()->numberBetween(1, 4),
            'power_kw' => fake()->randomElement([7.4, 11, 22, 50, 150]),
        ];
    }
}
