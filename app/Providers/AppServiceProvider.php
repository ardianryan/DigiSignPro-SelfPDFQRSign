<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Throwable;

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

        Gate::define('admin-only', function (User $user) {
            return $user->role === 'admin';
        });

        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id
                ?: $request->header('X-API-Key')
                ?: $request->ip();

            return Limit::perMinute(60)->by((string) $key);
        });

        // Apply timezone from app_settings when available
        try {
            $settings = AppSetting::query()->first();
            if ($settings && ! empty($settings->timezone)) {
                config(['app.timezone' => $settings->timezone]);
                date_default_timezone_set($settings->timezone);
            }
        } catch (Throwable $e) {
            // DB may not be ready during early install/migrate
        }
    }
}
