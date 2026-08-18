<?php
namespace App\Http\Controllers;
use App\Http\Requests\RfidBadgeExpirationRequest;
use App\Http\Requests\RfidBadgeReassignRequest;
use App\Http\Requests\RfidBadgeStoreRequest;
use App\Http\Requests\RfidBadgeUpdateRequest;
use App\Http\Resources\RfidBadgeCollection;
use App\Http\Resources\RfidBadgeHistoryResource;
use App\Http\Resources\RfidBadgeResource;
use App\Models\RfidBadge;
use App\Services\RfidBadgeService;
use Illuminate\Http\Request;

class RfidBadgeController extends Controller
{
    public function __construct(
        protected RfidBadgeService $rfidBadgeService,
    ) {
    }

    public function index(Request $request)
    {
        $query = RfidBadge::query()->with('user');

        if (auth()->user()->hasRole('Client')) {
            $query->where('user_id', auth()->id());
        }

        if ($request->filled('administrative_status')) {
            $query->where('administrative_status', $request->input('administrative_status'));
        }

        if ($request->filled('user_id') && !auth()->user()->hasRole('Client')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $badges = $query->paginate($perPage);

        return new RfidBadgeCollection($badges);
    }

    public function show(RfidBadge $badge)
    {
        if (auth()->user()->hasRole('Client') && $badge->user_id !== auth()->id()) {
            abort(404);
        }

        return new RfidBadgeResource($badge->load('user'));
    }

    public function store(RfidBadgeStoreRequest $request)
    {
        $validated = $request->validated();

        $badge = $this->rfidBadgeService->create(
            ['user_id' => $validated['user_id'], 'label' => $validated['label'] ?? null, 'expires_at' => $validated['expires_at'] ?? null],
            $validated['identifier'],
            auth()->user(),
        );

        return (new RfidBadgeResource($badge->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(RfidBadgeUpdateRequest $request, RfidBadge $badge)
    {
        $badge = $this->rfidBadgeService->update($badge, $request->validated(), auth()->user());

        return new RfidBadgeResource($badge->load('user'));
    }

    public function reassign(RfidBadgeReassignRequest $request, RfidBadge $badge)
    {
        $badge = $this->rfidBadgeService->reassign($badge, $request->validated()['user_id'], auth()->user());

        return new RfidBadgeResource($badge->load('user'));
    }

    public function activate(RfidBadge $badge)
    {
        $badge = $this->rfidBadgeService->activate($badge, auth()->user());

        return new RfidBadgeResource($badge->load('user'));
    }

    public function block(RfidBadge $badge)
    {
        $badge = $this->rfidBadgeService->block($badge, auth()->user());

        return new RfidBadgeResource($badge->load('user'));
    }

    public function updateExpiration(RfidBadgeExpirationRequest $request, RfidBadge $badge)
    {
        $validated = $request->validated();
        $expiresAt = isset($validated['expires_at']) ? \Carbon\Carbon::parse($validated['expires_at']) : null;

        $badge = $this->rfidBadgeService->updateExpiration($badge, $expiresAt, auth()->user());

        return new RfidBadgeResource($badge->load('user'));
    }

    public function history(Request $request, RfidBadge $badge)
    {
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $histories = $badge->histories()->with('performedBy')->latest('id')->paginate($perPage);

        return RfidBadgeHistoryResource::collection($histories);
    }
}
