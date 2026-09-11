<?php

namespace App\Http\Controllers;

use App\Events\LogReceived;
use App\Events\MetricsUpdated;
use App\Jobs\CheckAlertThresholds;
use App\Models\Server;
use Illuminate\Http\Request;

class AgentAPIController extends Controller
{
    private function getAuthenticatedServer(Request $request): ?Server
    {
        $token = $request->header('X-Agent-Token') ?? $request->input('agent_token');

        if (!$token) {
            return null;
        }

        return Server::where('agent_token', $token)->first();
    }

    public function handshake(Request $request)
    {
        $server = $this->getAuthenticatedServer($request);

        if (!$server) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid Agent Token.',
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
            'message' => 'Handshake completed successfully for server: '.$server->name,
        ]);
    }

    public function reportMetrics(Request $request)
    {
        $server = $this->getAuthenticatedServer($request);

        if (!$server) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid Agent Token.',
            ], 401);
        }

        $validated = $request->validate([
            'cpu_usage' => 'required|numeric|min:0|max:100',
            'cpu_iowait' => 'nullable|numeric|min:0|max:100',
            'cpu_steal' => 'nullable|numeric|min:0|max:100',
            'load_1' => 'nullable|numeric|min:0|max:100000',
            'load_5' => 'nullable|numeric|min:0|max:100000',
            'load_15' => 'nullable|numeric|min:0|max:100000',
            'ram_usage' => 'required|numeric|min:0|max:100',
            'swap_usage' => 'nullable|numeric|min:0|max:100',
            'zombie_processes' => 'nullable|integer|min:0|max:1000000',
            'process_count' => 'nullable|integer|min:0|max:1000000',
            'disk_usage' => 'required|numeric|min:0|max:100',
            'disk_free_gb' => 'nullable|numeric|min:0|max:100000000',
            'inode_usage' => 'nullable|numeric|min:0|max:100',
            'network_rx_bytes' => 'nullable|integer|min:0',
            'network_tx_bytes' => 'nullable|integer|min:0',
            'uptime' => 'nullable|string|max:255',
        ]);

        $server->metrics()->create($validated);

        $server->update([
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        broadcast(new MetricsUpdated($server, $validated))->toOthers();
        CheckAlertThresholds::dispatch($server, $validated);

        $server->metrics()
            ->where('created_at', '<', now()->subHours(24))
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Metrics reported successfully',
        ]);
    }

    public function reportStatus(Request $request)
    {
        $server = $this->getAuthenticatedServer($request);

        if (!$server) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid Agent Token.',
            ], 401);
        }

        $validated = $request->validate([
            'services' => 'present|array|max:50',
            'services.*.name' => 'required|string|max:100',
            'services.*.state' => 'required|string|in:active,inactive,failed,activating,deactivating,reloading,unknown',

            'docker' => 'present|array|max:50',
            'docker.*.name' => 'required|string|max:150',
            'docker.*.state' => 'required|string|max:50',
            'docker.*.status' => 'nullable|string|max:255',
            'docker.*.cpu_percent' => 'nullable|numeric|min:0|max:10000',
            'docker.*.memory_percent' => 'nullable|numeric|min:0|max:100',
            'docker.*.memory_usage' => 'nullable|string|max:100',
            'docker.*.restart_count' => 'nullable|integer|min:0|max:100000000',

            'backup' => 'present|array',
            'backup.status' => 'required|string|in:ok,stale,missing,unknown',
            'backup.latest_file' => 'nullable|string|max:500',
            'backup.age_minutes' => 'nullable|integer|min:0',
            'backup.size_bytes' => 'nullable|integer|min:0',

            'http_checks' => 'present|array|max:30',
            'http_checks.*.url' => 'required|string|max:500',
            'http_checks.*.status_code' => 'required|integer|min:0|max:599',
            'http_checks.*.ok' => 'required|boolean',
            'http_checks.*.latency_ms' => 'nullable|numeric|min:0|max:600000',
        ]);

        $server->update([
            'service_status' => $validated['services'],
            'docker_status' => $validated['docker'],
            'backup_status' => $validated['backup'],
            'http_checks' => $validated['http_checks'],
            'status_checked_at' => now(),
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Infrastructure status updated',
        ]);
    }

    public function reportLogs(Request $request)
    {
        $server = $this->getAuthenticatedServer($request);

        if (!$server) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid Agent Token.',
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

        $server->logs()
            ->where('created_at', '<', now()->subDays(7))
            ->delete();

        return response()->json([
            'status' => 'success',
            'count' => count($validated['logs']),
        ]);
    }
}
