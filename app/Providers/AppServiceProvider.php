<?php

namespace App\Providers;

use App\Models\MxTask;
use App\Models\ServiceTask;
use App\Observers\MxTaskObserver;
use App\Observers\ServiceTaskObserver;
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
        ServiceTask::observe(ServiceTaskObserver::class);
        MxTask::observe(MxTaskObserver::class);
    }
}
