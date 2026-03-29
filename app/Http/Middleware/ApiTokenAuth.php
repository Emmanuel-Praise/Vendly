<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        $hashedToken = hash('sha256', $token);
        
        $user = \App\Models\User::where('api_token', $hashedToken)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token',
                'data'    => null
            ], 401);
        }

        Auth::login($user);
        
        return $next($request);
    }
}
