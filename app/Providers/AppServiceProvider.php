<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Login: 5 attempts per minute per email+IP combo
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();
            return Limit::perMinute(5)->by($key);
        });

        // 2FA challenge/enrollment verify: 5 attempts per minute per challenge_id
        RateLimiter::for('2fa-verify', function (Request $request) {
            $key = $request->input('challenge_id') ?? $request->input('enrollment_id') ?? $request->ip();
            return Limit::perMinute(5)->by($key);
        });

        // Forgot-password: 3 attempts per 10 minutes per email+IP
        RateLimiter::for('forgot-password', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();
            return Limit::perMinutes(10, 3)->by($key);
        });

        // Refresh: 30 attempts per minute per session_id cookie
        RateLimiter::for('refresh', function (Request $request) {
            return Limit::perMinute(30)->by($request->cookie('session_id') ?? $request->ip());
        });
    }
}
