<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Services\WorkflowEngine\Runner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WorkflowController extends Controller
{
    /**
     * Display a listing of the workflows.
     */
    public function index(): JsonResponse
    {
        $workflows = Workflow::with('project.team')
            ->latest()
            ->paginate(15);

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
            'status' => 'required|in:active,paused,draft',
            'workflow_json' => 'required|array',
            'trigger_type' => 'nullable|in:webhook,polling,schedule',
            'is_public' => 'boolean',
            'cron_expression' => 'nullable|string|required_if:trigger_type,schedule',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workflow = Workflow::create(array_merge(
            $validator->validated(),
            ['webhook_token' => $request->input('webhook_token') ?? bin2hex(random_bytes(32))]
        ));

        return response()->json($workflow, 201);
    }

    /**
     * Display the specified workflow.
     */
    public function show(Workflow $workflow): JsonResponse
    {
        return response()->json($workflow->load('project.team'));
    }

    /**
     * Update the specified workflow in storage.
     */
    public function update(Request $request, Workflow $workflow): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:active,paused,draft',
            'workflow_json' => 'sometimes|required|array',
            'trigger_type' => 'nullable|in:webhook,polling,schedule',
            'is_public' => 'boolean',
            'cron_expression' => 'nullable|string|required_if:trigger_type,schedule',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $workflow->update($validator->validated());

        return response()->json($workflow);
    }

    /**
     * Remove the specified workflow from storage.
     */
    public function destroy(Workflow $workflow): JsonResponse
    {
        $workflow->delete();

        return response()->json(null, 204);
    }

    /**
     * Execute the specified workflow.
     */
    public function execute(Workflow $workflow, Runner $runner): JsonResponse
    {
        try {
            $runner->start($workflow);

            return response()->json([
                'message' => 'Workflow execution started successfully',
                'workflow_id' => $workflow->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to execute workflow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the webhook URL for the workflow.
     */
    public function webhookUrl(Workflow $workflow): JsonResponse
    {
        if ($workflow->trigger_type !== 'webhook') {
            return response()->json([
                'message' => 'This workflow is not configured for webhook triggers',
            ], 400);
        }

        return response()->json([
            'webhook_url' => route('api.workflows.webhook', [
                'workflow' => $workflow->id,
                'token' => $workflow->webhook_token,
            ]),
        ]);
    }

    /**
     * Handle incoming webhook requests.
     */
    public function webhook(Request $request, Workflow $workflow, string $token): JsonResponse
    {
        if ($workflow->webhook_token !== $token) {
            return response()->json([
                'message' => 'Invalid webhook token',
            ], 401);
        }

        if ($workflow->status !== 'active') {
            return response()->json([
                'message' => 'Workflow is not active',
            ], 400);
        }

        try {
            $runner = app(Runner::class);
            $runner->start($workflow);

            return response()->json([
                'message' => 'Workflow execution started',
                'workflow_id' => $workflow->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to execute workflow',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
