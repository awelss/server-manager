<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Events\LogReceived;
use App\Events\MetricsUpdated;
use App\Jobs\CheckAlertThresholds;
use App\Models\Server;
use App\Models\ServerMetric;
use Illuminate\Support\Facades\Log;

class AgentAPIController extends Controller
{
    /**
     * Retrieve the server authenticated by the agent token.
     */
    private function getAuthenticatedServer(Request $request): ?Server
    {
        $token = $request->header('X-Agent-Token') ?? $request->input('agent_token');

        if (!$token) {
            return null;
        }

        return Server::where('agent_token', $token)->first();
    }

    /**
     * Perform the initial handshake to register server specifications.
     */
    public function handshake(Request $request)
    {
        $server = $this->getAuthenticatedServer($request);

        if (!$server) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid Agent Token.'
            ], 401);
        }

        $validated = $request->validate([
            'os_info' => 'required|string|max:255',
            'cpu_cores' => 'required|integer|min:1',
            'ram_total' => 'required|numeric|min:0.1',
            'disk_total' => 'required|numeric|min:0.1',
        ]);

        $server->update([
            'os_info' => $validated['os_info'],
            'cpu_cores' => $validated['cpu_cores'],
            'ram_total' => $validated['ram_total'],
            'disk_total' => $validated['disk_total'],
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Handshake completed successfully for server: ' . $server->name
        ]);
    }

    /**
     * Record a new batch of system resource metrics from the agent.
     */
    public function reportMetrics(Request $request)
    {
        $server = $this->getAuthenticatedServer($request);

        if (!$server) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid Agent Token.'
            ], 401);
        }

        $validated = $request->validate([
            'cpu_usage' => 'required|numeric|min:0|max:100',
            'ram_usage' => 'required|numeric|min:0|max:100',
            'disk_usage' => 'required|numeric|min:0|max:100',
            'uptime' => 'nullable|string|max:255',
        ]);

        // Create the metric entry
        $server->metrics()->create([
            'cpu_usage' => $validated['cpu_usage'],
            'ram_usage' => $validated['ram_usage'],
            'disk_usage' => $validated['disk_usage'],
            'uptime' => $validated['uptime'],
        ]);

        // Update server heartbeat status
        $server->update([
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        // Broadcast real-time update
        broadcast(new MetricsUpdated($server, $validated))->toOthers();

        // Check alert thresholds
        CheckAlertThresholds::dispatch($server, $validated);

        // Auto-pruning: Delete metrics older than 24 hours to prevent DB bloat
        $server->metrics()
            ->where('created_at', '<', now()->subHours(24))
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Metrics reported successfully'
        ]);
    }

    public function reportLogs(Request $request)
    {
        $server = $this->getAuthenticatedServer($request);

        if (!$server) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid Agent Token.'
            ], 401);
        }

        $validated = $request->validate([
            'logs' => 'required|array|max:50',
            'logs.*.source_file' => 'required|string|max:255',
            'logs.*.level' => 'required|string|in:debug,info,notice,warning,error,critical,alert,emergency',
            'logs.*.message' => 'required|string|max:2000',
            'logs.*.logged_at' => 'required|date',
        ]);

        foreach ($validated['logs'] as $logData) {
            $logEntry = $server->logs()->create($logData);
            broadcast(new LogReceived($server, $logEntry))->toOthers();
        }

        // Auto-prune logs older than 7 days
        $server->logs()
            ->where('created_at', '<', now()->subDays(7))
            ->delete();

        return response()->json([
            'status' => 'success',
            'count' => count($validated['logs'])
        ]);
    }
}
