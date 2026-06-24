<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $salesRepPerms = [
            'view_dashboard',
            'view_products',
            'view_customers', 'create_customers', 'edit_customers',
            'view_sales', 'create_sales', 'edit_sales', 'confirm_sales',
            'view_quotations', 'create_quotations', 'edit_quotations', 'send_quotations',
            'view_reports',
        ];

        $salesRep = Role::firstOrCreate(['name' => 'sales_rep', 'guard_name' => 'web']);

        $perms = collect($salesRepPerms)->map(fn ($p) =>
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web'])
        );

        $salesRep->syncPermissions($perms);
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::where('name', 'sales_rep')->where('guard_name', 'web')->first();
        if ($role) {
            $role->delete();
        }
    }
};
