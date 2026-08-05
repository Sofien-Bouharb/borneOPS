<?php

namespace App\Services;

use App\Models\ChargingStation;
use App\Models\Connector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class SupervisionDashboardService
{
    public function __construct(
        protected StationMonitoringService $monitoringService,
    ) {
    }

    /**
     * Build the full supervision dashboard payload: the station list
     * (eager-loaded, connector-counted, no N+1) plus honest KPI counts.
     *
     * Deliberately unpaginated per the roadmap's supervision-only exception —
     * the map and the list share one dataset.
     *
     * @return array{stations: Collection<int, ChargingStation>, kpis: array}
     */
    public function build(): array
    {
        $stations = ChargingStation::query()
            ->with(['site.organization'])
            ->withCount('connectors')
            ->get();

        return [
            'stations' => $stations,
            'kpis' => $this->buildKpis($stations),
        ];
    }

    protected function buildKpis(Collection $stations): array
    {
        $stationsByAdministrativeStatus = ChargingStation::query()
            ->select('administrative_status', DB::raw('count(*) as count'))
            ->groupBy('administrative_status')
            ->pluck('count', 'administrative_status');

        $stationsByOperationalStatus = ChargingStation::query()
            ->select('operational_status', DB::raw('count(*) as count'))
            ->groupBy('operational_status')
            ->pluck('count', 'operational_status');

        $stationsByConnectionStatus = $stations
            ->groupBy(fn (ChargingStation $station) => $this->monitoringService->connectionStatus($station))
            ->map(fn (Collection $group) => $group->count());

        $stationsMissingCoordinates = ChargingStation::query()
            ->whereNull('latitude')
            ->orWhereNull('longitude')
            ->count();

        $connectorsByOperationalStatus = Connector::query()
            ->select('operational_status', DB::raw('count(*) as count'))
            ->groupBy('operational_status')
            ->pluck('count', 'operational_status');

        $connectorsByAdministrativeStatus = Connector::query()
            ->select('administrative_status', DB::raw('count(*) as count'))
            ->groupBy('administrative_status')
            ->pluck('count', 'administrative_status');

        $stationsWithConnectorMismatch = $stations
            ->filter(fn (ChargingStation $station) => $station->declared_connector_count !== $station->connectors_count)
            ->count();

        return [
            'stations_total' => $stations->count(),
            'stations_by_administrative_status' => $stationsByAdministrativeStatus,
            'stations_by_operational_status' => $stationsByOperationalStatus,
            'stations_by_connection_status' => $stationsByConnectionStatus,
            'stations_missing_coordinates' => $stationsMissingCoordinates,
            'stations_with_connector_mismatch' => $stationsWithConnectorMismatch,
            'connectors_total' => Connector::count(),
            'connectors_by_operational_status' => $connectorsByOperationalStatus,
            'connectors_by_administrative_status' => $connectorsByAdministrativeStatus,
        ];
    }
}
