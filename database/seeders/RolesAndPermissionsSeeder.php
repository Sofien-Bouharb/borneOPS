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

            // Module 2 — Charging Station Management
            'charging_stations.view',
            'charging_stations.create',
            'charging_stations.update',
            'charging_stations.state.update',
            'charging_stations.lifecycle.update',
            'charging_stations.decommission',
            'charging_stations.assign',
            'charging_stations.history.view',
            'organizations.view',
            'organizations.create',
            'organizations.update',
            'sites.view',
            'sites.create',
            'sites.update',

            // Module 3 — Connector Management
            'connectors.view',
            'connectors.create',
            'connectors.update',
            'connectors.delete',
            'connectors.state.update',
            'connectors.availability.update',

            // Module 4 — Real-Time Supervision
            'supervision.view',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        $rolePermissions = [
            'Super Administrator' => [
                'charging_stations.view', 'charging_stations.create', 'charging_stations.update',
                'charging_stations.state.update', 'charging_stations.lifecycle.update',
                'charging_stations.decommission', 'charging_stations.assign', 'charging_stations.history.view',
                'organizations.view', 'organizations.create', 'organizations.update',
                'sites.view', 'sites.create', 'sites.update',
                'connectors.view', 'connectors.create', 'connectors.update', 'connectors.delete',
                'connectors.state.update', 'connectors.availability.update',
                'supervision.view',
            ],
            'Exploitant' => [
                'charging_stations.view', 'charging_stations.create', 'charging_stations.update',
                'charging_stations.state.update', 'charging_stations.lifecycle.update',
                'charging_stations.decommission', 'charging_stations.assign', 'charging_stations.history.view',
                'organizations.view', 'organizations.create', 'organizations.update',
                'sites.view', 'sites.create', 'sites.update',
                'connectors.view', 'connectors.create', 'connectors.update', 'connectors.delete',
                'connectors.state.update', 'connectors.availability.update',
                'supervision.view',
            ],
            'Opérateur' => [
                'charging_stations.view',
                'charging_stations.state.update',
                'charging_stations.history.view',
                'connectors.view',
                'connectors.state.update',
                'supervision.view',
            ],
            'Technicien' => [
                // charging_stations.state.update deliberately withheld — roadmap marks it "possibly, scoped"
                // and no assigned-technician relationship exists yet to scope it against.
                // connectors.state.update IS granted here per the Module 3 permission matrix (§18) —
                // it's a separate, more granular permission than the station-level one above.
                'charging_stations.view',
                'charging_stations.history.view',
                'connectors.view',
                'connectors.state.update',
                'supervision.view',
            ],
            'Service Client' => [
                'charging_stations.view',
                'charging_stations.history.view',
                'connectors.view',
                'supervision.view',
            ],
            'Finance' => [
                'charging_stations.view',
                'connectors.view',
                'supervision.view',
            ],
            'Client' => [
                // No access — deferred until organization_user scoping exists (Module 6).
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::findByName($roleName);
            $role->givePermissionTo($permissionNames);
        }
    }
}
