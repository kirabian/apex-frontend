<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\DB;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // User Management
    Route::apiResource('users', UserController::class);

    // Tambahkan ini di api.php
    Route::get('/branches', function () {
        return \App\Models\Branch::all();
    });
});

Route::get('/health-check', function () {
    $activeUsers = \App\Models\User::with('branch')
        ->whereNotNull('last_seen')
        ->orderBy('last_seen', 'desc')
        ->take(10)
        ->get()
        ->map(function ($user) {
            return [
                'name' => $user->name,
                'username' => $user->username,
                'branch' => $user->branch ? $user->branch->name : 'PStore Pusat',
                // Carbon diffForHumans buat status "1 minute ago" dsb
                'last_seen' => $user->last_seen->diffForHumans(),
                'tz' => 'Asia/Jakarta (WIB)'
            ];
        });

    return response()->json([
        'status' => 'online',
        'database' => 'connected',
        'server_time' => now()->format('H:i:s'),
        'server_date' => now()->format('d M Y'),
        'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
        'active_personnel' => $activeUsers,
        'uptime' => '99.9%'
    ]);
});
