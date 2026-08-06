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
            ]
        );

        // ── Admin general del panel ──────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@logan.com'],
            [
                'name' => 'Administrador Logan',
                'password' => bcrypt('password'),
                'activo' => true,
            ]
        );
        $admin->assignRole('admin');

        // ── Generar 20 administradores aleatorios ─────────────────────
        $users = User::factory()->count(20)->create([
            'password' => bcrypt('password'),
            'activo' => true,
            'is_super_admin' => false,
        ]);

        foreach ($users as $user) {
            $user->assignRole('admin');
        }
    }
}
