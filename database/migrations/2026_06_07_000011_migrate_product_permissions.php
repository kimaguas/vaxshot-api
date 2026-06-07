<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Resolve permission IDs
        $viewOldId      = DB::table('permissions')->where('name', 'view_supplier_pricing')->value('id');
        $manageOldId    = DB::table('permissions')->where('name', 'manage_supplier_pricing')->value('id');
        $viewProductsId = DB::table('permissions')->where('name', 'view_products')->value('id');

        // Create manage_products if it doesn't exist
        $manageProductsId = DB::table('permissions')->where('name', 'manage_products')->value('id');
        if (!$manageProductsId) {
            $manageProductsId = DB::table('permissions')->insertGetId([
                'name'       => 'manage_products',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Transfer role_has_permissions: view_supplier_pricing → view_products
        if ($viewOldId && $viewProductsId) {
            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $viewOldId)
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                $exists = DB::table('role_has_permissions')
                    ->where('permission_id', $viewProductsId)
                    ->where('role_id', $roleId)
                    ->exists();
                if (!$exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $viewProductsId,
                        'role_id'       => $roleId,
                    ]);
                }
            }
        }

        // Transfer role_has_permissions: manage_supplier_pricing → manage_products
        if ($manageOldId) {
            $roleIds = DB::table('role_has_permissions')
                ->where('permission_id', $manageOldId)
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                $exists = DB::table('role_has_permissions')
                    ->where('permission_id', $manageProductsId)
                    ->where('role_id', $roleId)
                    ->exists();
                if (!$exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $manageProductsId,
                        'role_id'       => $roleId,
                    ]);
                }
            }
        }

        // Transfer model_has_permissions (direct user permissions): view_supplier_pricing → view_products
        if ($viewOldId && $viewProductsId) {
            $users = DB::table('model_has_permissions')
                ->where('permission_id', $viewOldId)
                ->get(['model_type', 'model_id']);

            foreach ($users as $u) {
                $exists = DB::table('model_has_permissions')
                    ->where('permission_id', $viewProductsId)
                    ->where('model_type', $u->model_type)
                    ->where('model_id', $u->model_id)
                    ->exists();
                if (!$exists) {
                    DB::table('model_has_permissions')->insert([
                        'permission_id' => $viewProductsId,
                        'model_type'    => $u->model_type,
                        'model_id'      => $u->model_id,
                    ]);
                }
            }
        }

        // Transfer model_has_permissions: manage_supplier_pricing → manage_products
        if ($manageOldId) {
            $users = DB::table('model_has_permissions')
                ->where('permission_id', $manageOldId)
                ->get(['model_type', 'model_id']);

            foreach ($users as $u) {
                $exists = DB::table('model_has_permissions')
                    ->where('permission_id', $manageProductsId)
                    ->where('model_type', $u->model_type)
                    ->where('model_id', $u->model_id)
                    ->exists();
                if (!$exists) {
                    DB::table('model_has_permissions')->insert([
                        'permission_id' => $manageProductsId,
                        'model_type'    => $u->model_type,
                        'model_id'      => $u->model_id,
                    ]);
                }
            }
        }

        // Delete old permissions (cascades to role_has_permissions and model_has_permissions)
        DB::table('permissions')->whereIn('name', [
            'view_supplier_pricing',
            'manage_supplier_pricing',
            'create_products',
            'edit_products',
            'delete_products',
        ])->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Not reversible
    }
};
