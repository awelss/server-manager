<?php

use App\Http\Controllers\AgentAPIController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/agent')->middleware('throttle:agent')->group(function () {
    Route::post('/handshake', [AgentAPIController::class, 'handshake']);
    Route::post('/metrics', [AgentAPIController::class, 'reportMetrics']);
    Route::post('/status', [AgentAPIController::class, 'reportStatus']);
    Route::post('/logs', [AgentAPIController::class, 'reportLogs']);
});
