<?php

namespace App\Services;

use App\Jobs\ExecuteNodeJob;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Services\WorkflowEngine\WorkflowNodeHandlerFactory;
use App\Services\WorkflowEngine\WorkflowGraph;
use Illuminate\Support\Facades\Log;

class WorkflowService
{
    /**
     * Execute a workflow.
     */
    public function executeWorkflow(Workflow $workflow, string $triggerType, array $triggerData = []): WorkflowExecution
    {
        // Create a new execution record
        $execution = $workflow->executions()->create([
            'status' => WorkflowExecution::STATUS_RUNNING,
            'started_at' => now(),
            'trigger_type' => $triggerType,
            'trigger_data' => $triggerData,
            'input' => $triggerData['input'] ?? [],
        ]);

        try {
            // Dispatch the first node for execution
            $this->executeNextNode($workflow, $execution, 'start');
        } catch (\Exception $e) {
            Log::error('Failed to start workflow execution', [
                'workflow_id' => $workflow->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $execution->update([
                'status' => WorkflowExecution::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        return $execution->fresh();
    }

    /**
     * Execute the next node in the workflow.
     */
    public function executeNextNode(Workflow $workflow, WorkflowExecution $execution, string $nodeId, array $previousResults = [])
    {
        $workflowData = $workflow->workflow_json;
        
        // Find the node in the workflow
        $node = collect($workflowData['nodes'] ?? [])->firstWhere('id', $nodeId);
        
        if (!$node) {
            throw new \RuntimeException("Node {$nodeId} not found in workflow");
        }

        // Create execution log
        $log = $execution->logs()->create([
            'node_id' => $nodeId,
            'node_name' => $node['name'] ?? $node['type'],
            'node_type' => $node['type'],
            'level' => 'info',
            'message' => 'Node execution started',
            'data' => [
                'input' => $previousResults,
            ],
        ]);

        try {
            // Get the appropriate handler for this node type
            $handler = app(WorkflowNodeHandlerFactory::class)->getHandler($node['type']);
            
            // Execute the node with the current context
            $context = array_merge(
                ['execution_id' => $execution->id],
                $previousResults
            );
            
            $result = $handler->handle($node, $context);
            
            // Update the log with success
            $log->update([
                'status' => 'completed',
                'message' => 'Node executed successfully',
                'data' => array_merge($log->data ?? [], [
                    'output' => $result,
                    'completed_at' => now()->toDateTimeString(),
                ]),
            ]);

            // Find and execute connected nodes
            $connections = collect($workflowData['connections'] ?? [])
                ->where('source', $nodeId)
                ->all();

            foreach ($connections as $connection) {
                $this->executeNextNode(
                    $workflow,
                    $execution,
                    $connection['target'],
                    $result
                );
            }

            // If this was the last node, mark execution as complete
            if (empty($connections)) {
                $execution->update([
                    'status' => WorkflowExecution::STATUS_COMPLETED,
                    'finished_at' => now(),
                    'output' => $result,
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            // Log the error
            $log->update([
                'status' => 'failed',
                'level' => 'error',
                'message' => $e->getMessage(),
                'data' => array_merge($log->data ?? [], [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'failed_at' => now()->toDateTimeString(),
                ]),
            ]);

            // Mark execution as failed
            $execution->update([
                'status' => WorkflowExecution::STATUS_FAILED,
                'error' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the execution status.
     */
    public function getExecutionStatus(WorkflowExecution $execution): array
    {
        return [
            'status' => $execution->status,
            'started_at' => $execution->started_at,
            'finished_at' => $execution->finished_at,
            'execution_time' => $execution->finished_at 
                ? $execution->started_at->diffInSeconds($execution->finished_at) 
                : $execution->started_at->diffInSeconds(now()),
            'logs' => $execution->logs()->orderBy('created_at')->get(),
        ];
    }
}
