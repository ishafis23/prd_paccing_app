<?php

namespace App\Providers;

use App\Enums\RoleName;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Owner = super admin: lolos semua otorisasi.
        Gate::before(function ($user) {
            return $user->hasRole(RoleName::Owner->value) ? true : null;
        });
    }
}
