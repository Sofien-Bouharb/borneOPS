<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/verify-2fa', [AuthController::class, 'verifyTwoFactor']);
Route::post('/login/setup-2fa', [AuthController::class, 'completeTwoFactorEnrollment']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/forgot-password', [PasswordResetController::class, 'forgot']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);
Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
});
