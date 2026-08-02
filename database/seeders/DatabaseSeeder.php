<?php

namespace Database\Seeders;

use App\Models\ChargingStation;
use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use App\Services\ConnectorService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
            ]
        );

        $this->call(RolesAndPermissionsSeeder::class);

        Organization::factory()
            ->count(3)
            ->has(Site::factory()->count(2)
                ->has(ChargingStation::factory()->count(3)))
            ->create();

        $this->seedConnectors();
    }

    /**
     * Seed connectors for every charging station created above.
     *
     * Routed through ConnectorService::create() rather than a raw factory
     * call, so seeded data goes through the same business rules (the
     * commissioning-only lock, server-side current_type derivation) as real
     * API requests. Every freshly-factory-created station starts in
     * 'commissioning' by default, so the lock always passes here.
     *
     * connector_number is assigned explicitly per connector (1, 2, 3...)
     * to satisfy the composite unique constraint — ConnectorFactory's own
     * default of a flat 1 would collide if used directly for more than one
     * connector per station.
     *
     * The actual connector count is usually equal to the station's
     * declared_connector_count, but sometimes off by one in either
     * direction, so ChargingStationResource's connector_count_matches
     * field has real mismatches to display in local development, not just
     * trivial matches.
     */
    private function seedConnectors(): void
    {
        $connectorService = new ConnectorService();
        $standards = ['ccs', 'type2', 'chademo'];
        $powerOptions = [7.4, 11, 22, 50, 150];

        foreach (ChargingStation::all() as $station) {
            $target = max(1, (int) $station->declared_connector_count);
            $variance = fake()->randomElement([-1, 0, 0, 0, 1]);
            $actualCount = max(1, $target + $variance);

            $validPowers = collect($powerOptions)
                ->filter(fn ($power) => $power <= $station->power_kw)
                ->values();

            for ($connectorNumber = 1; $connectorNumber <= $actualCount; $connectorNumber++) {
                $connectorService->create($station, [
                    'connector_number' => $connectorNumber,
                    'standard' => fake()->randomElement($standards),
                    'max_power_kw' => $validPowers->random(),
                ]);
            }
        }
    }
}
