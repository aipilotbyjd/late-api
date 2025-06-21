<?php

namespace App\Http\Controllers;

use App\Jobs\ExecuteNodeJob;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowVersion;
use App\Services\WorkflowEngine\Runner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    /**
     * Display a listing of the workflows.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Workflow::with(['project.team', 'latestVersion'])
            ->withCount('executions')
            ->latest();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by name or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $workflows = $query->paginate($request->per_page ?? 15);

        return response()->json($workflows);
    }

    /**
     * Store a newly created workflow in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'workflow_json' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $workflow = DB::transaction(function () use ($request) {
            $workflow = Workflow::create($request->only('project_id', 'name', 'description'));

            $version = $workflow->versions()->create([
                'name' => 'Initial Version',
                'description' => 'Initial version of the workflow',
                'workflow_json' => $request->workflow_json,
                'is_active' => true,
            ]);

            $workflow->update(['active_version_id' => $version->id]);

            return $workflow;
        });

        return response()->json($workflow->load('activeVersion'), 201);
    }

    /**
     * Display the specified workflow.
     */
    public function show(Workflow $workflow): JsonResponse
    {
        return response()->json(
            $workflow->load([
                'project.team',
                'versions' => function ($query) {
                    $query->latest()->limit(5);
                },
                'executions' => function ($query) {
                    $query->latest()->limit(5);
                }
            ])
        );
    }

    /**
     * Update the specified workflow in storage.
     */
    public function update(Request $request, Workflow $workflow): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:' . implode(',', [
                Workflow::STATUS_ACTIVE,
                Workflow::STATUS_PAUSED,
                Workflow::STATUS_DRAFT,
            ]),
            'trigger_type' => 'nullable|in:' . implode(',', [
                Workflow::TRIGGER_WEBHOOK,
                Workflow::TRIGGER_POLLING,
                Workflow::TRIGGER_SCHEDULE,
                Workflow::TRIGGER_MANUAL,
            ]),
            'is_public' => 'sometimes|boolean',
            'cron_expression' => 'nullable|string|required_if:trigger_type,' . Workflow::TRIGGER_SCHEDULE,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $workflow->update($validator->validated());

        return response()->json($workflow->load('activeVersion'));
    }



    /**
     * List all versions of a workflow.
     */
    public function listVersions(Workflow $workflow): JsonResponse
    {
        return response()->json($workflow->versions()->paginate());
    }

    /**
     * Create a new version for a workflow.
     */
    public function storeVersion(Request $request, Workflow $workflow): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'workflow_json' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $version = $workflow->versions()->create($validator->validated());

        return response()->json($version, 201);
    }

    /**
     * Set the active version for a workflow.
     */
    public function setActiveVersion(Workflow $workflow, WorkflowVersion $version): JsonResponse
    {
        DB::transaction(function () use ($workflow, $version) {
            $workflow->versions()->where('id', '!=', $version->id)->update(['is_active' => false]);
            $version->update(['is_active' => true]);
            $workflow->update(['active_version_id' => $version->id]);
        });

        return response()->json($workflow->load('activeVersion'));
    }

    /**
     * Remove the specified workflow from storage.
     */
    public function destroy(Workflow $workflow): JsonResponse
    {
        DB::transaction(function () use ($workflow) {
            // Soft delete related records
            $workflow->executions()->delete();
            $workflow->versions()->delete();
            $workflow->delete();
        });

        return response()->json(null, 204);
    }

    /**
     * Execute a workflow manually.
     */
    public function execute(Workflow $workflow, Request $request): JsonResponse
    {
        if (!$workflow->isActive()) {
            return response()->json([
                'message' => 'Workflow is not active',
            ], 400);
        }

        $execution = $workflow->executions()->create([
            'status' => WorkflowExecution::STATUS_RUNNING,
            'started_at' => now(),
            'trigger_type' => Workflow::TRIGGER_MANUAL,
            'trigger_data' => [
                'user_id' => $request->user()->id,
                'input' => $request->input(),
            ],
        ]);

        // Dispatch the job to execute the workflow
        $this->dispatchWorkflowJob($workflow, $execution->id, [
            'trigger' => 'manual',
            'user_id' => $request->user()->id,
            'input' => $request->input(),
        ]);

        return response()->json([
            'message' => 'Workflow execution started',
            'execution_id' => $execution->id,
        ]);
    }

    /**
     * Handle webhook request.
     */
    public function webhook(Workflow $workflow, string $token, Request $request): JsonResponse
    {
        if ($workflow->webhook_token !== $token) {
            return response()->json([
                'message' => 'Invalid webhook token',
            ], 403);
        }

        if (!$workflow->isActive()) {
            return response()->json([
                'message' => 'Workflow is not active',
            ], 400);
        }

        $execution = $workflow->executions()->create([
            'status' => WorkflowExecution::STATUS_RUNNING,
            'started_at' => now(),
            'trigger_type' => Workflow::TRIGGER_WEBHOOK,
            'trigger_data' => [
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
                'input' => $request->input(),
            ],
        ]);

        // Dispatch the job to execute the workflow
        $this->dispatchWorkflowJob($workflow, $execution->id, [
            'trigger' => 'webhook',
            'webhook_token' => $token,
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
            'input' => $request->all(),
        ]);

        return response()->json([
            'message' => 'Webhook received and workflow execution started',
            'execution_id' => $execution->id,
        ]);
    }

    /**
     * Dispatch a workflow execution job.
     */
    protected function dispatchWorkflowJob(Workflow $workflow, string $executionId, array $data = []): void
    {
        ExecuteNodeJob::dispatch(
            $workflow,
            $executionId,
            $data
        )->onQueue('workflows');
    }

    /**
     * Get the webhook URL for a workflow.
     */
    public function webhookUrl(Workflow $workflow): JsonResponse
    {
        return response()->json([
            'url' => $workflow->getWebhookUrl(),
            'method' => 'POST',
            'content_type' => 'application/json',
        ]);
    }

    /**
     * Get execution history for a workflow.
     */
    public function executions(Workflow $workflow, Request $request): JsonResponse
    {
        $executions = $workflow->executions()
            ->withCount('logs')
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json($executions);
    }

    /**
     * Get details of a specific execution.
     */
    public function execution(Workflow $workflow, string $executionId): JsonResponse
    {
        $execution = $workflow->executions()
            ->with('logs')
            ->findOrFail($executionId);

        return response()->json($execution);
    }

    /**
     * Get all versions of a workflow.
     */
    public function versions(Workflow $workflow): JsonResponse
    {
        $versions = $workflow->versions()
            ->latest()
            ->paginate(15);

        return response()->json($versions);
    }

    /**
     * Activate a specific version of a workflow.
     */
    public function activateVersion(Workflow $workflow, string $versionId): JsonResponse
    {
        $version = $workflow->versions()->findOrFail($versionId);
        $version->activate();

        return response()->json([
            'message' => 'Version activated successfully',
            'workflow' => $workflow->fresh(['versions']),
        ]);
    }

    /**
     * Regenerate the webhook token for a workflow.
     */
    public function regenerateToken(Workflow $workflow): JsonResponse
    {
        $workflow->update([
            'webhook_token' => Str::random(40),
        ]);

        return response()->json([
            'message' => 'Webhook token regenerated',
            'webhook_url' => $workflow->getWebhookUrl(),
        ]);
    }

    /**
     * Duplicate an existing workflow.
     */
    public function duplicate(Workflow $workflow): JsonResponse
    {
        return DB::transaction(function () use ($workflow) {
            $newWorkflow = $workflow->replicate();
            $newWorkflow->name = $workflow->name . ' (Copy)';
            $newWorkflow->status = Workflow::STATUS_DRAFT;
            $newWorkflow->webhook_token = Str::random(40);
            $newWorkflow->push();

            // Duplicate the active version
            $activeVersion = $workflow->versions()->where('is_active', true)->first();
            if ($activeVersion) {
                $newVersion = $activeVersion->replicate();
                $newVersion->workflow_id = $newWorkflow->id;
                $newVersion->is_active = true;
                $newVersion->save();
            }

            return response()->json($newWorkflow->load('versions'), 201);
        });
    }

    /**
     * Export a workflow as JSON.
     */
    public function export(Workflow $workflow): JsonResponse
    {
        $workflow->load('versions');

        return response()->json([
            'workflow' => $workflow->toArray(),
            'exported_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Import a workflow from JSON.
     */
    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'workflow' => 'required|array',
            'workflow.name' => 'required|string|max:255',
            'workflow.project_id' => 'required|exists:projects,id',
            'workflow.versions' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $workflowData = $request->workflow;

            // Create the workflow
            $workflow = Workflow::create([
                'project_id' => $workflowData['project_id'],
                'name' => $workflowData['name'],
                'description' => $workflowData['description'] ?? null,
                'status' => Workflow::STATUS_DRAFT,
                'workflow_json' => $workflowData['workflow_json'] ?? [],
                'trigger_type' => $workflowData['trigger_type'] ?? null,
                'webhook_token' => Str::random(40),
                'is_public' => $workflowData['is_public'] ?? false,
                'cron_expression' => $workflowData['cron_expression'] ?? null,
                'settings' => $workflowData['settings'] ?? [],
            ]);

            // Import versions
            foreach ($workflowData['versions'] as $versionData) {
                $workflow->versions()->create([
                    'version' => $versionData['version'],
                    'name' => $versionData['name'],
                    'description' => $versionData['description'] ?? null,
                    'workflow_json' => $versionData['workflow_json'],
                    'is_active' => $versionData['is_active'] ?? false,
                    'notes' => $versionData['notes'] ?? null,
                ]);
            }

            // Activate the first version if none is active
            if (!$workflow->versions()->where('is_active', true)->exists()) {
                $workflow->versions()->first()->update(['is_active' => true]);
            }

            return response()->json($workflow->load('versions'), 201);
        });
    }
}
