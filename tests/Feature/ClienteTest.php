<?php

use App\Models\Cliente;
use App\Models\Direccion;
use App\Models\ListaPrecio;
use App\Models\Pais;
use App\Models\Ubigeo;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('un cliente se puede crear con su perfil', function () {
    $listaPrecio = ListaPrecio::factory()->create();
    $user = User::factory()->create();

    $cliente = Cliente::create([
        'user_id' => $user->id,
        'tipo_persona' => 'natural',
        'tipo_cliente' => 'minorista',
        'lista_precio_id' => $listaPrecio->id,
        'telefono' => '987654321',
        'activo' => true,
    ]);

    expect($cliente)->toBeInstanceOf(Cliente::class)
        ->and($cliente->tipo_persona)->toBe('natural')
        ->and($cliente->tipo_cliente)->toBe('minorista')
        ->and($cliente->activo)->toBeTrue();
});

test('un cliente pertenece a un user', function () {
    $listaPrecio = ListaPrecio::factory()->create();
    $cliente = Cliente::factory()->create(['lista_precio_id' => $listaPrecio->id]);

    expect($cliente->user)->toBeInstanceOf(User::class);
});

test('un cliente pertenece a una lista de precios', function () {
    $listaPrecio = ListaPrecio::factory()->create(['nombre' => 'Precio Mayor']);
    $cliente = Cliente::factory()->mayorista()->create(['lista_precio_id' => $listaPrecio->id]);

    expect($cliente->listaPrecio->nombre)->toBe('Precio Mayor');
});

test('un user tiene un perfil cliente', function () {
    $listaPrecio = ListaPrecio::factory()->create();
    $user = User::factory()->create();
    Cliente::factory()->create([
        'user_id' => $user->id,
        'lista_precio_id' => $listaPrecio->id,
    ]);

    expect($user->cliente)->toBeInstanceOf(Cliente::class);
});

test('no puede existir dos perfiles cliente para el mismo user', function () {
    $listaPrecio = ListaPrecio::factory()->create();
    $user = User::factory()->create();

    Cliente::factory()->create([
        'user_id' => $user->id,
        'lista_precio_id' => $listaPrecio->id,
    ]);

    expect(fn () => Cliente::factory()->create([
        'user_id' => $user->id,
        'lista_precio_id' => $listaPrecio->id,
    ]))->toThrow(QueryException::class);
});

test('scope minoristas filtra correctamente', function () {
    $listaPrecio = ListaPrecio::factory()->create();

    Cliente::factory()->minorista()->count(3)->create(['lista_precio_id' => $listaPrecio->id]);
    Cliente::factory()->mayorista()->count(2)->create(['lista_precio_id' => $listaPrecio->id]);

    expect(Cliente::minoristas()->count())->toBe(3);
});

test('scope mayoristas filtra correctamente', function () {
    $listaPrecio = ListaPrecio::factory()->create();

    Cliente::factory()->minorista()->count(2)->create(['lista_precio_id' => $listaPrecio->id]);
    Cliente::factory()->mayorista()->count(4)->create(['lista_precio_id' => $listaPrecio->id]);

    expect(Cliente::mayoristas()->count())->toBe(4);
});

test('factory mayorista es persona juridica con ruc', function () {
    $listaPrecio = ListaPrecio::factory()->create();
    $cliente = Cliente::factory()->mayorista()->create(['lista_precio_id' => $listaPrecio->id]);

    expect($cliente->ruc)->not->toBeNull()
        ->and($cliente->dni)->toBeNull()
        ->and($cliente->tipo_cliente)->toBe('mayorista')
        ->and($cliente->tipo_persona)->toBe('juridica');
});

test('nombreMostrar retorna razon_social si es empresa', function () {
    $listaPrecio = ListaPrecio::factory()->create();
    $cliente = Cliente::factory()->mayorista()->create([
        'lista_precio_id' => $listaPrecio->id,
        'razon_social' => 'Distribuidora XYZ SAC',
    ]);

    expect($cliente->nombreMostrar())->toBe('Distribuidora XYZ SAC');
});

