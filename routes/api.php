<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\ChargingStationController;
use App\Http\Controllers\ConnectorController;
use App\Http\Controllers\SupervisionController;
use App\Http\Controllers\ChargingSessionController;
use App\Http\Controllers\Ocpp\OcppBridgeController;
use App\Http\Controllers\ChargingStationRemoteControlController;
use App\Http\Controllers\UserController;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/login/verify-2fa', [AuthController::class, 'verifyTwoFactor'])
    ->middleware('throttle:2fa-verify');

Route::post('/login/setup-2fa', [AuthController::class, 'completeTwoFactorEnrollment'])
    ->middleware('throttle:2fa-verify');

Route::post('/refresh', [AuthController::class, 'refresh'])
    ->middleware('throttle:refresh');

Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
    ->middleware('throttle:forgot-password');

Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:forgot-password');

Route::middleware(['auth:api', 'account.active'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::delete('/sessions/{sessionId}', [SessionController::class, 'destroy']);

        // --- Module 6: User Management ---
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:users.view');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:users.create');
    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.view');
    Route::patch('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.update');
    Route::patch('/users/{user}/account-status', [UserController::class, 'updateAccountStatus'])
        ->middleware('permission:users.disable');
    Route::patch('/users/{user}/role', [UserController::class, 'assignRole'])
        ->middleware('permission:users.update');
    Route::patch('/users/{user}/organizations', [UserController::class, 'syncOrganizations'])
        ->middleware('permission:users.update');
    Route::post('/users/{user}/password-setup-link', [UserController::class, 'resendPasswordSetupLink'])
        ->middleware(['permission:users.update', 'throttle:password-setup-resend']);
    Route::get('/users/{user}/history', [UserController::class, 'history'])
        ->middleware('permission:users.view');



    // --- Broadcasting authorization (private/presence channels) ---
    Broadcast::routes(['middleware' => ['auth:api']]);

    // --- Module 2: Organizations ---
    Route::get('/organizations', [OrganizationController::class, 'index'])
        ->middleware('permission:organizations.view');
    Route::post('/organizations', [OrganizationController::class, 'store'])
        ->middleware('permission:organizations.create');
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])
        ->middleware('permission:organizations.view');
    Route::patch('/organizations/{organization}', [OrganizationController::class, 'update'])
        ->middleware('permission:organizations.update');

        // --- Module 2: Sites ---
    Route::get('/sites', [SiteController::class, 'index'])
        ->middleware('permission:sites.view');
    Route::post('/sites', [SiteController::class, 'store'])
        ->middleware('permission:sites.create');
    Route::get('/sites/{site}', [SiteController::class, 'show'])
        ->middleware('permission:sites.view');
    Route::patch('/sites/{site}', [SiteController::class, 'update'])
        ->middleware('permission:sites.update');

        // --- Module 2: Charging Stations ---
    Route::get('/charging-stations', [ChargingStationController::class, 'index'])
        ->middleware('permission:charging_stations.view');
    Route::post('/charging-stations', [ChargingStationController::class, 'store'])
        ->middleware('permission:charging_stations.create');
    Route::get('/charging-stations/{station}', [ChargingStationController::class, 'show'])
        ->middleware('permission:charging_stations.view');
    Route::patch('/charging-stations/{station}', [ChargingStationController::class, 'update'])
        ->middleware('permission:charging_stations.update');
    Route::patch('/charging-stations/{station}/disable', [ChargingStationController::class, 'disable'])
        ->middleware('permission:charging_stations.lifecycle.update');
    Route::patch('/charging-stations/{station}/reactivate', [ChargingStationController::class, 'reactivate'])
        ->middleware('permission:charging_stations.lifecycle.update');
    Route::patch('/charging-stations/{station}/decommission', [ChargingStationController::class, 'decommission'])
        ->middleware('permission:charging_stations.decommission');
    Route::patch('/charging-stations/{station}/state', [ChargingStationController::class, 'updateState'])
        ->middleware('permission:charging_stations.state.update');
    Route::patch('/charging-stations/{station}/assignment', [ChargingStationController::class, 'assign'])
        ->middleware('permission:charging_stations.assign');
    Route::get('/charging-stations/{station}/history', [ChargingStationController::class, 'history'])
        ->middleware('permission:charging_stations.history.view');

        // --- Module 3: Connectors ---
    Route::get('/charging-stations/{station}/connectors', [ConnectorController::class, 'index'])
        ->middleware('permission:connectors.view');
    Route::post('/charging-stations/{station}/connectors', [ConnectorController::class, 'store'])
        ->middleware('permission:connectors.create');
    Route::get('/charging-stations/{station}/connectors/{connector}', [ConnectorController::class, 'show'])
        ->middleware('permission:connectors.view');
    Route::patch('/charging-stations/{station}/connectors/{connector}', [ConnectorController::class, 'update'])
        ->middleware('permission:connectors.update');
    Route::delete('/charging-stations/{station}/connectors/{connector}', [ConnectorController::class, 'destroy'])
        ->middleware('permission:connectors.delete');
    Route::patch('/charging-stations/{station}/connectors/{connector}/state', [ConnectorController::class, 'updateState'])
        ->middleware('permission:connectors.state.update');
    Route::patch('/charging-stations/{station}/connectors/{connector}/availability', [ConnectorController::class, 'updateAvailability'])
        ->middleware('permission:connectors.availability.update');

        // --- Module 4: Real-Time Supervision ---
    Route::get('/supervision/dashboard', [SupervisionController::class, 'dashboard'])
        ->middleware('permission:supervision.view');

        // --- Module 5: Charging Sessions ---
    Route::get('/charging-sessions', [ChargingSessionController::class, 'index'])
        ->middleware('permission:charging_sessions.view');
    Route::post('/charging-sessions', [ChargingSessionController::class, 'store'])
        ->middleware('permission:charging_sessions.create');
    Route::get('/charging-sessions/{session}', [ChargingSessionController::class, 'show'])
        ->middleware('permission:charging_sessions.view');
    Route::post('/charging-sessions/{session}/start', [ChargingSessionController::class, 'start'])
        ->middleware('permission:charging_sessions.start');
    Route::post('/charging-sessions/{session}/pause', [ChargingSessionController::class, 'pause'])
        ->middleware('permission:charging_sessions.pause');
    Route::post('/charging-sessions/{session}/resume', [ChargingSessionController::class, 'resume'])
        ->middleware('permission:charging_sessions.pause');
    Route::post('/charging-sessions/{session}/end', [ChargingSessionController::class, 'end'])
        ->middleware('permission:charging_sessions.end');
    Route::post('/charging-sessions/{session}/cancel', [ChargingSessionController::class, 'cancel'])
        ->middleware('permission:charging_sessions.cancel');
