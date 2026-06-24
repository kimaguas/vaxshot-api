<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::firstOrCreate([
            'name'       => 'manage_settings',
            'guard_name' => 'web',
        ]);

        // Grant to admin role
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole && !$adminRole->hasPermissionTo($perm)) {
            $adminRole->givePermissionTo($perm);
        }

        // Grant directly to the default admin user
        $adminUser = User::where('email', 'admin@vaxshot.com')->first();
        if ($adminUser && !$adminUser->hasPermissionTo($perm)) {
            $adminUser->givePermissionTo($perm);
        }
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perm = Permission::where('name', 'manage_settings')->where('guard_name', 'web')->first();
        if ($perm) {
            $perm->delete();
        }
    }
};
