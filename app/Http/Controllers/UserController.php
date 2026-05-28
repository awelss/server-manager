<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::withCount('servers')
            ->with('assignedServers:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'servers_count' => $user->servers_count,
                'assigned_servers' => $user->assignedServers->pluck('id'),
                'created_at' => $user->created_at->toDateTimeString(),
            ]);

        $servers = Server::select('id', 'name', 'ip_address')->orderBy('name')->get();

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'allServers' => $servers,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', Rules\Password::defaults()],
            'role' => 'required|in:admin,user',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        return redirect()->back()->with('flash', [
            'success' => 'User created successfully.',
        ]);
    }

    public function update(Request $request, User $user)
    {
        // Prevent admin from demoting themselves
        if ($user->id === $request->user()->id && $request->input('role') !== 'admin') {
            return redirect()->back()->withErrors([
                'role' => 'You cannot change your own role.',
            ]);
        }

        $validated = $request->validate([
            'role' => 'required|in:admin,user',
        ]);

        $user->update($validated);

        return redirect()->back()->with('flash', [
            'success' => 'User role updated.',
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return redirect()->back()->withErrors([
                'user' => 'You cannot delete your own account from here.',
            ]);
        }

        $user->delete();

        return redirect()->back()->with('flash', [
            'success' => 'User deleted.',
        ]);
    }

    public function assignServers(Request $request, User $user)
    {
        $validated = $request->validate([
            'server_ids' => 'present|array',
            'server_ids.*' => 'exists:servers,id',
        ]);

        $user->assignedServers()->sync($validated['server_ids']);

        return redirect()->back()->with('flash', [
            'success' => 'Server assignments updated.',
        ]);
    }
}
