<?php

namespace App\Providers;

use App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Model::shouldBeStrict(! $this->app->isProduction());

        // Stable morph names keep activity_logs readable and refactor-safe.
        Relation::enforceMorphMap([
            'user' => Models\User::class,
            'unit' => Models\Unit::class,
            'loan' => Models\Loan::class,
            'lead' => Models\Lead::class,
            'customer' => Models\Customer::class,
        ]);
    }
}
