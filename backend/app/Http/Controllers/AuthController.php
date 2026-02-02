<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda dinonaktifkan.',
                ], 403);
            }

            // Handle Remember Me
            $remember = $request->boolean('remember_me');

            if ($remember) {
                // Persistent token (no specific expiration set, Sanctum default is usually unlimited or config based)
                $token = $user->createToken('auth_token')->plainTextToken;
            } else {
                // 8 hours expiration
                $expiration = now()->addHours(8);
                $token = $user->createToken('auth_token', ['*'], $expiration)->plainTextToken;
            }

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => $user->load('branch', 'roles', 'warehouse', 'onlineShop'),
                'theme_color' => $user->theme_color, // TAMBAHKAN INI
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Login gagal. Periksa username dan password.',
        ], 401);
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()->load('branch', 'roles', 'warehouse', 'onlineShop'),
        ]);
    }
}
