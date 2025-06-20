<?php

namespace App\Services\WorkflowEngine\Nodes;

interface NodeHandlerInterface
{
    /**
     * Handle the node execution.
     *
     * @param array $node The node configuration
     * @param array $context The execution context
     * @return array The result of the node execution
     */
    public function handle(array $node, array $context = []): array;

    /**
     * Get the node type that this handler can process.
     */
    public static function getType(): string;
}
