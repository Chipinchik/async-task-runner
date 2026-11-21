<?php

namespace App\Providers;

use App\Models\User;
use App\Queue\TaskflowConsumer;
use App\Services\WorkerIdentity;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WorkerIdentity::class);
        $this->app->singleton('rabbitmq.consumer', fn ($app) => new TaskflowConsumer(
            $app['queue'], $app['events'], $app[ExceptionHandler::class], fn () => $app->isDownForMaintenance()
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->role === 'admin');
    }
}
