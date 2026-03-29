<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class ApiTokenOptionalAuth
{
    /**
     * Handle an incoming request.
     * Allow guest access but set Auth user if valid token is provided.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        
        if ($token) {
            $hashedToken = hash('sha256', $token);
            $user = \App\Models\User::where('api_token', $hashedToken)->first();
            
            if ($user) {
                Auth::login($user);
            }
        }

        return $next($request);
    }
}
