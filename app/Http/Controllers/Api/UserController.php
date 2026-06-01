<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use LogsActivity;

    // Get all user names for dropdowns (no pagination)
    public function list()
    {
        $users = User::orderBy('name')->get(['id', 'name']);
        return response()->json(['users' => $users], 200);
    }

    // Get all users
    public function index(Request $request)
    {
        $query = User::with('roles');

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('username', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        $users = $query->latest()->paginate(10);

        return response()->json([
            'users'      => UserResource::collection($users),
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
            'username' => $request->username,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        $this->logActivity(
            action      : 'CREATE',
            module      : 'Users',
            description : "Created user: {$user->name} (@{$user->username}) with role: {$request->role}",
            newData     : ['name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'role' => $request->role]
        );

        return response()->json([
            'message' => 'User created successfully',
            'user'    => new UserResource($user)
        ], 201);
    }

    // Update user
    public function update(UpdateUserRequest $request, User $user)
    {
        $oldData = ['name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'role' => $user->getRoleNames()->first()];

        $user->update([
            'name'     => $request->name     ?? $user->name,
            'username' => $request->username ?? $user->username,
            'email'    => $request->email    ?? $user->email,
            'password' => $request->password
                            ? Hash::make($request->password)
                            : $user->password,
        ]);

        if ($request->role) {
            $user->syncRoles($request->role);
        }

        $this->logActivity(
            action      : 'UPDATE',
            module      : 'Users',
            description : "Updated user: {$user->name} (@{$user->username})",
            oldData     : $oldData,
            newData     : ['name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'role' => $request->role ?? $oldData['role']]
        );

        return response()->json([
            'message' => 'User updated successfully',
            'user'    => new UserResource($user)
        ], 200);
    }

    // Delete user
    public function destroy(User $user)
    {
        $this->logActivity(
            action      : 'DELETE',
            module      : 'Users',
            description : "Deleted user: {$user->name} (@{$user->username})",
            oldData     : ['name' => $user->name, 'username' => $user->username, 'email' => $user->email]
        );

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ], 200);
    }
}