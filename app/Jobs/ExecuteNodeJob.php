<?php

namespace App\Jobs;

use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Services\WorkflowEngine\WorkflowNodeHandlerFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteNodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [5, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Workflow $workflow,
        public string $executionId,
        public array $previousResults = [],
        public ?string $nodeId = 'start'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WorkflowNodeHandlerFactory $handlerFactory)
    {
        try {
            $workflowData = $this->workflow->workflow_json;
            $execution = WorkflowExecution::findOrFail($this->executionId);

            // If execution is already completed or failed, don't process
            if ($execution->status !== WorkflowExecution::STATUS_RUNNING) {
                Log::warning("Execution {$this->executionId} is not in running state");
                return;
            }


            // Find the current node in the workflow
            $node = collect($workflowData['nodes'] ?? [])->firstWhere('id', $this->nodeId);
            
            if (!$node) {
                throw new \RuntimeException("Node {$this->nodeId} not found in workflow");
            }

            // Create execution log
            $log = $execution->logs()->create([
                'node_id' => $this->nodeId,
                'node_name' => $node['name'] ?? $node['type'],
                'node_type' => $node['type'],
                'level' => 'info',
                'message' => 'Node execution started',
                'data' => [
                    'input' => $this->previousResults,
                ],
            ]);

            try {
                // Get the appropriate handler for this node type
                $handler = $handlerFactory->getHandler($node['type']);
                
                // Prepare context for the node
                $context = array_merge(
                    ['execution_id' => $execution->id],
                    $this->previousResults
                );
                
                // Execute the node
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

                // Find and dispatch connected nodes
                $connections = collect($workflowData['connections'] ?? [])
                    ->where('source', $this->nodeId)
                    ->all();

                foreach ($connections as $connection) {
                    self::dispatch(
                        $this->workflow,
                        $this->executionId,
                        $result,
                        $connection['target']
                    )->onQueue('workflows');
                }

                // If this was the last node, mark execution as complete
                if (empty($connections)) {
                    $execution->update([
                        'status' => WorkflowExecution::STATUS_COMPLETED,
                        'finished_at' => now(),
                        'output' => $result,
                    ]);
                }

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

                throw $e; // Re-throw to trigger job retry if needed
            }

        } catch (\Exception $e) {
            Log::error('Error in ExecuteNodeJob: ' . $e->getMessage(), [
                'workflow_id' => $this->workflow->id,
                'execution_id' => $this->executionId,
                'node_id' => $this->nodeId,
                'exception' => $e,
            ]);
            
            // If we have an execution but it's not updated yet
            if (isset($execution)) {
                $execution->update([
                    'status' => WorkflowExecution::STATUS_FAILED,
                    'error' => $e->getMessage(),
                    'finished_at' => now(),
                ]);
            }
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        // Only log the final failure after all retries
        Log::error('ExecuteNodeJob failed after all retries', [
            'workflow_id' => $this->workflow->id,
            'execution_id' => $this->executionId,
            'node_id' => $this->nodeId,
            'error' => $exception->getMessage(),
        ]);
    }
}
