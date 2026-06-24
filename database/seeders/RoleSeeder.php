<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_dashboard',
            'view_products', 'manage_products', 'view_acquisition_cost',
            'view_suppliers', 'create_suppliers', 'edit_suppliers', 'delete_suppliers',
            'view_customers', 'create_customers', 'edit_customers', 'delete_customers',
            'view_purchase_orders', 'create_purchase_orders', 'receive_purchase_orders', 'cancel_purchase_orders',
            'view_sales', 'create_sales', 'edit_sales', 'confirm_sales', 'cancel_sales',
            'view_quotations', 'create_quotations', 'edit_quotations', 'delete_quotations', 'send_quotations',
            'view_email_templates', 'create_email_templates', 'edit_email_templates', 'delete_email_templates',
            'view_reports',
            'view_area_codes', 'create_area_codes', 'edit_area_codes', 'delete_area_codes',
            'view_users', 'create_users', 'edit_users', 'delete_users',
            'view_activity_logs',
            'manage_settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminPerms = $permissions;

        $managerPerms = array_values(array_diff($permissions, [
            'view_users', 'create_users', 'edit_users', 'delete_users',
            'view_activity_logs',
        ]));

        $staffPerms = array_values(array_diff($managerPerms, [
            'delete_suppliers', 'delete_customers',
            'cancel_purchase_orders', 'cancel_sales',
            'delete_quotations',
            'delete_email_templates',
            'delete_area_codes',
            'view_acquisition_cost',
            'manage_products',
        ]));

        $viewerPerms = ['view_dashboard', 'view_reports'];

        $admin   = Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $staff   = Role::firstOrCreate(['name' => 'staff',   'guard_name' => 'web']);
        $viewer  = Role::firstOrCreate(['name' => 'viewer',  'guard_name' => 'web']);

        $admin->syncPermissions($adminPerms);
        $manager->syncPermissions($managerPerms);
        $staff->syncPermissions($staffPerms);
        $viewer->syncPermissions($viewerPerms);

        $user = User::firstOrCreate(
            ['email' => 'admin@vaxshot.com'],
            [
                'name'     => 'Super Admin',
                'username' => 'admin',
                'password' => Hash::make('admin123'),
            ]
        );

        $user->syncRoles('admin');
        $user->syncPermissions($adminPerms);
    }
}
