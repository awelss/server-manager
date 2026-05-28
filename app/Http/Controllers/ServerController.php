<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ServerController extends Controller
{
    public function index(Request $request)
    {
        $servers = $request->user()->servers()
            ->with('latestMetric')
            ->orderBy('name')
            ->get()
            ->map(function ($server) {
                $server->is_online = $server->is_online;
                return $server;
            });

        return Inertia::render('Dashboard', [
            'servers' => $servers,
            'apiBaseUrl' => url('/')
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
                'agent_token' => $server->agent_token
            ]
        ]);
    }

    public function destroy(Request $request, Server $server)
    {
        if ($server->user_id !== $request->user()->id) {
            abort(403);
        }

        $server->delete();

        return redirect()->back()->with('flash', [
            'success' => 'Server deleted successfully.'
        ]);
    }

    public function metrics(Request $request, Server $server)
    {
        if ($server->user_id !== $request->user()->id) {
            abort(403);
        }

        $metrics = $server->metrics()
            ->select('cpu_usage', 'ram_usage', 'disk_usage', 'created_at')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'metrics' => $metrics
        ]);
    }

    public function logs(Request $request, Server $server)
    {
        if ($server->user_id !== $request->user()->id) {
            abort(403);
        }

        $logs = $server->logs()
            ->orderByDesc('logged_at')
            ->paginate(100);

        return Inertia::render('Logs', [
            'server' => $server->only('id', 'name', 'ip_address'),
            'logs' => $logs,
        ]);
    }
}
