<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Super Admin (oculto, control total) ─────────────────────
        // Este usuario NO aparece en el panel de Gestión de Usuarios.
        // Bypasea todos los permisos vía Gate::before() en AppServiceProvider.
        // Cambiar las credenciales antes de producción.
        User::firstOrCreate(
            ['email' => 'root@logan.com'],
            [
                'name' => 'Root',
                'password' => bcrypt('Sup3rS3cr3t!'),
                'activo' => true,
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // ── Admin general del panel ──────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@logan.com'],
            [
                'name' => 'Administrador Logan',
                'password' => bcrypt('password'),
                'activo' => true,
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');

        // ── Generar 20 administradores aleatorios ─────────────────────
        $users = User::factory()->count(20)->create([
            'password' => bcrypt('password'),
            'activo' => true,
            'is_super_admin' => false,
            'role' => 'admin',
        ]);

        foreach ($users as $user) {
            $user->assignRole('admin');
        }

        // ── Cliente específico para pruebas ──────────────────────────
        $cliente = User::firstOrCreate(
            ['email' => 'cliente@logan.com'],
            [
                'name' => 'Cliente Logan',
                'password' => bcrypt('password'),
                'activo' => true,
                'role' => 'cliente',
                'email_verified_at' => now(),
            ]
        );
        $cliente->assignRole('cliente');

        // ── Generar 10 clientes aleatorios ─────────────────────
        $customers = User::factory()->count(10)->create([
            'password' => bcrypt('password'),
            'activo' => true,
            'is_super_admin' => false,
            'role' => 'cliente',
        ]);

        foreach ($customers as $customer) {
            $customer->assignRole('cliente');
        }
    }
}
