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

    public function list()
    {
        $users = User::orderBy('name')->get(['id', 'name']);
        return response()->json(['users' => $users], 200);
    }

    public function index(Request $request)
    {
        $query = User::with('roles', 'permissions', 'areaCode');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
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
            ],
        ], 200);
    }

    public function show(User $user)
    {
        return response()->json([
            'user' => new UserResource($user),
        ], 200);
    }

    public function store(StoreUserRequest $request)
    {
        $user = User::create([
            'name'         => $request->name,
            'username'     => $request->username,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'area_code_id' => $request->area_code_id ?? null,
        ]);

        $user->assignRole($request->role);

        // Seed direct permissions: use provided list or fall back to the role's preset
        $permissions = $request->permissions ?? $user->getPermissionsViaRoles()->pluck('name')->toArray();
        $user->syncPermissions($permissions);

        $this->logActivity(
            action:      'CREATE',
            module:      'Users',
            description: "Created user: {$user->name} (@{$user->username}) with role: {$request->role}",
            newData:     ['name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'role' => $request->role]
        );

        return response()->json([
            'message' => 'User created successfully',
            'user'    => new UserResource($user),
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $oldData = [
            'name'     => $user->name,
            'username' => $user->username,
            'email'    => $user->email,
            'role'     => $user->getRoleNames()->first(),
        ];

        $user->update([
            'name'         => $request->name         ?? $user->name,
            'username'     => $request->username     ?? $user->username,
            'email'        => $request->email        ?? $user->email,
            'password'     => $request->password
                                ? Hash::make($request->password)
                                : $user->password,
            'area_code_id' => $request->has('area_code_id')
                                ? $request->area_code_id
                                : $user->area_code_id,
        ]);

        if ($request->role) {
            $user->syncRoles($request->role);
        }

        if ($request->has('permissions')) {
            $user->syncPermissions($request->permissions);
        }

        $this->logActivity(
            action:      'UPDATE',
            module:      'Users',
            description: "Updated user: {$user->name} (@{$user->username})",
            oldData:     $oldData,
            newData:     ['name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'role' => $request->role ?? $oldData['role']]
        );

        return response()->json([
            'message' => 'User updated successfully',
            'user'    => new UserResource($user),
        ], 200);
    }

    public function destroy(User $user)
    {
        $this->logActivity(
            action:      'DELETE',
            module:      'Users',
            description: "Deleted user: {$user->name} (@{$user->username})",
            oldData:     ['name' => $user->name, 'username' => $user->username, 'email' => $user->email]
        );

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ], 200);
    }

    public function getPermissions(User $user)
    {
        return response()->json([
            'permissions' => $user->getDirectPermissions()->pluck('name')->values(),
        ], 200);
    }

    public function updatePermissions(Request $request, User $user)
    {
        $request->validate([
            'permissions'   => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $oldPermissions = $user->getDirectPermissions()->pluck('name')->toArray();

        $user->syncPermissions($request->permissions);

        $this->logActivity(
            action:      'UPDATE',
            module:      'Users',
            description: "Updated permissions for user: {$user->name} (@{$user->username})",
            oldData:     ['permissions' => $oldPermissions],
            newData:     ['permissions' => $request->permissions]
        );

        return response()->json([
            'message'     => 'Permissions updated successfully',
            'permissions' => $user->getDirectPermissions()->pluck('name')->values(),
        ], 200);
    }
}
