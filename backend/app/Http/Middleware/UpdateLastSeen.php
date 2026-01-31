<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Hanya update jika user sedang login
        if (auth()->check()) {
            $user = auth()->user();

            // Gunakan update senyap agar tidak memicu event observer yang mungkin crash
            $user->timestamps = false;
            $user->last_seen = now();
            $user->save();
        }

        return $next($request);
    }
}
