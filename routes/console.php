<?php

use App\Models\Server;
use App\Models\ServerLog;
use App\Models\ServerMetric;
use Illuminate\Support\Facades\Schedule;

// Mark servers as offline if not seen in 30 seconds
Schedule::call(function () {
    Server::where('last_seen_at', '<', now()->subSeconds(30))
        ->where('status', 'online')
        ->update(['status' => 'offline']);
})->everyFifteenSeconds();

// Prune server logs older than 7 days
Schedule::command('model:prune', ['--model' => [ServerLog::class]])->daily();

// Safety net: prune old metrics
Schedule::call(function () {
    ServerMetric::where('created_at', '<', now()->subHours(24))->delete();
})->hourly();
