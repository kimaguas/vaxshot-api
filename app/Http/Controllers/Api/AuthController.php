<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use LogsActivity;

    // Login
    public function login(LoginRequest $request)
    {
        $user = User::where('username', $request->login)
                    ->orWhere('email', $request->login)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Log login
        $this->logActivity(
            action      : 'LOGIN',
            module      : 'Auth',
            description : "{$user->name} logged in",
        );

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => new UserResource($user),
        ], 200);
    }

    // Logout
    public function logout(Request $request)
    {
        // Log logout before deleting token
        $this->logActivity(
            action      : 'LOGOUT',
            module      : 'Auth',
            description : "{$request->user()->name} logged out",
        );

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ], 200);
    }

    // Get current logged in user
    public function me(Request $request)
    {
        return response()->json([
            'user' => new UserResource($request->user())
        ], 200);
    }
}