<?php

namespace App\Services\WorkflowEngine;

use App\Models\Workflow;

class Runner
{
    public function start(Workflow $workflow)
    {
        // Use workflow_json instead of json to match the database schema
        $graphData = $workflow->workflow_json;
        $graph = new WorkflowGraph($graphData['nodes'] ?? [], $graphData['connections'] ?? []);

        $dispatcher = new Dispatcher();
        $context = new Context();

        $startNodes = $graph->findStartNodes();
        $dispatcher->dispatch($workflow, $graph, $startNodes, $context);
    }
}
