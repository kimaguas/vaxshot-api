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
        // Create roles
        $admin   = Role::create(['name' => 'admin']);
        $manager = Role::create(['name' => 'manager']);
        $staff   = Role::create(['name' => 'staff']);
        $viewer  = Role::create(['name' => 'viewer']);

        // Create default admin user
        $user = User::create([
            'name'     => 'Super Admin',
            'email'    => 'admin@vaxshot.com',
            'password' => Hash::make('admin123'),
        ]);

        // Assign admin role to default user
        $user->assignRole('admin');
    }
}