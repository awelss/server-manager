<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Admin sees all rules, user sees own rules
        $rules = $user->isAdmin()
            ? AlertRule::with('server:id,name')->get()
            : $user->alertRules()->with('server:id,name')->get();

        // Server dropdown filtered by accessible servers
        $servers = $user->accessibleServers()->select('id', 'name')->get();

        return Inertia::render('Alerts', [
            'rules' => $rules,
            'servers' => $servers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'server_id' => 'nullable|exists:servers,id',
            'metric' => 'required|in:cpu,ram,disk',
            'operator' => 'required|in:>,>=,<,<=',
            'threshold' => 'required|numeric|min:0|max:100',
            'cooldown_minutes' => 'required|integer|min:1|max:1440',
            'whatsapp_number' => 'required|string|max:20',
        ]);

        $user = $request->user();

        // Verify server access if specified
        if ($validated['server_id']) {
            $hasAccess = $user->accessibleServers()
                ->where('servers.id', $validated['server_id'])
                ->exists();
            if (! $hasAccess) {
                abort(403);
            }
        }

        $user->alertRules()->create($validated);

        return redirect()->back()->with('flash', [
            'success' => 'Alert rule created successfully.',
        ]);
    }

    public function update(Request $request, AlertRule $alert)
    {
        $user = $request->user();
        if (! $user->isAdmin() && $alert->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'enabled' => 'sometimes|boolean',
            'server_id' => 'nullable|exists:servers,id',
            'metric' => 'sometimes|in:cpu,ram,disk',
            'operator' => 'sometimes|in:>,>=,<,<=',
            'threshold' => 'sometimes|numeric|min:0|max:100',
            'cooldown_minutes' => 'sometimes|integer|min:1|max:1440',
            'whatsapp_number' => 'sometimes|string|max:20',
        ]);

        $alert->update($validated);

        return redirect()->back()->with('flash', [
            'success' => 'Alert rule updated.',
        ]);
    }

    public function destroy(Request $request, AlertRule $alert)
    {
        $user = $request->user();
        if (! $user->isAdmin() && $alert->user_id !== $user->id) {
            abort(403);
        }

        $alert->delete();

        return redirect()->back()->with('flash', [
            'success' => 'Alert rule deleted.',
        ]);
    }
}
