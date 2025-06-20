<?php

namespace App\Services\WorkflowEngine;

use App\Services\WorkflowEngine\Nodes\NodeHandlerInterface;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;

class WorkflowNodeHandlerFactory
{
    /**
     * Default namespace for node handlers.
     */
    protected string $handlerNamespace = 'App\\Services\\WorkflowEngine\\Nodes\\';

    /**
     * Cache of instantiated handlers.
     */
    protected array $handlers = [];

    /**
     * Get a handler for the given node type.
     */
    public function getHandler(string $nodeType): NodeHandlerInterface
    {
        if (isset($this->handlers[$nodeType])) {
            return $this->handlers[$nodeType];
        }

        $handlerClass = $this->resolveHandlerClass($nodeType);
        
        if (!class_exists($handlerClass)) {
            throw new InvalidArgumentException("No handler found for node type: {$nodeType}");
        }

        $handler = App::make($handlerClass);

        if (!$handler instanceof NodeHandlerInterface) {
            throw new InvalidArgumentException(
                "Handler for {$nodeType} must implement NodeHandlerInterface"
            );
        }

        return $this->handlers[$nodeType] = $handler;
    }

    /**
     * Resolve the handler class name from node type.
     */
    protected function resolveHandlerClass(string $nodeType): string
    {
        // Convert kebab-case to StudlyCase (e.g., 'http-request' => 'HttpRequest')
        $studlyName = str_replace(' ', '', ucwords(str_replace('-', ' ', $nodeType)));
        
        return $this->handlerNamespace . $studlyName . 'Handler';
    }

    /**
     * Register a custom handler for a node type.
     */
    public function registerHandler(string $nodeType, string $handlerClass): void
    {
        if (!is_subclass_of($handlerClass, NodeHandlerInterface::class)) {
            throw new InvalidArgumentException(
                "Handler class must implement " . NodeHandlerInterface::class
            );
        }

        $this->handlers[$nodeType] = App::make($handlerClass);
    }
}