// --- OCPP Remote Control ---
    Route::post('/charging-stations/{station}/remote-start', [ChargingStationRemoteControlController::class, 'remoteStart'])
        ->middleware('permission:charging_stations.remote_control');
    Route::post('/charging-stations/{station}/charging-sessions/{session}/remote-stop', [ChargingStationRemoteControlController::class, 'remoteStop'])
        ->middleware('permission:charging_stations.remote_control');
    Route::post('/charging-stations/{station}/reset', [ChargingStationRemoteControlController::class, 'reset'])
        ->middleware('permission:charging_stations.remote_control');
    Route::post('/charging-stations/{station}/connectors/{connector}/unlock', [ChargingStationRemoteControlController::class, 'unlockConnector'])
        ->middleware('permission:charging_stations.remote_control');


});

// --- OCPP Server Integration: internal bridge ---
// Authenticated by a shared service secret (OCPP_BRIDGE_TOKEN via the
// ocpp_bridge middleware), never a human JWT, never Spatie permissions.
// Only the Python OCPP gateway calls these routes.
Route::middleware('ocpp_bridge')->prefix('internal/ocpp')->group(function () {
    Route::get('/ping', function () {
        return response()->json(['message' => 'OCPP bridge authenticated successfully.']);
    });

    Route::post('/events/heartbeat', [OcppBridgeController::class, 'heartbeat']);
    Route::post('/events/boot-notification', [OcppBridgeController::class, 'bootNotification']);
    Route::post('/events/status-notification', [OcppBridgeController::class, 'statusNotification']);
    Route::post('/transactions/start', [OcppBridgeController::class, 'startTransaction']);
    Route::post('/transactions/meter-values', [OcppBridgeController::class, 'meterValues']);
    Route::post('/transactions/stop', [OcppBridgeController::class, 'stopTransaction']);
    Route::post('/transactions/start-external', [OcppBridgeController::class, 'startTransactionExternal']);
    Route::post('/verify-station-credential', [OcppBridgeController::class, 'verifyStationCredential']);

});
