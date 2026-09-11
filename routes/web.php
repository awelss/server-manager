<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => false,
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ServerController::class, 'index'])->name('dashboard');
    Route::get('/infrastructure', [ServerController::class, 'infrastructure'])->name('infrastructure');
    Route::post('/dashboard/servers', [ServerController::class, 'store'])->name('servers.store');
    Route::delete('/dashboard/servers/{server}', [ServerController::class, 'destroy'])->name('servers.destroy');
    Route::get('/dashboard/servers/{server}/metrics', [ServerController::class, 'metrics'])->name('servers.metrics');
    Route::get('/servers/{server}/logs', [ServerController::class, 'logs'])->name('servers.logs');
    Route::resource('alerts', AlertController::class)->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/users/{user}/servers', [UserController::class, 'assignServers'])->name('admin.users.assignServers');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
