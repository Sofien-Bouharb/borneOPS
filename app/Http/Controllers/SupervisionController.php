<?php

namespace App\Http\Controllers;

use App\Http\Resources\SupervisionDashboardResource;
use App\Services\SupervisionDashboardService;

class SupervisionController extends Controller
{
    public function dashboard(SupervisionDashboardService $dashboardService)
    {
        return new SupervisionDashboardResource($dashboardService->build());
    }
}
