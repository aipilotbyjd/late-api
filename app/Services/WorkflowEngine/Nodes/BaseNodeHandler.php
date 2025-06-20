<?php

namespace App\Services\WorkflowEngine\Nodes;

abstract class BaseNodeHandler implements NodeHandlerInterface
{
    /**
     * The type of the node this handler can process.
     */
    protected static string $type;

    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        if (!isset(static::$type)) {
            throw new \RuntimeException('Node type is not defined');
        }
        return static::$type;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $node, array $context = []): array
    {
        if (!isset($node['id']) || !isset($node['type']) || $node['type'] !== static::getType()) {
            throw new \InvalidArgumentException('Invalid node configuration');
        }

        return $this->process($node, $context);
    }

    /**
     * Process the node.
     *
     * @param array $node The node configuration
     * @param array $context The execution context
     * @return array The result of the node execution
     */
    abstract protected function process(array $node, array $context = []): array;
}
