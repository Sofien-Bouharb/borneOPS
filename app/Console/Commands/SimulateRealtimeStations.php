<?php

namespace App\Console\Commands;

use App\Models\ChargingStation;
use App\Services\StationMonitoringService;
use Illuminate\Console\Command;

class SimulateRealtimeStations extends Command
{
    protected $signature = 'stations:simulate-realtime
        {--continuous : Keep running and emitting events until stopped (Ctrl+C)}
        {--interval=5 : Seconds to wait between passes in continuous mode}
        {--count=5 : Number of stations touched per pass}';

    protected $description = 'Dev-only simulator that emits fake heartbeats and status changes through the real monitoring service, without a live OCPP server.';

    protected array $operationalStatuses = [
        'available',
        'occupied',
        'out_of_service',
        'maintenance',
        'disconnected',
        'fault',
    ];

    public function handle(StationMonitoringService $monitoringService): int
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->error('stations:simulate-realtime can only run in the local or testing environment.');

            return self::FAILURE;
        }

        $count = (int) $this->option('count');
        $interval = (int) $this->option('interval');
        $continuous = (bool) $this->option('continuous');

        if ($continuous) {
            $this->info("Starting continuous simulation — {$count} station(s) per pass, every {$interval}s. Press Ctrl+C to stop.");

            while (true) {
                $this->runPass($monitoringService, $count);
                sleep($interval);
            }
        }

        $this->info("Running a single simulation pass — {$count} station(s).");
        $this->runPass($monitoringService, $count);

        return self::SUCCESS;
    }

    protected function runPass(StationMonitoringService $monitoringService, int $count): void
    {
        $stations = ChargingStation::query()
            ->whereNull('deleted_at')
            ->where('administrative_status', '!=', 'decommissioned')
            ->inRandomOrder()
            ->limit($count)
            ->get();

        if ($stations->isEmpty()) {
            $this->warn('No eligible stations found to simulate.');

            return;
        }

        foreach ($stations as $station) {
            $this->simulateEventFor($monitoringService, $station);
        }
    }

    protected function simulateEventFor(StationMonitoringService $monitoringService, ChargingStation $station): void
    {
        $connector = $station->connectors()->inRandomOrder()->first();

        $possibleActions = ['heartbeat', 'station_status'];
        if ($connector !== null) {
            $possibleActions[] = 'connector_status';
        }

        $action = $possibleActions[array_rand($possibleActions)];

        match ($action) {
            'heartbeat' => $this->simulateHeartbeat($monitoringService, $station),
            'station_status' => $this->simulateStationStatus($monitoringService, $station),
            'connector_status' => $this->simulateConnectorStatus($monitoringService, $connector),
        };
    }

    protected function simulateHeartbeat(StationMonitoringService $monitoringService, ChargingStation $station): void
    {
        $monitoringService->recordHeartbeat($station);
        $this->line("  [{$station->id}] {$station->name} — heartbeat");
    }

    protected function simulateStationStatus(StationMonitoringService $monitoringService, ChargingStation $station): void
    {
        $status = $this->operationalStatuses[array_rand($this->operationalStatuses)];
        $monitoringService->recordOperationalStatus($station, $status, null, 'ocpp');
        $this->line("  [{$station->id}] {$station->name} — operational status → {$status}");
    }

    protected function simulateConnectorStatus(StationMonitoringService $monitoringService, $connector): void
    {
        $status = $this->operationalStatuses[array_rand($this->operationalStatuses)];
        $monitoringService->recordConnectorOperationalStatus($connector, $status);
        $this->line("  [connector {$connector->id}] station {$connector->charging_station_id} — connector status → {$status}");
    }
}
