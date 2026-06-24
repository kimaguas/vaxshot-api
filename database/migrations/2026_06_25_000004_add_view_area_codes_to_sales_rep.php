<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'view_area_codes', 'guard_name' => 'web']);

        $salesRep = Role::where('name', 'sales_rep')->where('guard_name', 'web')->first();
        if ($salesRep && !$salesRep->hasPermissionTo($perm)) {
            $salesRep->givePermissionTo($perm);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $salesRep = Role::where('name', 'sales_rep')->where('guard_name', 'web')->first();
        if ($salesRep) {
            $salesRep->revokePermissionTo('view_area_codes');
        }
    }
};
