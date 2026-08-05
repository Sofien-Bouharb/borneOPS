<?php

namespace App\Console\Commands;

use App\Services\StationMonitoringService;
use Illuminate\Console\Command;

class DetectOfflineStations extends Command
{
    protected $signature = 'stations:detect-offline';

    protected $description = 'Mark charging stations as disconnected once their last heartbeat has gone stale.';

    public function handle(StationMonitoringService $monitoringService): int
    {
        $count = $monitoringService->refreshStaleStations();

        if ($count === 0) {
            $this->info('No stations found stale. Nothing to update.');
        } else {
            $this->info("Marked {$count} station(s) as disconnected due to a stale heartbeat.");
        }

        return self::SUCCESS;
    }
}
