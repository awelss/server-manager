<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AgentAPIController;

Route::prefix('v1/agent')->middleware('throttle:agent')->group(function () {
    Route::post('/handshake', [AgentAPIController::class, 'handshake']);
    Route::post('/metrics', [AgentAPIController::class, 'reportMetrics']);
    Route::post('/logs', [AgentAPIController::class, 'reportLogs']);
});
