<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpiar caché de permisos antes de comenzar
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // =============================================
        // 1. DEFINIR TODOS LOS PERMISOS GRANULARES
        // =============================================
        $permissions = [
            // Panel administrativo general
            'panel.acceder',

            // Dashboard financiero
            'dashboard.ver',

            // Catálogo de Productos
            'productos.ver',
            'productos.crear',
            'productos.editar',

            // Guías de Inventario (entradas, salidas, transferencias)
            'guias.ver',
            'guias.crear',

            // Kardex Valorizado
            'kardex.ver',

            // Ventas / POS
            'ventas.ver',
            'ventas.crear',

            // Promociones y Cupones
            'promociones.ver',
            'promociones.crear',

            // Mantenimiento Base
            'mantenimiento.ver',
            'mantenimiento.editar',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // =============================================
        // 2. CREAR ROLES Y ASIGNAR PERMISOS
        // =============================================

        // ROL: admin → Acceso total
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        // ROL: supervisor → Dashboard, Productos (ver/crear/editar), Guías, Kardex, Ventas, Promociones
        $supervisor = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $supervisor->syncPermissions([
            'panel.acceder',
            'dashboard.ver',
            'productos.ver',
            'productos.crear',
            'productos.editar',
            'guias.ver',
            'guias.crear',
            'kardex.ver',
            'ventas.ver',
            'ventas.crear',
            'promociones.ver',
            'promociones.crear',
        ]);

        // ROL: vendedor → Solo POS de ventas y ver productos
        $vendedor = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $vendedor->syncPermissions([
            'panel.acceder',
            'productos.ver',
            'ventas.ver',
            'ventas.crear',
        ]);

        // ROL: almacen → Gestión física: Guías + Kardex
        $almacen = Role::firstOrCreate(['name' => 'almacen', 'guard_name' => 'web']);
        $almacen->syncPermissions([
            'panel.acceder',
            'productos.ver',
            'guias.ver',
            'guias.crear',
            'kardex.ver',
        ]);

        // ROL: logistica → Guías (despacho y transferencias), ver productos
        $logistica = Role::firstOrCreate(['name' => 'logistica', 'guard_name' => 'web']);
        $logistica->syncPermissions([
            'panel.acceder',
            'productos.ver',
            'guias.ver',
            'guias.crear',
        ]);

        // ROL: cliente → Solo tienda pública (no accede al /admin)
        Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        // Sin permisos de panel — accede solo al e-commerce

        // =============================================
        // 3. ASIGNAR ROL ADMIN AL USUARIO EXISTENTE (ID 1)
        // =============================================
        $adminUser = User::find(1);
        if ($adminUser && ! $adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }

        $this->command->info('✅ Roles y permisos creados correctamente.');
        $this->command->table(
            ['Rol', 'Permisos'],
            Role::with('permissions')->get()->map(fn ($r) => [
                $r->name,
                $r->permissions->pluck('name')->implode(', ') ?: '(sin permisos de panel)',
            ])->toArray()
        );
    }
}
