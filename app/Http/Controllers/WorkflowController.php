<?php

namespace App\Http\Controllers;

use App\Jobs\ExecuteNodeJob;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Models\WorkflowVersion;
use App\Services\WorkflowEngine\Runner;
use Composer\Semver\Comparator;
use Cron\CronExpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    /**
     * Check if a cron expression is valid.
     *
     * @param string|null $expression
     * @return bool
     */
    /**
     * Check if a cron expression is valid.
     */
    protected function isValidCronExpression($expression): bool
    {
        if (empty($expression)) {
            return false;
        }

        try {
            CronExpression::factory($expression);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the next version number based on the current version.
     * Follows semantic versioning with support for pre-release versions.
     */
    protected function getNextVersion(string $currentVersion = null): string
    {
        if (!$currentVersion) {
            return '1.0.0';
        }

        // Try to parse semantic version
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-([\w-]+(?:\.[\w-]+)*))?(?:\+([\w-]+(?:\.[\w-]+)*))?$/', $currentVersion, $matches)) {
            $major = (int)$matches[1];
            $minor = (int)$matches[2];
            $patch = (int)$matches[3];
            $preRelease = $matches[4] ?? null;

            // Handle pre-release versions (e.g., 1.0.0-alpha.1)
            if (str_starts_with($preRelease ?? '', 'draft.')) {
                $draftNumber = (int)substr($preRelease, 6) + 1;
                return "{$major}.{$minor}.{$patch}-draft.{$draftNumber}";
            }

            // Bump patch version by default
            return "{$major}.{$minor}." . ($patch + 1);
        }

        // Fallback: append .1 if not a semantic version
        return $currentVersion . '.1';
    }

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
            'status' => 'required|in:' . implode(',', [
                Workflow::STATUS_ACTIVE,
                Workflow::STATUS_PAUSED,
                Workflow::STATUS_DRAFT,
                Workflow::STATUS_ERROR,
            ]),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $workflow = DB::transaction(function () use ($request) {
            $workflow = Workflow::create($request->only('project_id', 'name', 'description', 'workflow_json', 'status'));

            $version = $workflow->versions()->create([
                'version' => '1.0.0',
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
                Workflow::STATUS_ERROR,
            ]),
            'trigger_type' => [
                'nullable',
                'in:' . implode(',', [
                    Workflow::TRIGGER_WEBHOOK,
                    Workflow::TRIGGER_POLLING,
                    Workflow::TRIGGER_SCHEDULE,
                    Workflow::TRIGGER_MANUAL,
                ]),
                function ($attribute, $value, $fail) use ($workflow, $request) {
                    if ($value === Workflow::TRIGGER_WEBHOOK && empty($workflow->webhook_token) && empty($request->webhook_token)) {
                        // Webhook token will be generated automatically
                        return;
                    }
                },
            ],
            'is_public' => 'sometimes|boolean',
            'cron_expression' => [
                'nullable',
                'string',
                Rule::requiredIf(function () use ($request) {
                    return $request->trigger_type === Workflow::TRIGGER_SCHEDULE;
                }),
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->trigger_type === Workflow::TRIGGER_SCHEDULE && !$this->isValidCronExpression($value)) {
                        $fail('The cron expression is not valid.');
                    }
                },
            ],
            'workflow_json' => 'sometimes|array',
            'version_notes' => 'nullable|string|required_with:workflow_json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->only(['name', 'description', 'status', 'trigger_type', 'is_public']);

            // Handle cron expression for scheduled workflows
            if ($request->has('trigger_type')) {
                if ($request->trigger_type === Workflow::TRIGGER_SCHEDULE) {
                    $updateData['cron_expression'] = $request->cron_expression;
                } else {
                    $updateData['cron_expression'] = null;
                }
            }

            // Generate webhook token if changing to webhook trigger and no token exists
            if (($request->trigger_type === Workflow::TRIGGER_WEBHOOK || $workflow->trigger_type === Workflow::TRIGGER_WEBHOOK) &&
                empty($workflow->webhook_token)
            ) {
                $updateData['webhook_token'] = Str::random(40);
            }

            $workflow->update($updateData);

            // If workflow_json is being updated, create a new version
            if ($request->has('workflow_json')) {
                $latestVersion = $workflow->versions()->latest()->first();

                // Parse and increment version similar to n8n
                if ($latestVersion) {
                    $currentVersion = $latestVersion->version;
                    // Try to parse semantic version (e.g., 1.2.3)
                    if (preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-([\w-]+(?:\.[\w-]+)*))?(?:\+([\w-]+(?:\.[\w-]+)*))?$/', $currentVersion, $matches)) {
                        $major = (int)$matches[1];
                        $minor = (int)$matches[2];
                        $patch = (int)$matches[3];
                        $preRelease = $matches[4] ?? null;
                        $build = $matches[5] ?? null;

                        // If this is a draft version (e.g., 1.2.3-draft.1), increment the draft number
                        if (str_starts_with($preRelease ?? '', 'draft.')) {
                            $draftNumber = (int)substr($preRelease, 6) + 1;
                            $newVersion = "{$major}.{$minor}.{$patch}-draft.{$draftNumber}";
                        } else {
                            // Otherwise, increment the patch version
                            $newVersion = "{$major}.{$minor}." . ($patch + 1);
                        }
                    } else {
                        // Fallback for non-semver versions
                        $newVersion = $currentVersion . '.1';
                    }
                } else {
                    // First version
                    $newVersion = '1.0.0';
                }
                // Create new version with proper metadata
                $version = $workflow->versions()->create([
                    'version' => $newVersion,
                    'name' => 'v' . $newVersion,
                    'description' => $request->version_notes ?? 'Workflow updated',
                    'workflow_json' => $request->workflow_json,
                    'is_active' => true,
                    'created_by' => auth()->user() ? auth()->user()->id : null,
                    'updated_by' => auth()->user() ? auth()->user()->id : null,
                    'nodes' => count($request->workflow_json['nodes'] ?? []),
                    'connections' => count($request->workflow_json['edges'] ?? []),
                ]);

                // Deactivate old versions and set the new one as active
                $workflow->versions()
                    ->where('id', '!=', $version->id)
                    ->update(['is_active' => false]);

                $workflow->update(['active_version_id' => $version->id]);
            }

            DB::commit();

            return response()->json($workflow->fresh()->load('activeVersion'));
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update workflow: ' . $e->getMessage(), [
                'exception' => $e,
                'workflow_id' => $workflow->id,
                'request' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Failed to update workflow',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
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
