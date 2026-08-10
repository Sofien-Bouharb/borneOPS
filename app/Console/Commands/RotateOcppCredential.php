<?php

namespace App\Console\Commands;

use App\Models\ChargingStation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RotateOcppCredential extends Command
{
    protected $signature = 'stations:rotate-ocpp-credential {station : The station ID or reference}';

    protected $description = 'Generate and store a new OCPP authentication credential for a charging station.';

    public function handle(): int
    {
        $identifier = $this->argument('station');

        $station = is_numeric($identifier)
            ? ChargingStation::find($identifier)
            : ChargingStation::where('reference', $identifier)->first();

        if ($station === null) {
            $this->error("No charging station found matching '{$identifier}'.");

            return self::FAILURE;
        }

        if ($station->ocpp_auth_password_hash !== null) {
            $confirmed = $this->confirm(
                "Station '{$station->reference}' already has an OCPP credential set (last rotated: {$station->ocpp_auth_updated_at}). Overwrite it?"
            );

            if (!$confirmed) {
                $this->info('Aborted. No changes made.');

                return self::SUCCESS;
            }
        }

        $plaintextCredential = Str::random(32);

        $station->ocpp_auth_password_hash = Hash::make($plaintextCredential);
        $station->ocpp_auth_updated_at = now();
        $station->save();

        $this->info("New OCPP credential generated for station '{$station->reference}' (ID: {$station->id}).");
        $this->newLine();
        $this->line('Plaintext credential (shown once — configure this on the physical charger now):');
        $this->line($plaintextCredential);
        $this->newLine();
        $this->warn('This credential will not be shown again. Only its hash is stored.');

        return self::SUCCESS;
    }
}
