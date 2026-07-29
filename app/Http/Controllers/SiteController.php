<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Http\Resources\SiteResource;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;

class SiteController extends Controller
{
    public function index()
    {
        return SiteResource::collection(Site::all());
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
