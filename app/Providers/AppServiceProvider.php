<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\AppSetting;
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

        // Apply timezone from app_settings when available
        try {
            $settings = AppSetting::query()->first();
            if ($settings && !empty($settings->timezone)) {
                config(['app.timezone' => $settings->timezone]);
                date_default_timezone_set($settings->timezone);
            }
        } catch (Throwable $e) {
            // DB may not be ready during early install/migrate
        }
    }
}
