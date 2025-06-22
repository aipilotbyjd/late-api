<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\Auth\SlackOAuthController;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\Api\v1\OrganizationController;

Route::prefix('v1')->group(function () {
    // Authentication routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/refresh', [AuthController::class, 'refreshToken']);

    // OAuth routes (no auth required for callbacks)
    Route::prefix('auth')->group(function () {
        // Slack OAuth
        Route::get('slack/redirect', [SlackOAuthController::class, 'redirect'])
            ->name('oauth.slack.redirect');
        Route::get('slack/callback', [SlackOAuthController::class, 'callback'])
            ->name('oauth.slack.callback');

        // Google OAuth
        Route::get('google/redirect', [GoogleOAuthController::class, 'redirect'])
            ->name('oauth.google.redirect');
        Route::get('google/callback', [GoogleOAuthController::class, 'callback'])
            ->name('oauth.google.callback');
    });

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
