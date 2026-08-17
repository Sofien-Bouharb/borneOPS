<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRoleUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserHistoryResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService,
    ) {
    }

    public function index(Request $request)
    {
        $query = User::query()->with('organizations');

        if ($request->filled('account_status')) {
            $query->where('account_status', $request->input('account_status'));
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', function ($roleQuery) use ($request) {
                $roleQuery->where('name', $request->input('role'));
            });
        }

        if ($request->filled('organization_id')) {
            $query->whereHas('organizations', function ($orgQuery) use ($request) {
                $orgQuery->where('organizations.id', $request->input('organization_id'));
            });
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('name', $likeOperator, "%{$search}%")
                    ->orWhere('email', $likeOperator, "%{$search}%");
            });
        }

        $perPage = max(1, min((int) $request->input('per_page', 15), 100));
        $users = $query->paginate($perPage);

        return new UserCollection($users);
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->create(
            $request->validated(),
            auth()->user(),
        );

        return (new UserResource($user->load('organizations')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user)
    {
        $user->load('organizations');

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->userService->update(
            $user,
            $request->validated(),
            auth()->user(),
        );

        return new UserResource($user->load('organizations'));
    }

    public function updateAccountStatus(Request $request, User $user)
    {
        $data = $request->validate([
            'account_status' => ['required', 'string', 'in:active,disabled'],
        ]);

        $user = $this->userService->updateAccountStatus(
            $user,
            $data['account_status'],
            auth()->user(),
        );

        return new UserResource($user->load('organizations'));
    }

    public function assignRole(AssignRoleUserRequest $request, User $user)
    {
        $data = $request->validated();

        $user = $this->userService->assignRole(
            $user,
            $data['role'],
            auth()->user(),
        );

        return new UserResource($user->load('organizations'));
    }

    public function syncOrganizations(Request $request, User $user)
    {
        $data = $request->validate([
            'organization_ids' => ['present', 'array'],
            'organization_ids.*' => ['integer', 'exists:organizations,id'],
        ]);

        $user = $this->userService->syncOrganizations(
            $user,
            $data['organization_ids'],
            auth()->user(),
        );

        return new UserResource($user->load('organizations'));
    }

    public function resendPasswordSetupLink(User $user)
    {
        $this->userService->resendPasswordSetupLink($user, auth()->user());

        return response()->json([
            'message' => 'Le lien de configuration du mot de passe a été renvoyé.',
        ]);
    }

    public function history(User $user)
    {
        $histories = $user->histories()
            ->with('performedBy')
            ->latest()
            ->paginate(15);

        return UserHistoryResource::collection($histories);
    }
}
