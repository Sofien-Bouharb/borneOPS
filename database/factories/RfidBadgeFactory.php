<?php

namespace Database\Factories;

use App\Models\RfidBadge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RfidBadgeFactory extends Factory
{
    protected $model = RfidBadge::class;

    public function definition(): array
    {
        $raw = strtoupper(Str::random(12));

        return [
            'user_id' => User::factory(),
            'identifier_hash' => RfidBadge::hashIdentifier($raw),
            'identifier_hint' => RfidBadge::hintFor($raw),
            'label' => null,
            'administrative_status' => 'pending',
            'expires_at' => null,
            'activated_at' => null,
            'blocked_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'administrative_status' => 'active',
            'activated_at' => now(),
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => [
            'administrative_status' => 'blocked',
            'activated_at' => now()->subDay(),
            'blocked_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'administrative_status' => 'active',
            'activated_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
        ]);
    }
}
