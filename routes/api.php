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
        Route::post('workflows/{workflow}/execute', [WorkflowController::class, 'execute']);
        Route::get('workflows/{workflow}/webhook-url', [WorkflowController::class, 'webhookUrl']);
    });

    // Public webhook endpoint (no auth required)
    Route::post('workflows/{workflow}/webhook/{token}', [WorkflowController::class, 'webhook'])
        ->name('api.workflows.webhook');
});
