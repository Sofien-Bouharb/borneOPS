<?php
namespace App\Http\Controllers;
use App\Models\Organization;
use App\Http\Resources\OrganizationResource;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Services\OrganizationAccessService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrganizationController extends Controller
{
    public function __construct(
        protected OrganizationAccessService $organizationAccessService,
    ) {
    }

    public function index()
    {
        $query = $this->organizationAccessService->scopeOrganizations(
            Organization::query(),
            auth()->user(),
        );

        return OrganizationResource::collection($query->get());
    }

    public function store(StoreOrganizationRequest $request)
    {
        $organization = Organization::create($request->validated());
        return (new OrganizationResource($organization))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Organization $organization)
    {
        if (!$this->organizationAccessService->canAccessOrganization(auth()->user(), $organization)) {
            throw new NotFoundHttpException();
        }

        return new OrganizationResource($organization);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        $organization->update($request->validated());
        return new OrganizationResource($organization);
    }

    public function destroy(Organization $organization)
    {
        // No delete endpoint in Module 2 (see roadmap §9).
    }
}
