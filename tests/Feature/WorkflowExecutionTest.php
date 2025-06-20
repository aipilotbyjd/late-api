<?php

namespace Tests\Feature;

use App\Jobs\ExecuteNodeJob;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkflowExecutionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function it_can_execute_a_workflow_manually()
    {
        Queue::fake();

        $workflow = Workflow::factory()->create([
            'status' => 'active',
            'workflow_json' => [
                'nodes' => [
                    [
                        'id' => 'start',
                        'type' => 'http-request',
                        'name' => 'Test Request',
                        'config' => [
                            'method' => 'GET',
                            'url' => 'https://example.com/api/test',
                        ],
                    ],
                ],
                'connections' => [],
            ],
        ]);

        $response = $this->postJson("/api/workflows/{$workflow->id}/execute", [
            'test' => 'data',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'execution_id',
            ]);

        Queue::assertPushed(ExecuteNodeJob::class, function ($job) use ($workflow) {
            return $job->workflow->id === $workflow->id
                && $job->nodeId === 'start'
                && $job->previousResults['input']['test'] === 'data';
        });
    }

    /** @test */
    public function it_can_handle_webhook_requests()
    {
        Queue::fake();

        $workflow = Workflow::factory()->create([
            'status' => 'active',
            'webhook_token' => 'test-token',
            'workflow_json' => [
                'nodes' => [
                    [
                        'id' => 'start',
                        'type' => 'http-request',
                        'name' => 'Webhook Handler',
                        'config' => [
                            'method' => 'POST',
                            'url' => 'https://example.com/api/webhook',
                        ],
                    ],
                ],
                'connections' => [],
            ],
        ]);

        $response = $this->postJson("/api/webhook/{$workflow->id}/test-token", [
            'event' => 'test',
            'data' => ['key' => 'value'],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'execution_id',
            ]);

        Queue::assertPushed(ExecuteNodeJob::class, function ($job) {
            return $job->nodeId === 'start';
        });
    }

    /** @test */
    public function it_prevents_unauthorized_webhook_access()
    {
        $workflow = Workflow::factory()->create([
            'status' => 'active',
            'webhook_token' => 'valid-token',
        ]);

        $response = $this->postJson("/api/webhook/{$workflow->id}/invalid-token");
        $response->assertStatus(403);
    }

    /** @test */
    public function it_tracks_workflow_execution()
    {
        $workflow = Workflow::factory()->create([
            'status' => 'active',
            'workflow_json' => [
                'nodes' => [
                    [
                        'id' => 'start',
                        'type' => 'http-request',
                        'name' => 'Test Request',
                        'config' => [
                            'method' => 'GET',
                            'url' => 'https://example.com/api/test',
                        ],
                    ],
                ],
                'connections' => [],
            ],
        ]);

        $execution = $workflow->executions()->create([
            'status' => WorkflowExecution::STATUS_RUNNING,
            'started_at' => now(),
            'trigger_type' => 'manual',
            'trigger_data' => ['user_id' => 1],
        ]);

        $job = new ExecuteNodeJob($workflow, $execution->id, ['test' => 'data']);
        $job->handle(app()->make(\App\Services\WorkflowEngine\WorkflowNodeHandlerFactory::class));

        $this->assertDatabaseHas('workflow_executions', [
            'id' => $execution->id,
            'status' => WorkflowExecution::STATUS_COMPLETED,
        ]);

        $this->assertDatabaseHas('workflow_execution_logs', [
            'workflow_execution_id' => $execution->id,
            'node_id' => 'start',
            'node_type' => 'http-request',
        ]);
    }
}
