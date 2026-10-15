<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Di belakang nginx (TLS) + Caddy (HTTP): paksa semua URL yang
        // dibuat Laravel memakai https:// agar redirect tidak jadi http://.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Super admin bypasses all policy checks.
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
            return null;
        });

        // "Admin area" gate: any staff role (not plain employee).
        Gate::define('admin-access', fn (User $user) => $user->isAdmin());
    }
}
