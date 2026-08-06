<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear Usuario Admin por defecto
        $admin = User::firstOrCreate(
            ['email' => 'admin@logan.com'],
            [
                'name' => 'Administrador Logan',
                'password' => bcrypt('password'),
            ]
        );
        $admin->assignRole('admin');
    }
}
