<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles y permisos (§2 Usuarios, roles y permisos).
 * 4 roles: administrador, capturista, vendedor, supervisor.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        $permissions = [
            // Usuarios y configuración
            'users.manage',        // alta/edición/activar/desactivar (§3.1)
            'catalogs.manage',     // catálogos (§3.2)
            // Leads
            'leads.capture',       // captura (§3.3)
            'leads.view.all',      // bandeja general (§4.1) - admin/supervisor
            'leads.view.assigned', // panel del vendedor (§4.2)
            'leads.assign',        // asignar/reasignar (§4.1)
            'leads.export',        // exportación CSV/Excel (§9)
            // Actividad
            'activities.manage',   // registrar seguimiento (§6)
            // Reportes
            'reports.view',        // panel administrativo (§8)
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $roles = [
            // Acceso completo (§2)
            'administrador' => $permissions,
            // Registra y clasifica nuevos leads; sin configuración administrativa (§2)
            'capturista' => ['leads.capture', 'leads.view.assigned'],
            // Únicamente los leads asignados (§2, §4.2)
            'vendedor' => ['leads.view.assigned', 'activities.manage'],
            // Consulta leads, vendedores, tiempos y reportes; sin configuración crítica (§2)
            'supervisor' => ['leads.view.all', 'reports.view'],
        ];

        foreach ($roles as $role => $perms) {
            $r = Role::firstOrCreate(['name' => $role]);
            $r->syncPermissions($perms);
        }
    }
}