test('esEmpresa y esPersonaNatural funcionan correctamente', function () {
    $listaPrecio = ListaPrecio::factory()->create();

    $persona = Cliente::factory()->minorista()->create(['lista_precio_id' => $listaPrecio->id]);
    $empresa = Cliente::factory()->mayorista()->create(['lista_precio_id' => $listaPrecio->id]);

    expect($persona->esPersonaNatural())->toBeTrue()
        ->and($persona->esEmpresa())->toBeFalse()
        ->and($empresa->esEmpresa())->toBeTrue()
        ->and($empresa->esPersonaNatural())->toBeFalse();
});

test('scope empresas filtra por tipo_persona juridica', function () {
    $listaPrecio = ListaPrecio::factory()->create();

    Cliente::factory()->minorista()->count(3)->create(['lista_precio_id' => $listaPrecio->id]);
    Cliente::factory()->mayorista()->count(2)->create(['lista_precio_id' => $listaPrecio->id]);

    expect(Cliente::empresas()->count())->toBe(2)
        ->and(Cliente::personasNaturales()->count())->toBe(3);
});

test('un cliente puede tener multiples direcciones', function () {
    $listaPrecio = ListaPrecio::factory()->create();
    $cliente = Cliente::factory()->create(['lista_precio_id' => $listaPrecio->id]);

    Direccion::factory()->count(3)->create(['cliente_id' => $cliente->id]);

    expect($cliente->direcciones)->toHaveCount(3);
});

test('solo una direccion puede ser predeterminada por cliente', function () {
    $listaPrecio = ListaPrecio::factory()->create();
    $cliente = Cliente::factory()->create(['lista_precio_id' => $listaPrecio->id]);

    Direccion::factory()->predeterminada()->create(['cliente_id' => $cliente->id]);
    Direccion::factory()->predeterminada()->create(['cliente_id' => $cliente->id]);

    expect(Direccion::where('cliente_id', $cliente->id)->where('es_predeterminada', true)->count())->toBe(1);
});

test('direccionCompleta retorna cadena formateada con ubigeos', function () {
    $listaPrecio = ListaPrecio::factory()->create();
    $cliente = Cliente::factory()->create(['lista_precio_id' => $listaPrecio->id]);

    $pais = Pais::factory()->peru()->create();
    $dep = Ubigeo::factory()->departamento($pais->id)->create(['nombre' => 'Lima', 'pais_id' => $pais->id]);
    $prov = Ubigeo::factory()->provinciaOf($dep)->create(['nombre' => 'Lima']);
    $dist = Ubigeo::factory()->distritoOf($prov)->create(['nombre' => 'Miraflores']);

    $direccion = Direccion::factory()->create([
        'cliente_id' => $cliente->id,
        'pais_id' => $pais->id,
        'departamento_id' => $dep->id,
        'provincia_id' => $prov->id,
        'distrito_id' => $dist->id,
        'direccion' => 'Av. Larco 456',
    ]);

    expect($direccion->direccionCompleta())->toBe('Av. Larco 456, Miraflores, Lima, Lima, Perú');
});

test('labelsGeograficos retorna etiquetas del pais', function () {
    $listaPrecio = ListaPrecio::factory()->create();
    $cliente = Cliente::factory()->create(['lista_precio_id' => $listaPrecio->id]);
    $pais = Pais::factory()->bolivia()->create();

    $direccion = Direccion::factory()->create([
        'cliente_id' => $cliente->id,
        'pais_id' => $pais->id,
    ]);

    $labels = $direccion->labelsGeograficos();

    expect($labels['nivel1'])->toBe('Departamento')
        ->and($labels['nivel2'])->toBe('Provincia')
        ->and($labels['nivel3'])->toBe('Municipio');
});

test('ubigeo labelNivel retorna la etiqueta correcta del pais', function () {
    $pais = Pais::factory()->peru()->create();
    $dep = Ubigeo::factory()->departamento($pais->id)->create(['pais_id' => $pais->id]);

    expect($dep->labelNivel())->toBe('Departamento');
});

test('departamentos de un pais se cargan por relacion', function () {
    $pais = Pais::factory()->create();
    Ubigeo::factory()->departamento($pais->id)->count(3)->create(['pais_id' => $pais->id]);

    expect($pais->departamentos)->toHaveCount(3);
});

test('provincias se cargan como hijos de un departamento', function () {
    $pais = Pais::factory()->create();
    $dep = Ubigeo::factory()->departamento($pais->id)->create(['pais_id' => $pais->id]);
    Ubigeo::factory()->provinciaOf($dep)->count(4)->create();

    expect($dep->hijos)->toHaveCount(4);
});
