<?php

namespace App\Services\WorkflowEngine;

use App\Jobs\ExecuteNodeJob;
use App\Models\Workflow;

class Dispatcher
{
    public function dispatch(Workflow $workflow, WorkflowGraph $graph, array $nodeIds, Context $context)
    {
        foreach ($nodeIds as $id) {
            ExecuteNodeJob::dispatch($workflow, $graph, $id, $context);
        }
    }
}
