<?php

namespace App\Services\WorkflowEngine;

use App\NodeHandlers;

class NodeHandlerFactory
{
    public static function resolve(string $type): NodeHandlers\BaseNodeHandler
    {
        $class = "App\\NodeHandlers\\" . ucfirst($type) . "Handler";
        if (!class_exists($class)) {
            throw new \Exception("Unknown node type: $type");
        }
        return new $class;
    }
}
