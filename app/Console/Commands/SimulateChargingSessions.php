<?php

namespace App\Console\Commands;

use App\Models\ChargingSession;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Services\ChargingSessionService;
use App\Services\StationMonitoringService;
use Illuminate\Console\Command;

class SimulateChargingSessions extends Command
{
    protected $signature = 'sessions:simulate-charging
        {--continuous : Keep running and emitting events until stopped (Ctrl+C)}
        {--interval=5 : Seconds to wait between passes in continuous mode}
        {--count=5 : Number of session actions attempted per pass}';

    protected $description = 'Dev-only simulator that drives charging sessions through their full lifecycle via ChargingSessionService, without a live OCPP server.';

    /**
     * Kept in sync with ChargingSessionService::COMPLETION_REASON_CODES.
     * Duplicated here rather than exposed as public constants on the
     * service, since the service's reason-code lists are an internal
     * implementation detail, not part of its public contract.
     */
    protected array $completionReasonCodes = [
        'user_requested',
        'operator_requested',
        'remote_stop',
        'vehicle_disconnected',
        'equipment_fault',
        'power_loss',
        'communication_loss',
        'other',
    ];

    /**
     * Kept in sync with ChargingSessionService::CANCELLATION_REASON_CODES.
     */
    protected array $cancellationReasonCodes = [
        'user_requested',
        'operator_requested',
        'equipment_unavailable',
        'other',
    ];

    protected array $lifecycleActions = [
        'create',
        'start',
        'meter',
        'pause',
        'resume',
        'complete',
        'cancel',
    ];

    public function handle(ChargingSessionService $sessionService, StationMonitoringService $monitoringService): int
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->error('sessions:simulate-charging can only run in the local or testing environment.');

