<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $viewId = DB::table('permissions')->insertGetId([
            'name'       => 'view_inventory',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $adjustId = DB::table('permissions')->insertGetId([
            'name'       => 'adjust_inventory',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Assign to roles: admin + manager + staff → view; admin + manager → adjust
        $adminId   = DB::table('roles')->where('name', 'admin')->value('id');
        $managerId = DB::table('roles')->where('name', 'manager')->value('id');
        $staffId   = DB::table('roles')->where('name', 'staff')->value('id');

        $inserts = [];
        foreach (array_filter([$adminId, $managerId, $staffId]) as $roleId) {
            $inserts[] = ['permission_id' => $viewId,   'role_id' => $roleId];
        }
        foreach (array_filter([$adminId, $managerId]) as $roleId) {
            $inserts[] = ['permission_id' => $adjustId, 'role_id' => $roleId];
        }

        if ($inserts) {
            DB::table('role_has_permissions')->insert($inserts);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', ['view_inventory', 'adjust_inventory'])->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
