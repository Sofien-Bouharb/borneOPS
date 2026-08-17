<?php
namespace App\Services;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
class SupervisionDashboardService
{
    public function __construct(
        protected StationMonitoringService $monitoringService,
        protected OrganizationAccessService $organizationAccessService,
    ) {
    }
    /**
     * Build the full supervision dashboard payload: the station list
     * (eager-loaded, connector-counted, no N+1) plus honest KPI counts.
     *
     * Deliberately unpaginated per the roadmap's supervision-only exception —
     * the map and the list share one dataset.
     *
     * Every query in this method — the station list AND every KPI aggregate
     * in buildKpis() — is scoped BEFORE computing, never computed globally
     * then filtered, per Module 6 Decision N. A restricted (Client) user
     * with no organization memberships must see zero everywhere, not a
     * filtered station list next to real global KPI totals.
     *
     * @return array{stations: Collection<int, ChargingStation>, kpis: array}
     */
    public function build(User $user): array
    {
        $stationsQuery = $this->organizationAccessService->scopeStations(
            ChargingStation::query()->with(['site.organization'])->withCount('connectors'),
            $user,
        );

        $stations = $stationsQuery->get();

        return [
            'stations' => $stations,
            'kpis' => $this->buildKpis($stations, $user),
        ];
    }
    protected function buildKpis(Collection $stations, User $user): array
    {
        $organizationIds = $this->organizationAccessService->organizationIdsFor($user);
        $isRestricted = $this->organizationAccessService->isRestricted($user);

        $scopedStationQuery = function () use ($isRestricted, $organizationIds) {
            $query = ChargingStation::query();

            if ($isRestricted) {
                $query->whereHas('site', function ($siteQuery) use ($organizationIds) {
                    $siteQuery->whereIn('organization_id', $organizationIds);
                });
            }

            return $query;
        };

        $scopedConnectorQuery = function () use ($isRestricted, $organizationIds) {
            $query = Connector::query();

            if ($isRestricted) {
                $query->whereHas('chargingStation.site', function ($siteQuery) use ($organizationIds) {
                    $siteQuery->whereIn('organization_id', $organizationIds);
                });
            }

            return $query;
        };

        $stationsByAdministrativeStatus = $scopedStationQuery()
            ->select('administrative_status', DB::raw('count(*) as count'))
            ->groupBy('administrative_status')
            ->pluck('count', 'administrative_status');
        $stationsByOperationalStatus = $scopedStationQuery()
            ->select('operational_status', DB::raw('count(*) as count'))
            ->groupBy('operational_status')
            ->pluck('count', 'operational_status');
        $stationsByConnectionStatus = $stations
            ->groupBy(fn (ChargingStation $station) => $this->monitoringService->connectionStatus($station))
            ->map(fn (Collection $group) => $group->count());
        $stationsMissingCoordinates = $scopedStationQuery()
            ->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            })
            ->count();
        $connectorsByOperationalStatus = $scopedConnectorQuery()
            ->select('operational_status', DB::raw('count(*) as count'))
            ->groupBy('operational_status')
            ->pluck('count', 'operational_status');
        $connectorsByAdministrativeStatus = $scopedConnectorQuery()
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
            'connectors_total' => $scopedConnectorQuery()->count(),
            'connectors_by_operational_status' => $connectorsByOperationalStatus,
            'connectors_by_administrative_status' => $connectorsByAdministrativeStatus,
        ];
    }
}
