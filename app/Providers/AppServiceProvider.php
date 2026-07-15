<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(10)->by('login-ip:'.$request->ip()),
                Limit::perMinute(10)->by('login-email:'.$email),
            ];
        });

        RateLimiter::for('register', function (Request $request): array {
            return [
                Limit::perMinute(5)->by('register-ip:'.$request->ip()),
                Limit::perHour(20)->by('register-email:'.Str::lower(trim((string) $request->input('email')))),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request): array {
            return [
                Limit::perMinute(10)->by('two-factor-ip:'.$request->ip()),
                Limit::perMinute(5)->by('two-factor-session:'.$request->session()->getId()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request): array {
            return [
                Limit::perMinute(5)->by('password-reset-ip:'.$request->ip()),
                Limit::perMinute(5)->by('password-reset-email:'.Str::lower(trim((string) $request->input('email')))),
            ];
        });
    }
}
