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
});

Route::get('/health-check', function () {
    return response()->json([
        'status' => 'online',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'uptime' => '99.9%', // Bisa dikembangkan dengan uptime asli
        'server_time' => now()->toDateTimeString(),
        'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
    ]);
});
