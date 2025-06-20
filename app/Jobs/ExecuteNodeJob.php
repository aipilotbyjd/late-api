<?php

namespace App\Jobs;

use App\Models\Workflow;
use App\Services\WorkflowEngine\Context;
use App\Services\WorkflowEngine\NodeHandlerFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExecuteNodeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Workflow $workflow,
        public \App\Services\WorkflowEngine\WorkflowGraph $graph,
        public string $nodeId,
        public Context $context
    ) {}

    public function handle()
    {
        $node = collect($this->graph->nodes)->firstWhere('id', $this->nodeId);
        $handler = NodeHandlerFactory::resolve($node['type']);

        $newContext = $handler->handle($node['config'], $this->context);

        $nextNodeIds = collect($this->graph->connections)
            ->where('source', $this->nodeId)
            ->pluck('target')
            ->all();

        (new \App\Services\WorkflowEngine\Dispatcher())->dispatch(
            $this->workflow,
            $this->graph,
            $nextNodeIds,
            $newContext
        );
    }
}
