<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ServerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $servers = $user->accessibleServers()
            ->with('latestMetric')
            ->orderBy('name')
            ->get()
            ->map(function ($server) {
                $server->is_online = $server->is_online;
                return $server;
            });

        return Inertia::render('Dashboard', [
            'servers' => $servers,
            'apiBaseUrl' => url('/'),
        ]);
    }

    public function infrastructure(Request $request)
    {
        $servers = $request->user()->accessibleServers()
            ->with('latestMetric')
            ->orderBy('name')
            ->get()
            ->map(function ($server) {
                $server->is_online = $server->is_online;
                return $server;
            });

        return Inertia::render('Infrastructure', [
            'servers' => $servers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|string|max:255',
        ]);

        $server = $request->user()->servers()->create([
            'name' => $validated['name'],
            'ip_address' => $validated['ip_address'],
            'agent_token' => Str::random(40),
            'status' => 'pending',
        ]);

        return redirect()->back()->with('flash', [
            'success' => 'Server registered successfully!',
            'new_server' => [
                'name' => $server->name,
                'agent_token' => $server->agent_token,
            ],
        ]);
    }

    public function destroy(Request $request, Server $server)
    {
        $user = $request->user();

        if (!$user->isAdmin() && $server->user_id !== $user->id) {
            abort(403);
        }

        $server->delete();

        return redirect()->back()->with('flash', [
            'success' => 'Server deleted successfully.',
        ]);
    }

    public function metrics(Request $request, Server $server)
    {
        $this->authorizeServerAccess($request->user(), $server);

        $metrics = $server->metrics()
            ->select([
                'cpu_usage',
                'cpu_iowait',
                'cpu_steal',
                'load_1',
                'load_5',
                'load_15',
                'ram_usage',
                'swap_usage',
                'zombie_processes',
                'process_count',
                'disk_usage',
                'disk_free_gb',
                'inode_usage',
                'network_rx_bytes',
                'network_tx_bytes',
                'uptime',
                'created_at',
            ])
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(2000)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'status' => 'success',
            'metrics' => $metrics,
        ]);
    }

    public function logs(Request $request, Server $server)
    {
        $this->authorizeServerAccess($request->user(), $server);

        $logs = $server->logs()
            ->orderByDesc('logged_at')
            ->paginate(100);

        return Inertia::render('Logs', [
            'server' => $server->only('id', 'name', 'ip_address'),
            'logs' => $logs,
        ]);
    }

    private function authorizeServerAccess($user, Server $server): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $accessible = $user->accessibleServers()->pluck('servers.id');
        if (!$accessible->contains($server->id)) {
            abort(403);
        }
    }
}
