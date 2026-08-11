<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            // La columna role tomará el valor por defecto 'client' en la tabla users
        ]);

        // 1. Asignar rol con Spatie Permissions
        $user->assignRole('cliente');

        // 2. Obtener o crear una lista de precios por defecto para evitar error de Foreign Key
        $listaPrecio = \App\Models\ListaPrecio::firstOrCreate(['nombre' => 'Precio Menor']);

        // 3. Crear su perfil en la tabla clientes
        $user->cliente()->create([
            'tipo_persona' => 'natural',
            'tipo_cliente' => 'minorista',
            'lista_precio_id' => $listaPrecio->id,
        ]);

        return $user;
    }
}
