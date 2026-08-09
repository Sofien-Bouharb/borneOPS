<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelChargingSessionRequest;
use App\Http\Requests\EndChargingSessionRequest;
use App\Http\Requests\PauseChargingSessionRequest;
use App\Http\Requests\ResumeChargingSessionRequest;
use App\Http\Requests\StartChargingSessionRequest;
use App\Http\Requests\StoreChargingSessionRequest;
use App\Http\Resources\ChargingSessionResource;
use App\Models\ChargingSession;
use App\Services\ChargingSessionService;
use Illuminate\Http\Request;

class ChargingSessionController extends Controller
{
    public function __construct(
        protected ChargingSessionService $chargingSessionService
    ) {
    }

    /**
     * GET /charging-sessions
     *
     * Lists sessions with optional filters. Unlike connectors (always
     * scoped under a station URL), sessions have their own top-level list
     * endpoint per the Module 5 roadmap §17, since a single session most
     * often needs to be looked up or monitored independently of navigating
     * through its parent station first.
     */
    public function index(Request $request)
    {
        $query = ChargingSession::query()->with(['chargingStation', 'connector', 'customer']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('charging_station_id')) {
            $query->where('charging_station_id', $request->input('charging_station_id'));
        }

        if ($request->filled('connector_id')) {
            $query->where('connector_id', $request->input('connector_id'));
        }

        if ($request->filled('customer_user_id')) {
            $query->where('customer_user_id', $request->input('customer_user_id'));
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $sessions = $query->latest()->paginate($perPage);

        return ChargingSessionResource::collection($sessions);
    }

    /**
     * POST /charging-sessions
     *
     * Delegates entirely to ChargingSessionService::create(), which runs
     * the full §3.10 eligibility predicate before the session ever touches
     * the database.
     */
    public function store(StoreChargingSessionRequest $request)
    {
        $session = $this->chargingSessionService->create(
            $request->validated(),
            auth()->user(),
        );

        return (new ChargingSessionResource($session))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /charging-sessions/{session}
     */
    public function show(ChargingSession $session)
    {
        $session->load(['chargingStation', 'connector', 'customer']);

        return new ChargingSessionResource($session);
    }

    /**
     * POST /charging-sessions/{session}/start
     *
     * Re-runs the full eligibility predicate — station/connector state may
     * have drifted since the session was created.
     */
    public function start(StartChargingSessionRequest $request, ChargingSession $session)
    {
        $session = $this->chargingSessionService->start(
            $session,
            $request->validated()['meter_start_wh'],
            auth()->user(),
        );

        return new ChargingSessionResource($session);
    }

    /**
     * POST /charging-sessions/{session}/pause
     */
    public function pause(PauseChargingSessionRequest $request, ChargingSession $session)
    {
        $session = $this->chargingSessionService->pause($session, auth()->user());

        return new ChargingSessionResource($session);
    }

    /**
     * POST /charging-sessions/{session}/resume
     */
    public function resume(ResumeChargingSessionRequest $request, ChargingSession $session)
    {
        $session = $this->chargingSessionService->resume($session, auth()->user());

        return new ChargingSessionResource($session);
    }

    /**
     * POST /charging-sessions/{session}/end
     */
    public function end(EndChargingSessionRequest $request, ChargingSession $session)
    {
        $data = $request->validated();

        $session = $this->chargingSessionService->complete(
            $session,
            $data['meter_stop_wh'],
            $data['reason_code'],
            $data['reason_detail'] ?? null,
            auth()->user(),
        );

        return new ChargingSessionResource($session);
    }

    /**
     * POST /charging-sessions/{session}/cancel
     *
     * Only ever valid while the session is still 'pending' — see
     * ChargingSessionService::cancel().
     */
    public function cancel(CancelChargingSessionRequest $request, ChargingSession $session)
    {
        $data = $request->validated();

        $session = $this->chargingSessionService->cancel(
            $session,
            $data['reason_code'],
            $data['reason_detail'] ?? null,
            auth()->user(),
        );

        return new ChargingSessionResource($session);
    }
}
