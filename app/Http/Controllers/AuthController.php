<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class AuthController extends Controller
{
    use ApiResponse;
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|min:2',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|regex:/^[0-9+\-\s]*$/|max:20',
            'location' => 'nullable|string|max:255',
        ]);

        $user = \App\Models\User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'location' => $request->location,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        ]);

        $verificationUrl = url('/api/email/verify/' . $user->id . '/' . sha1($user->email));

        $token = \Illuminate\Support\Str::random(60);
        $user->forceFill(['api_token' => hash('sha256', $token)])->save();

        return $this->success([
            'user' => $user,
            'token' => $token
        ], 'User registered successfully', 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string|min:1',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            return $this->error('Invalid credentials', 401);
        }

        $token = \Illuminate\Support\Str::random(60);
        $user->forceFill(['api_token' => hash('sha256', $token)])->save();

        return $this->success([
            'user' => $user,
            'token' => $token
        ], 'Login successful');
    }

    public function logout(Request $request)
    {
        if ($user = \Illuminate\Support\Facades\Auth::user()) {
            $user->forceFill(['api_token' => null])->save();
        }

        return $this->success(null, 'Logged out successfully');
    }

    public function user(Request $request)
    {
        return $this->success($request->user());
    }

    public function googleLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $response = \Illuminate\Support\Facades\Http::get('https://oauth2.googleapis.com/tokeninfo?id_token=' . $request->id_token);
        
        if ($response->failed() || !isset($response['email'])) {
            return $this->error('Invalid or expired Google OAuth token', 401);
        }

        $email = $response['email'];
        $name = $response['name'] ?? 'Google User';
        $googleId = $response['sub'] ?? null;

        if (!$email || !$googleId) {
            return $this->error('Invalid Google token payload', 401);
        }

        $user = \App\Models\User::where('email', $email)->first();

        if (!$user) {
            $user = \App\Models\User::create([
                'name' => $name,
                'email' => $email,
                'google_id' => $googleId,
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(24)),
            ]);
        }

        $token = \Illuminate\Support\Str::random(60);
        $user->forceFill(['api_token' => hash('sha256', $token)])->save();

        return $this->success([
            'user' => $user,
            'token' => $token
        ], 'Google login successful');
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = \App\Models\User::findOrFail($id);
        
        if (!hash_equals(sha1($user->email), $hash)) {
            return $this->error('Invalid verification link', 400);
        }

        if ($user->email_verified_at) {
            return $this->error('Email already verified', 400);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return $this->success(null, 'Email verified successfully');
    }

    public function resendVerificationEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        
        $user = \App\Models\User::where('email', $request->email)->first();
        
        if (!$user) {
            return $this->error('User not found', 404);
        }

        if ($user->email_verified_at) {
            return $this->error('Email already verified', 400);
        }

        return $this->success(null, 'Verification email resent');
    }

}
