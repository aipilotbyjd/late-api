<?php

namespace App\Providers;

use App\Services\WorkflowEngine\Nodes\Gmail\SendEmailHandler;
use App\Services\WorkflowEngine\Nodes\Slack\SendMessageHandler;
use App\Services\WorkflowEngine\WorkflowNodeHandlerFactory;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    /**
     * The node handlers to register.
     *
     * @var array
     */
    protected $nodeHandlers = [
        SendMessageHandler::class,
        SendEmailHandler::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(WorkflowNodeHandlerFactory::class, function ($app) {
            $factory = new WorkflowNodeHandlerFactory();
            
            // Register all node handlers
            foreach ($this->nodeHandlers as $handler) {
                $factory->registerHandler($app->make($handler));
            }
            
            return $factory;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration files
        $this->publishes([
            __DIR__.'/../../config/workflow.php' => config_path('workflow.php'),
        ], 'config');
    }
}
