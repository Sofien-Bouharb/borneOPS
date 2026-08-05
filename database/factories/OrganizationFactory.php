<?php

namespace Database\Factories;

use App\Support\TunisianData;
use Database\Factories\Concerns\HasTunisianAddress;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    use HasTunisianAddress;

    public function definition(): array
    {
        $baseName = fake()->randomElement(TunisianData::ORGANIZATION_NAMES);
        $suffix = fake()->randomElement(TunisianData::ORGANIZATION_SUFFIXES);
        $slug = Str::slug($baseName);

        return [
            'name' => "{$baseName} {$suffix}",
            'type' => fake()->randomElement(['operator', 'client']),
            'contact_email' => "contact@{$slug}.tn",
            'contact_phone' => '+216 ' . fake()->numberBetween(20, 99) . ' ' . fake()->numberBetween(100, 999) . ' ' . fake()->numberBetween(100, 999),
            'address' => $this->tunisianAddress(),
        ];
    }
}
