<?php

namespace App\Http\Controllers;

use App\Models\ChargingStation;
use App\Http\Resources\ChargingStationResource;
use App\Http\Requests\StoreChargingStationRequest;
use App\Http\Requests\UpdateChargingStationRequest;
use App\Services\ChargingStationService;
use App\Http\Resources\ChargingStationCollection;
use Illuminate\Http\Request;
use App\Http\Requests\DisableChargingStationRequest;
use App\Services\ChargingStationLifecycleService;
use App\Http\Requests\ReactivateChargingStationRequest;
use App\Http\Requests\DecommissionChargingStationRequest;
use App\Http\Requests\UpdateChargingStationStateRequest;
use App\Http\Requests\AssignChargingStationRequest;
use App\Http\Resources\ChargingStationHistoryResource;
use Illuminate\Support\Facades\DB;


class ChargingStationController extends Controller
{
public function __construct(
    protected ChargingStationService $chargingStationService,
    protected ChargingStationLifecycleService $lifecycleService,
) {}

public function index(Request $request)
{
    $query = ChargingStation::query()->with('site.organization');
    if ($request->filled('administrative_status')) {
        $query->where('administrative_status', $request->input('administrative_status'));
    }

    if ($request->filled('operational_status')) {
        $query->where('operational_status', $request->input('operational_status'));
    }

    if ($request->filled('manufacturer')) {
        $query->where('manufacturer', $request->input('manufacturer'));
    }

    if ($request->filled('site_id')) {
        $query->where('site_id', $request->input('site_id'));
    }

    if ($request->filled('organization_id')) {
        $query->whereHas('site', function ($siteQuery) use ($request) {
            $siteQuery->where('organization_id', $request->input('organization_id'));
        });
    }

    if ($request->filled('search')) {
        $search = $request->input('search');
        $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        $query->where(function ($q) use ($search, $likeOperator) {
            $q->where('name', $likeOperator, "%{$search}%")
                ->orWhere('reference',$likeOperator, "%{$search}%")
                ->orWhere('serial_number', $likeOperator, "%{$search}%");
        });
    }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $stations = $query->paginate($perPage);

    return new ChargingStationCollection($stations);
}

    public function store(StoreChargingStationRequest $request)
    {
        $station = $this->chargingStationService->create(
            $request->validated(),
            auth()->user(),
        );

        return (new ChargingStationResource($station))
            ->response()
            ->setStatusCode(201);
    }

  public function show(ChargingStation $station)
{
    $station->load('site.organization');
    return new ChargingStationResource($station);
}
    public function update(UpdateChargingStationRequest $request, ChargingStation $station)
    {
        $station = $this->chargingStationService->update(
            $station,
            $request->validated(),
            auth()->user(),
        );

        return new ChargingStationResource($station);
    }

    public function disable(DisableChargingStationRequest $request, ChargingStation $station)
{
    $data = $request->validated();

    $station = $this->lifecycleService->disable(
        $station,
        $data['reason'],
        $data['comment'] ?? null,
        auth()->user(),
    );

    return new ChargingStationResource($station);
}

public function reactivate(ReactivateChargingStationRequest $request, ChargingStation $station)
{
    $data = $request->validated();

    $station = $this->lifecycleService->reactivate(
        $station,
        $data['reason'] ?? null,
        $data['comment'] ?? null,
        auth()->user(),
    );

    return new ChargingStationResource($station);
}

public function decommission(DecommissionChargingStationRequest $request, ChargingStation $station)
{
    $data = $request->validated();

    $station = $this->lifecycleService->decommission(
        $station,
        $data['reason'],
        $data['comment'] ?? null,
        auth()->user(),
    );

    return new ChargingStationResource($station);
}

public function updateState(UpdateChargingStationStateRequest $request, ChargingStation $station)
{
    $data = $request->validated();

    $station = $this->chargingStationService->updateOperationalStatus(
        $station,
        $data['operational_status'],
        $data['reason'],
        $data['comment'] ?? null,
        auth()->user(),
    );

    return new ChargingStationResource($station);
}

public function assign(AssignChargingStationRequest $request, ChargingStation $station)
{
    $data = $request->validated();

    $station = $this->chargingStationService->assign(
        $station,
        $data['site_id'],
        $data['reason'] ?? null,
        $data['comment'] ?? null,
        auth()->user(),
    );

    return new ChargingStationResource($station);
}

public function history(ChargingStation $station)
{
    $histories = $station->histories()
        ->with('performedBy')
        ->latest()
        ->paginate(15);

    return ChargingStationHistoryResource::collection($histories);
}

    public function destroy(ChargingStation $station)
    {
        // No delete endpoint in Module 2 (see roadmap §9).
    }
}
