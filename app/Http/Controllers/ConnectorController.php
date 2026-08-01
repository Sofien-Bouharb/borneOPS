<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConnectorRequest;
use App\Http\Requests\UpdateConnectorAvailabilityRequest;
use App\Http\Requests\UpdateConnectorRequest;
use App\Http\Requests\UpdateConnectorStateRequest;
use App\Http\Resources\ConnectorResource;
use App\Models\ChargingStation;
use App\Models\Connector;
use App\Services\ConnectorService;

class ConnectorController extends Controller
{
    public function __construct(
        protected ConnectorService $connectorService
    ) {
    }

    /**
     * GET /charging-stations/{station}/connectors
     *
     * Lists every connector belonging to the given station. There is no
     * global connector-list endpoint — connectors are always accessed
     * through their parent station, per the roadmap.
     */
    public function index(ChargingStation $station)
    {
        $connectors = $station->connectors()->with('chargingStation')->get();

        return ConnectorResource::collection($connectors);
    }

    /**
     * POST /charging-stations/{station}/connectors
     *
     * Delegates entirely to ConnectorService::create(), which enforces the
     * commissioning-only lock and derives current_type server-side.
     */
    public function store(StoreConnectorRequest $request, ChargingStation $station)
    {
        $connector = $this->connectorService->create($station, $request->validated());

        return (new ConnectorResource($connector))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /charging-stations/{station}/connectors/{connector}
     */
    public function show(ChargingStation $station, Connector $connector)
    {
        $this->ensureConnectorBelongsToStation($station, $connector);

        $connector->load('chargingStation');

        return new ConnectorResource($connector);
    }

    /**
     * PATCH /charging-stations/{station}/connectors/{connector}
     *
     * Delegates to ConnectorService::update(). $request->validated() is used
     * deliberately, never $request->all() — current_type, operational_status,
     * and administrative_status are absent from the Form Request's rules, so
     * validated() strips them automatically even if a client sends them.
     */
    public function update(UpdateConnectorRequest $request, ChargingStation $station, Connector $connector)
    {
        $this->ensureConnectorBelongsToStation($station, $connector);

        $connector = $this->connectorService->update($connector, $request->validated());

        return new ConnectorResource($connector);
    }

    /**
     * DELETE /charging-stations/{station}/connectors/{connector}
     *
     * Soft-deletes via ConnectorService::delete(). Returns 204 No Content —
     * there is nothing meaningful left to hand back once a resource is gone.
     */
    public function destroy(ChargingStation $station, Connector $connector)
    {
        $this->ensureConnectorBelongsToStation($station, $connector);

        $this->connectorService->delete($connector);

        return response()->noContent();
    }

    /**
     * PATCH /charging-stations/{station}/connectors/{connector}/state
     *
     * Updates operational_status. Blocked only when the parent station is
     * decommissioned — active/disabled parents still permit this.
     */
    public function updateState(UpdateConnectorStateRequest $request, ChargingStation $station, Connector $connector)
    {
        $this->ensureConnectorBelongsToStation($station, $connector);

        $connector = $this->connectorService->updateOperationalStatus(
            $connector,
            $request->validated()['operational_status']
        );

        return new ConnectorResource($connector);
    }

    /**
     * PATCH /charging-stations/{station}/connectors/{connector}/availability
     *
     * Updates administrative_status (enabled/disabled). Same parent lock as
     * updateState(), plus a same-state 409 enforced inside the service.
     */
    public function updateAvailability(
        UpdateConnectorAvailabilityRequest $request,
        ChargingStation $station,
        Connector $connector
    ) {
        $this->ensureConnectorBelongsToStation($station, $connector);

        $connector = $this->connectorService->updateAvailability(
            $connector,
            $request->validated()['administrative_status']
        );

        return new ConnectorResource($connector);
    }

    /**
     * Route-model binding resolves {connector} by ID alone, regardless of
     * which station it actually belongs to. Every route that receives both
     * {station} and {connector} must confirm the connector really belongs
     * to that station, or a request could reach a connector through the
     * wrong station's URL entirely.
     */
    private function ensureConnectorBelongsToStation(ChargingStation $station, Connector $connector): void
    {
        if ($connector->charging_station_id !== $station->id) {
            abort(404, "Ce connecteur n'appartient pas à cette borne.");
        }
    }
}
