<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Only insert if not already present under web guard
        if (!DB::table('permissions')->where('name', 'view_deliveries')->where('guard_name', 'web')->exists()) {
            $viewId = DB::table('permissions')->insertGetId([
                'name'       => 'view_deliveries',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $viewId = DB::table('permissions')->where('name', 'view_deliveries')->where('guard_name', 'web')->value('id');
        }

        if (!DB::table('permissions')->where('name', 'manage_deliveries')->where('guard_name', 'web')->exists()) {
            $manageId = DB::table('permissions')->insertGetId([
                'name'       => 'manage_deliveries',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $manageId = DB::table('permissions')->where('name', 'manage_deliveries')->where('guard_name', 'web')->value('id');
        }

        $adminId   = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');
        $managerId = DB::table('roles')->where('name', 'manager')->where('guard_name', 'web')->value('id');
        $staffId   = DB::table('roles')->where('name', 'staff')->where('guard_name', 'web')->value('id');

        $inserts = [];
        foreach (array_filter([$adminId, $managerId, $staffId]) as $roleId) {
            if (!DB::table('role_has_permissions')->where('permission_id', $viewId)->where('role_id', $roleId)->exists()) {
                $inserts[] = ['permission_id' => $viewId, 'role_id' => $roleId];
            }
        }
        foreach (array_filter([$adminId, $managerId]) as $roleId) {
            if (!DB::table('role_has_permissions')->where('permission_id', $manageId)->where('role_id', $roleId)->exists()) {
                $inserts[] = ['permission_id' => $manageId, 'role_id' => $roleId];
            }
        }

        if ($inserts) {
            DB::table('role_has_permissions')->insert($inserts);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', ['view_deliveries', 'manage_deliveries'])
            ->where('guard_name', 'web')
            ->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
