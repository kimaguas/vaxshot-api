<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'sanctum';

        $viewDeliveries   = Permission::firstOrCreate(['name' => 'view_deliveries',   'guard_name' => $guard]);
        $manageDeliveries = Permission::firstOrCreate(['name' => 'manage_deliveries', 'guard_name' => $guard]);

        $admin   = Role::where('name', 'admin')->where('guard_name', $guard)->first();
        $manager = Role::where('name', 'manager')->where('guard_name', $guard)->first();
        $staff   = Role::where('name', 'staff')->where('guard_name', $guard)->first();

        if ($admin)   $admin->givePermissionTo([$viewDeliveries, $manageDeliveries]);
        if ($manager) $manager->givePermissionTo([$viewDeliveries, $manageDeliveries]);
        if ($staff)   $staff->givePermissionTo([$viewDeliveries]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('guard_name', 'sanctum')
            ->whereIn('name', ['view_deliveries', 'manage_deliveries'])
            ->delete();
    }
};
