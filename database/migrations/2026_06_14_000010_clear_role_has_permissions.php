<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Roles are now display labels only — all permission enforcement uses
        // direct user permissions (model_has_permissions) set via syncPermissions().
        // Clearing role_has_permissions ensures that revoking a permission from a
        // user via the edit form actually prevents access (no role-grant fallback).
        DB::table('role_has_permissions')->truncate();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally no rollback — direct user permissions remain the source of truth.
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
