<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\ChargingStationController;


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

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/sessions', [SessionController::class, 'index']);
    Route::delete('/sessions/{sessionId}', [SessionController::class, 'destroy']);

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
});