            return self::FAILURE;
        }

        $count = (int) $this->option('count');
        $interval = (int) $this->option('interval');
        $continuous = (bool) $this->option('continuous');

        if ($continuous) {
            $this->info("Starting continuous session simulation — {$count} action(s) per pass, every {$interval}s. Press Ctrl+C to stop.");

            while (true) {
                $this->runPass($sessionService, $monitoringService, $count);
                sleep($interval);
            }
        }

        $this->info("Running a single session simulation pass — {$count} action(s).");
        $this->runPass($sessionService, $monitoringService, $count);

        return self::SUCCESS;
    }

    protected function runPass(ChargingSessionService $sessionService, StationMonitoringService $monitoringService, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $action = $this->lifecycleActions[array_rand($this->lifecycleActions)];

            match ($action) {
                'create' => $this->simulateCreate($sessionService, $monitoringService),
                'start' => $this->simulateStart($sessionService, $monitoringService),
                'meter' => $this->simulateMeter($sessionService),
                'pause' => $this->simulatePause($sessionService),
                'resume' => $this->simulateResume($sessionService),
                'complete' => $this->simulateComplete($sessionService),
                'cancel' => $this->simulateCancel($sessionService),
            };
        }
    }

    /**
     * Find one connector that satisfies the exact same §3.10 eligibility
     * predicate ChargingSessionService::assertEligible() enforces, so the
     * simulator only ever attempts a create() that is actually expected to
     * succeed. Uses the real StationMonitoringService::connectionStatus()
     * for the connection check rather than re-deriving it independently.
     */
    protected function findEligibleConnector(StationMonitoringService $monitoringService): ?Connector
    {
        $candidates = Connector::query()
            ->whereNull('deleted_at')
            ->where('administrative_status', 'enabled')
            ->where('operational_status', 'available')
            ->whereNotIn('id', function ($query) {
                $query->select('connector_id')
                    ->from('charging_sessions')
                    ->whereIn('status', ['pending', 'active', 'paused']);
            })
            ->inRandomOrder()
            ->limit(20)
            ->get();

        foreach ($candidates as $connector) {
            $station = ChargingStation::find($connector->charging_station_id);

            if ($station === null || $station->trashed()) {
                continue;
            }

            if ($station->administrative_status !== 'active') {
                continue;
            }

            if (!in_array($station->operational_status, ['available', 'occupied'], true)) {
                continue;
            }

            if ($monitoringService->connectionStatus($station) !== 'connected') {
                continue;
            }

            return $connector;
        }

        return null;
    }

    protected function simulateCreate(ChargingSessionService $sessionService, StationMonitoringService $monitoringService): void
    {
        $connector = $this->findEligibleConnector($monitoringService);

        if ($connector === null) {
            $this->line('  [create] no eligible connector found — skipped');

            return;
        }

        $session = $sessionService->create([
            'charging_station_id' => $connector->charging_station_id,
            'connector_id' => $connector->id,
        ], null, 'system');

        $this->line("  [create] session #{$session->id} — connector #{$connector->id} — pending");
    }

    protected function simulateStart(ChargingSessionService $sessionService, StationMonitoringService $monitoringService): void
    {
        $session = ChargingSession::where('status', 'pending')->inRandomOrder()->first();

        if ($session === null) {
            $this->line('  [start] no pending session found — skipped');

            return;
        }

        try {
            $meterStartWh = random_int(0, 5000);
            $session = $sessionService->start($session, $meterStartWh, null, 'system');
            $this->line("  [start] session #{$session->id} — meter_start_wh={$meterStartWh}");
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            $this->line("  [start] session #{$session->id} — skipped: {$e->getMessage()}");
        }
    }

    protected function simulateMeter(ChargingSessionService $sessionService): void
    {
        $session = ChargingSession::whereIn('status', ['active', 'paused'])->inRandomOrder()->first();

        if ($session === null) {
            $this->line('  [meter] no active or paused session found — skipped');

            return;
        }

        $currentReading = $session->latest_meter_wh ?? $session->meter_start_wh ?? 0;
        $newReading = $currentReading + random_int(50, 500);

        $session = $sessionService->recordMeterValue($session, $newReading);
        $this->line("  [meter] session #{$session->id} — latest_meter_wh={$newReading}");
    }

    protected function simulatePause(ChargingSessionService $sessionService): void
    {
        $session = ChargingSession::where('status', 'active')->inRandomOrder()->first();

        if ($session === null) {
            $this->line('  [pause] no active session found — skipped');

            return;
        }

        $session = $sessionService->pause($session, null, 'system');
        $this->line("  [pause] session #{$session->id}");
    }

    protected function simulateResume(ChargingSessionService $sessionService): void
    {
        $session = ChargingSession::where('status', 'paused')->inRandomOrder()->first();

        if ($session === null) {
            $this->line('  [resume] no paused session found — skipped');

            return;
        }

        $session = $sessionService->resume($session, null, 'system');
        $this->line("  [resume] session #{$session->id}");
    }

    protected function simulateComplete(ChargingSessionService $sessionService): void
    {
        $session = ChargingSession::whereIn('status', ['active', 'paused'])->inRandomOrder()->first();

        if ($session === null) {
            $this->line('  [complete] no active or paused session found — skipped');

            return;
        }

        $currentReading = $session->latest_meter_wh ?? $session->meter_start_wh ?? 0;
        $meterStopWh = $currentReading + random_int(50, 500);
        $reasonCode = $this->completionReasonCodes[array_rand($this->completionReasonCodes)];

        $session = $sessionService->complete($session, $meterStopWh, $reasonCode, null, null, 'system');
        $this->line("  [complete] session #{$session->id} — meter_stop_wh={$meterStopWh} — reason={$reasonCode}");
    }

    protected function simulateCancel(ChargingSessionService $sessionService): void
    {
        $session = ChargingSession::where('status', 'pending')->inRandomOrder()->first();

        if ($session === null) {
            $this->line('  [cancel] no pending session found — skipped');

            return;
        }

        $reasonCode = $this->cancellationReasonCodes[array_rand($this->cancellationReasonCodes)];

        $session = $sessionService->cancel($session, $reasonCode, null, null, 'system');
        $this->line("  [cancel] session #{$session->id} — reason={$reasonCode}");
    }
}
