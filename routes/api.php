<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\WorkflowController;

Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/refresh', [AuthController::class, 'refreshToken']);

    // Protected routes (require authentication)
    Route::group(['middleware' => ['auth:api']], function () {
        // User routes
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Workflow routes
        Route::apiResource('workflows', WorkflowController::class);
        
        // Workflow execution
        Route::post('workflows/{workflow}/execute', [WorkflowController::class, 'execute']);
        
        // Webhook management
        Route::get('workflows/{workflow}/webhook-url', [WorkflowController::class, 'webhookUrl']);
        Route::post('workflows/{workflow}/regenerate-token', [WorkflowController::class, 'regenerateToken']);
        
        // Workflow versions
        Route::get('workflows/{workflow}/versions', [WorkflowController::class, 'versions']);
        Route::post('workflows/{workflow}/versions/{version}/activate', [WorkflowController::class, 'activateVersion']);
        
        // Execution history
        Route::get('workflows/{workflow}/executions', [WorkflowController::class, 'executions']);
        Route::get('workflows/{workflow}/executions/{execution}', [WorkflowController::class, 'execution']);
        
        // Workflow actions
        Route::post('workflows/{workflow}/duplicate', [WorkflowController::class, 'duplicate']);
        Route::post('workflows/{workflow}/export', [WorkflowController::class, 'export']);
        Route::post('workflows/import', [WorkflowController::class, 'import']);
    });

    // Public webhook endpoint (no auth required)
    Route::post('workflows/{workflow}/webhook/{token}', [WorkflowController::class, 'webhook'])
        ->name('api.workflows.webhook');
});
