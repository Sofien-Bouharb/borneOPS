<?php
namespace App\Http\Controllers;
use App\Models\Site;
use App\Http\Resources\SiteResource;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Services\OrganizationAccessService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SiteController extends Controller
{
    public function __construct(
        protected OrganizationAccessService $organizationAccessService,
    ) {
    }

    public function index()
    {
        $query = $this->organizationAccessService->scopeSites(
            Site::query(),
            auth()->user(),
        );

        return SiteResource::collection($query->get());
    }

    public function store(StoreSiteRequest $request)
    {
        $site = Site::create($request->validated());
        return (new SiteResource($site))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Site $site)
    {
        if (!$this->organizationAccessService->canAccessSite(auth()->user(), $site)) {
            throw new NotFoundHttpException();
        }

        return new SiteResource($site);
    }

    public function update(UpdateSiteRequest $request, Site $site)
    {
        $site->update($request->validated());
        return new SiteResource($site);
    }

    public function destroy(Site $site)
    {
        // No delete endpoint in Module 2 (see roadmap §9).
    }
}
