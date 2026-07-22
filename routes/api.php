<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;

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
});
