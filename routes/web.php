<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

use App\Http\Controllers\ServerController;

use App\Http\Controllers\AlertController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ServerController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/servers', [ServerController::class, 'store'])->name('servers.store');
    Route::delete('/dashboard/servers/{server}', [ServerController::class, 'destroy'])->name('servers.destroy');
    Route::get('/dashboard/servers/{server}/metrics', [ServerController::class, 'metrics'])->name('servers.metrics');
    Route::get('/servers/{server}/logs', [ServerController::class, 'logs'])->name('servers.logs');
    Route::resource('alerts', AlertController::class)->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
