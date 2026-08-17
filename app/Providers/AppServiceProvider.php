<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Rate limit default untuk semua route api
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Login dibatasi lebih ketat: 3 percobaan / menit per IP + username
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip().'|'.strtolower((string) $request->input('username')));
        });
    }
}
