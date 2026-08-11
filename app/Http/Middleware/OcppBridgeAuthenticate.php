<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OcppBridgeAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.ocpp_bridge.token');
        $providedToken = $request->header('X-OCPP-Bridge-Token');

        if (empty($expectedToken) || empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'message' => 'Unauthorized. A valid OCPP bridge token is required.',
            ], 401);
        }

        return $next($request);
    }
}
