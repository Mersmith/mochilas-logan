<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Direccion;
use App\Models\ListaPrecio;
use App\Models\Pais;
use App\Models\Ubigeo;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea clientes demo con direcciones reales de Lima, Perú.
 * Depende de: UbigeoPeruSeeder, ListaPrecioSeeder, RolesAndPermissionsSeeder.
 */
class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        $peru = Pais::where('codigo_iso2', 'PE')->firstOrFail();

        $listaMenor = ListaPrecio::where('nombre', 'Precio Menor')->firstOrFail();
        $listaMayor = ListaPrecio::where('nombre', 'Precio Mayor')->firstOrFail();

        // ── Ubicaciones de Lima usadas en las direcciones ──────────────────────
        // Región Lima (nivel 1)
        $regionLima = Ubigeo::where('pais_id', $peru->id)
            ->where('nivel', 1)
            ->where('nombre', 'LIMA')
            ->firstOrFail();

        // Provincia Lima (nivel 2, hija de Región Lima)
        $provinciaLima = Ubigeo::where('parent_id', $regionLima->id)
            ->where('nombre', 'LIMA')
            ->firstOrFail();

        // Distritos de Lima (nivel 3, hijos de Provincia Lima)
        $distritos = Ubigeo::where('parent_id', $provinciaLima->id)
            ->whereIn('nombre', ['MIRAFLORES', 'SAN ISIDRO', 'SURCO', 'LA VICTORIA', 'SAN JUAN DE LURIGANCHO', 'LOS OLIVOS', 'BREÑA', 'LINCE', 'BARRANCO', 'SURQUILLO'])
            ->get()
            ->keyBy('nombre');

        // ── Datos de clientes demo ─────────────────────────────────────────────
        $clientesData = [
            [
                'user' => ['name' => 'María García', 'email' => 'maria.garcia@example.com'],
                'cliente' => ['tipo_persona' => 'natural', 'tipo_cliente' => 'minorista', 'dni' => '45123456', 'lista_precio_id' => $listaMenor->id],
                'direcciones' => [
                    ['alias' => 'Casa', 'direccion' => 'Jr. Las Flores 123', 'distrito' => 'MIRAFLORES', 'es_predeterminada' => true],
                    ['alias' => 'Trabajo', 'direccion' => 'Av. Petit Thouars 456', 'distrito' => 'SAN ISIDRO', 'es_predeterminada' => false],
                ],
            ],
            [
                'user' => ['name' => 'Carlos Quispe', 'email' => 'carlos.quispe@example.com'],
                'cliente' => ['tipo_persona' => 'natural', 'tipo_cliente' => 'minorista', 'dni' => '47896532', 'lista_precio_id' => $listaMenor->id],
                'direcciones' => [
                    ['alias' => 'Casa', 'direccion' => 'Calle Los Pinos 789', 'distrito' => 'SAN JUAN DE LURIGANCHO', 'es_predeterminada' => true],
                ],
            ],
            [
                'user' => ['name' => 'Ana Torres', 'email' => 'ana.torres@example.com'],
                'cliente' => ['tipo_persona' => 'natural', 'tipo_cliente' => 'emprendedor', 'dni' => '43215678', 'ruc' => '10432156781', 'razon_social' => 'Torres Accesorios', 'lista_precio_id' => $listaMayor->id],
                'direcciones' => [
                    ['alias' => 'Tienda', 'direccion' => 'Av. Grau 1200', 'distrito' => 'LA VICTORIA', 'es_predeterminada' => true],
                    ['alias' => 'Casa', 'direccion' => 'Jr. Huáscar 345', 'distrito' => 'BREÑA', 'es_predeterminada' => false],
                ],
            ],
            [
                'user' => ['name' => 'Distribuidora Norte SAC', 'email' => 'ventas@distribuidoranorte.com'],
                'cliente' => ['tipo_persona' => 'juridica', 'tipo_cliente' => 'mayorista', 'ruc' => '20512345678', 'razon_social' => 'Distribuidora Norte SAC', 'telefono' => '014567890', 'lista_precio_id' => $listaMayor->id],
                'direcciones' => [
                    ['alias' => 'Almacén Principal', 'direccion' => 'Av. Industrial 555', 'distrito' => 'LOS OLIVOS', 'es_predeterminada' => true],
                ],
            ],
            [
                'user' => ['name' => 'Lucía Mendoza', 'email' => 'lucia.mendoza@example.com'],
                'cliente' => ['tipo_persona' => 'natural', 'tipo_cliente' => 'minorista', 'dni' => '46789012', 'lista_precio_id' => $listaMenor->id],
                'direcciones' => [
                    ['alias' => 'Casa', 'direccion' => 'Calle Bolognesi 88', 'distrito' => 'BARRANCO', 'es_predeterminada' => true],
                ],
            ],
            [
                'user' => ['name' => 'Inversiones Lima EIRL', 'email' => 'pedidos@inversioneslima.pe'],
                'cliente' => ['tipo_persona' => 'juridica', 'tipo_cliente' => 'mayorista', 'ruc' => '20601234567', 'razon_social' => 'Inversiones Lima EIRL', 'telefono' => '016789012', 'lista_precio_id' => $listaMayor->id],
                'direcciones' => [
                    ['alias' => 'Oficina', 'direccion' => 'Av. Arequipa 2030', 'distrito' => 'LINCE', 'es_predeterminada' => true],
                    ['alias' => 'Depósito', 'direccion' => 'Av. Colonial 1500', 'distrito' => 'BREÑA', 'es_predeterminada' => false],
                ],
            ],
            [
                'user' => ['name' => 'Roberto Salas', 'email' => 'roberto.salas@example.com'],
                'cliente' => ['tipo_persona' => 'natural', 'tipo_cliente' => 'emprendedor', 'dni' => '41234567', 'ruc' => '10412345671', 'razon_social' => 'Salas Mochilas', 'lista_precio_id' => $listaMayor->id],
                'direcciones' => [
                    ['alias' => 'Tienda', 'direccion' => 'Jr. Ica 300', 'distrito' => 'SURQUILLO', 'es_predeterminada' => true],
                ],
            ],
            [
                'user' => ['name' => 'Elena Vargas', 'email' => 'elena.vargas@example.com'],
                'cliente' => ['tipo_persona' => 'natural', 'tipo_cliente' => 'minorista', 'dni' => '48901234', 'lista_precio_id' => $listaMenor->id],
                'direcciones' => [
                    ['alias' => 'Casa', 'direccion' => 'Calle Manuel Bonilla 12', 'distrito' => 'SURCO', 'es_predeterminada' => true],
                ],
            ],
        ];

        foreach ($clientesData as $data) {
            // Crear usuario con rol 'cliente'
            $user = User::firstOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name' => $data['user']['name'],
                    'password' => bcrypt('password'),
                    'activo' => true,
                    'email_verified_at' => now(),
                ]
            );
            $user->assignRole('cliente');

            // Crear perfil de cliente
            $cliente = Cliente::firstOrCreate(
                ['user_id' => $user->id],
                array_merge($data['cliente'], ['activo' => true])
            );

            // Crear direcciones
            foreach ($data['direcciones'] as $dirData) {
                $nombreDistrito = $dirData['distrito'];
                $distrito = $distritos->get($nombreDistrito);

                Direccion::firstOrCreate(
                    ['cliente_id' => $cliente->id, 'alias' => $dirData['alias']],
                    [
                        'pais_id' => $peru->id,
                        'departamento_id' => $regionLima->id,
                        'provincia_id' => $provinciaLima->id,
                        'distrito_id' => $distrito?->id,
                        'destinatario' => $user->name,
                        'telefono_contacto' => $data['cliente']['telefono'] ?? null,
                        'direccion' => $dirData['direccion'],
                        'referencia' => null,
                        'es_predeterminada' => $dirData['es_predeterminada'],
                        'activo' => true,
                    ]
                );
            }
        }

        $this->command->info('✅ '.count($clientesData).' clientes demo creados con direcciones en Lima.');
    }
}
