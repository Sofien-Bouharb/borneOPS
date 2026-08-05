<?php

namespace Database\Factories\Concerns;

use App\Support\TunisianData;

trait HasTunisianAddress
{
    protected function tunisianCity(): array
    {
        return fake()->randomElement(TunisianData::CITIES);
    }

    protected function tunisianAddress(?array $city = null): string
    {
        $city = $city ?? $this->tunisianCity();
        $street = fake()->randomElement(TunisianData::STREET_NAMES);
        $number = fake()->numberBetween(1, 200);

        return "{$number} {$street}, {$city['postal_code']} {$city['name']}";
    }

    protected function tunisianLatitude(): float
    {
        return (float) fake()->latitude(TunisianData::LAT_MIN, TunisianData::LAT_MAX);
    }

    protected function tunisianLongitude(): float
    {
        return (float) fake()->longitude(TunisianData::LON_MIN, TunisianData::LON_MAX);
    }
}
