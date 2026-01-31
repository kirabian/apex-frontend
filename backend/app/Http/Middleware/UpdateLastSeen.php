<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        // Jalankan request-nya dulu sampai selesai
        $response = $next($request);

        // Baru setelah itu update last_seen secara "silent" (tanpa trigger events)
        if (Auth::check()) {
            $user = Auth::user();

            // Pastikan kolom last_seen ada sebelum update
            $user->timestamps = false; // Agar updated_at tidak ikut berubah
            $user->last_seen = now();
            $user->save();
        }

        return $response;
    }
}