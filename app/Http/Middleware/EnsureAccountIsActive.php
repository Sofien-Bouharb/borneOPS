<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if ($user !== null && $user->account_status === 'disabled') {
            return response()->json([
                'message' => 'Ce compte a été désactivé.',
            ], 403);
        }

        return $next($request);
    }
}
