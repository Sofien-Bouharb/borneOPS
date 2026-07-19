<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
 public function run(): void
{
    $roles = [
        'Super Administrator',
        'Exploitant',
        'Opérateur',
        'Technicien',
        'Service Client',
        'Finance',
        'Client',
    ];

    foreach ($roles as $roleName) {
        Role::firstOrCreate(['name' => $roleName]);
    }

    $permissions = [
    'users.view',
    'users.create',
    'users.update',
    'users.disable',
    'roles.view',
    'roles.manage',
    'sessions.view-own',
    'sessions.revoke-own',
    'sessions.revoke-any',
    'audit.view',
    'settings.manage',
];

foreach ($permissions as $permissionName) {
    Permission::firstOrCreate(['name' => $permissionName]);
}

}
}
