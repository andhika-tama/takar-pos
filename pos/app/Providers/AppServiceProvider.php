<?php

namespace App\Providers;

use App\Enums\Role;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        Gate::define('owner', fn (User $user) => $user->role === Role::Owner && $user->active);

        // Livewire re-applies the page route's auth and can: middleware on every action request; add ours.
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);
    }
}
