<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // This is a fully decoupled JSON API with no server-rendered login
        // page. Never redirect an unauthenticated request anywhere — always
        // let it fall through to a clean 401 JSON response instead, which
        // shouldRenderJsonWhen() below then renders correctly.
        $middleware->redirectGuestsTo(fn () => null);

        // Register Spatie's permission/role middleware under short aliases so
        // routes can use `permission:organizations.view` etc. Module 1 seeded
        // the permissions but attached none to routes — Module 2 is the first
        // to actually enforce them.
        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\App\Exceptions\InvalidStateTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        });
    })->create();
