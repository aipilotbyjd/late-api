<?php

namespace App\Providers;

use App\Services\WorkflowEngine\WorkflowNodeHandlerFactory;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(WorkflowNodeHandlerFactory::class, function ($app) {
            return new WorkflowNodeHandlerFactory();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register node handlers here if needed
    }
}
