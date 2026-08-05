<?php

namespace Database\Factories;

use App\Models\Organization;
use Database\Factories\Concerns\HasTunisianAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    use HasTunisianAddress;

    public function definition(): array
    {
        $city = $this->tunisianCity();

        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement([
                "Station {$city['name']} Centre",
                "Site {$city['name']} Nord",
                "Parking {$city['name']}",
                "Zone Industrielle {$city['name']}",
                "Centre Commercial {$city['name']}",
            ]),
            'address' => $this->tunisianAddress($city),
            'latitude' => $this->tunisianLatitude(),
            'longitude' => $this->tunisianLongitude(),
        ];
    }
}
