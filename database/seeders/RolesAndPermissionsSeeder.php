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
            'charging_stations.remote_control',
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

            // Module 5 — Charging Sessions
            'charging_sessions.view',
            'charging_sessions.create',
            'charging_sessions.start',
            'charging_sessions.pause',
            'charging_sessions.end',
            'charging_sessions.cancel',

            // Module 7 — RFID Badge Management
            'rfid_badges.view',
            'rfid_badges.create',
            'rfid_badges.update',
            'rfid_badges.reassign',
            'rfid_badges.activate',
            'rfid_badges.block',
            'rfid_badges.expiration.update',
            'rfid_badges.history.view',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        $rolePermissions = [
            'Super Administrator' => [
                'users.view', 'users.create', 'users.update', 'users.disable',
                'roles.view', 'roles.manage',
                'charging_stations.view', 'charging_stations.create', 'charging_stations.update',
                'charging_stations.state.update', 'charging_stations.lifecycle.update',
                'charging_stations.decommission', 'charging_stations.assign', 'charging_stations.history.view',
                'charging_stations.remote_control',
                'organizations.view', 'organizations.create', 'organizations.update',
                'sites.view', 'sites.create', 'sites.update',
                'connectors.view', 'connectors.create', 'connectors.update', 'connectors.delete',
                'connectors.state.update', 'connectors.availability.update',
                'supervision.view',
                'charging_sessions.view', 'charging_sessions.create', 'charging_sessions.start',
                'charging_sessions.pause', 'charging_sessions.end', 'charging_sessions.cancel',
                'rfid_badges.view', 'rfid_badges.create', 'rfid_badges.update', 'rfid_badges.reassign',
                'rfid_badges.activate', 'rfid_badges.block', 'rfid_badges.expiration.update',
                'rfid_badges.history.view',
            ],
            'Exploitant' => [
                'users.view', 'users.create', 'users.update', 'users.disable',
                'roles.view',
                'charging_stations.view', 'charging_stations.create', 'charging_stations.update',
                'charging_stations.state.update', 'charging_stations.lifecycle.update',
                'charging_stations.decommission', 'charging_stations.assign', 'charging_stations.history.view',
                'charging_stations.remote_control',
                'organizations.view', 'organizations.create', 'organizations.update',
                'sites.view', 'sites.create', 'sites.update',
                'connectors.view', 'connectors.create', 'connectors.update', 'connectors.delete',
                'connectors.state.update', 'connectors.availability.update',
                'supervision.view',
                'charging_sessions.view', 'charging_sessions.create', 'charging_sessions.start',
                'charging_sessions.pause', 'charging_sessions.end', 'charging_sessions.cancel',
                'rfid_badges.view', 'rfid_badges.create', 'rfid_badges.update', 'rfid_badges.reassign',
                'rfid_badges.activate', 'rfid_badges.block', 'rfid_badges.expiration.update',
                'rfid_badges.history.view',
            ],
            'Opérateur' => [
                'charging_stations.view',
                'charging_stations.state.update',
                'charging_stations.history.view',
                'charging_stations.remote_control',
                'connectors.view',
                'connectors.state.update',
                'supervision.view',
                'charging_sessions.view', 'charging_sessions.create', 'charging_sessions.start',
                'charging_sessions.pause', 'charging_sessions.end', 'charging_sessions.cancel',
                'rfid_badges.view', 'rfid_badges.activate', 'rfid_badges.block',
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
                // Module 5 roadmap §16: Technicien is view-only on charging sessions —
                // no create/start/pause/end/cancel until a real scoping need is identified.
                'charging_sessions.view',
                // Module 7: Technicien is view-only on RFID badges — inspection for
                // troubleshooting only, no lifecycle/ownership authority.
                'rfid_badges.view',
            ],
            'Service Client' => [
                'users.view', 'users.update', 'users.disable',
                'charging_stations.view',
                'charging_stations.history.view',
                'connectors.view',
                'supervision.view',
                'charging_sessions.view',
                // Module 7: Service Client is the platform's support role for Client
                // accounts, so it gets full RFID lifecycle support — every badge owner
                // is required to be a Client (Decision A), so there is no separate
                // "staff badge" case to withhold access to here.
                'rfid_badges.view', 'rfid_badges.create', 'rfid_badges.update', 'rfid_badges.reassign',
                'rfid_badges.activate', 'rfid_badges.block', 'rfid_badges.expiration.update',
                'rfid_badges.history.view',
            ],
            'Finance' => [
                'charging_stations.view',
                'connectors.view',
                'supervision.view',
                'charging_sessions.view',
            ],
            'Client' => [
                'organizations.view', 'sites.view', 'charging_stations.view',
                'connectors.view', 'supervision.view', 'charging_sessions.view',
                // Module 7: Client sees their own badge state only, scoped in the
                // controller/service layer (Decision M) — same view-only permission
                // shape as charging_stations.view above, not a separate "own" permission.
                'rfid_badges.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::findByName($roleName);
            $role->givePermissionTo($permissionNames);
        }
    }
}
