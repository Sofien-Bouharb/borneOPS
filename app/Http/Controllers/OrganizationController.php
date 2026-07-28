<?php

namespace App\Http\Controllers;
use App\Models\Organization;
use App\Http\Resources\OrganizationResource;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;

class OrganizationController extends Controller
{
public function index()
{
    return OrganizationResource::collection(Organization::all());
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
