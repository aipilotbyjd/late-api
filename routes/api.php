<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\WorkflowController;
use App\Http\Controllers\Api\v1\OrganizationController;

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
        Route::post('workflows/{workflow}/execute', [WorkflowController::class, 'execute'])
            ->name('workflows.execute');

        // Workflow versions
        Route::get('workflows/{workflow}/versions', [WorkflowController::class, 'listVersions'])
            ->name('workflows.versions.index');
        Route::post('workflows/{workflow}/versions', [WorkflowController::class, 'storeVersion'])
            ->name('workflows.versions.store');
        Route::put('workflows/{workflow}/versions/{version}/activate', [WorkflowController::class, 'setActiveVersion'])
            ->name('workflows.versions.activate');

        // Organization routes
        Route::apiResource('organizations', OrganizationController::class);
    });

    // Public webhook endpoint (no auth required)
    Route::post('workflows/{workflow}/webhook/{token}', [WorkflowController::class, 'webhook'])
        ->name('api.workflows.webhook');
});
