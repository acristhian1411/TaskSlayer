<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\TaskEventController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskExecutionController;
use App\Http\Controllers\Api\UserPointsLedgerController;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('tasks/{task}/complete', [TaskController::class, 'complete']);
    Route::post('tasks/{task}/uncomplete', [TaskController::class, 'uncomplete']);
    Route::post('tasks/{task}/checkpoints/{checkpoint}/complete', [TaskController::class, 'completeCheckpoint']);
    Route::post('tasks/{task}/checkpoints/{checkpoint}/uncomplete', [TaskController::class, 'uncompleteCheckpoint']);
    Route::post('tasks/{task}/executions/start', [TaskExecutionController::class, 'start']);
    Route::get('tasks/{task}/executions/current', [TaskExecutionController::class, 'currentForTask']);
    Route::post('task-executions/{taskExecution}/pause', [TaskExecutionController::class, 'pause']);
    Route::post('task-executions/{taskExecution}/resume', [TaskExecutionController::class, 'resume']);
    Route::post('task-executions/{taskExecution}/stop', [TaskExecutionController::class, 'stop']);
    Route::post('task-executions/{taskExecution}/complete', [TaskExecutionController::class, 'complete']);
    Route::get('task-executions/{taskExecution}/events', [TaskEventController::class, 'executionTimeline']);
    Route::get('task-executions/{taskExecution}/events/stats', [TaskEventController::class, 'executionStats']);
    Route::get('task-executions/metrics/summary', [TaskExecutionController::class, 'summary']);
    Route::get('task-executions/metrics/time-by-task', [TaskExecutionController::class, 'timeByTask']);
    Route::get('task-executions/metrics/daily-productivity', [TaskExecutionController::class, 'dailyProductivity']);
    Route::post('rewards/{reward}/redeem', [RewardController::class, 'redeem']);
    Route::get('points/balance', [RewardController::class, 'pointsBalance']);
    Route::get('points/ledger', [UserPointsLedgerController::class, 'index']);
    Route::get('points/ledger/{entry}', [UserPointsLedgerController::class, 'show']);
    Route::get('points/summary', [UserPointsLedgerController::class, 'summary']);
    Route::get('reward-redemptions/me', [RewardController::class, 'myRedemptions']);
    Route::get('reward-redemptions/me/{rewardRedemption}', [RewardController::class, 'showMyRedemption']);

    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('task-events', TaskEventController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::apiResource('task-executions', TaskExecutionController::class);
    Route::apiResource('rewards', RewardController::class);
});