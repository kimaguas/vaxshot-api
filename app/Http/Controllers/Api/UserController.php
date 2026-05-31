<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // Get all users
    public function index()
    {
        $users = User::with('roles')->latest()->paginate(10);

        return response()->json([
            'users' => UserResource::collection($users),
            'pagination' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
                'from'         => $users->firstItem(),
                'to'           => $users->lastItem(),
            ]
        ], 200);
    }

    // Get single user
    public function show(User $user)
    {
        return response()->json([
            'user' => new UserResource($user)
        ], 200);
    }

    // Create new user
    public function store(StoreUserRequest $request)
    {
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        return response()->json([
            'message' => 'User created successfully',
            'user'    => new UserResource($user)
        ], 201);
    }

    // Update user
    public function update(UpdateUserRequest $request, User $user)
    {
        $user->update([
            'name'     => $request->name ?? $user->name,
            'email'    => $request->email ?? $user->email,
            'password' => $request->password
                            ? Hash::make($request->password)
                            : $user->password,
        ]);

        if ($request->role) {
            $user->syncRoles($request->role);
        }

        return response()->json([
            'message' => 'User updated successfully',
            'user'    => new UserResource($user)
        ], 200);
    }

    // Delete user
    public function destroy(User $user)
    {
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ], 200);
    }
}