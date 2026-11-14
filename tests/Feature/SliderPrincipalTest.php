<?php

use App\Models\SliderPrincipal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup roles and permissions for tests
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $permissions = [
        'panel.acceder',
        'sliders.ver',
        'sliders.crear',
        'sliders.editar',
        'sliders.eliminar',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $role->syncPermissions($permissions);
});

test('welcome page renders successfully and displays active sliders', function () {
    $slider = SliderPrincipal::factory()->create([
        'nombre' => 'Super Banner Logan',
        'activo' => true,
        'orden' => 1,
    ]);

    $response = $this->get(route('home'));

    $response->assertSuccessful();
    $response->assertSee('Super Banner Logan');
});

test('unauthenticated users cannot access sliders management', function () {
    $response = $this->get(route('admin.sliders.index'));

    $response->assertRedirect(route('login'));
});

test('authorized users can view the sliders index page', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user);

    $response = $this->get(route('admin.sliders.index'));

    $response->assertSuccessful();
});

test('can create a slider via livewire component with image uploads', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user);

    $desktopFile = UploadedFile::fake()->image('desktop.jpg', 1920, 600);
    $movilFile = UploadedFile::fake()->image('movil.jpg', 800, 800);

    Livewire::test('pages::erp.sliders.create')
        ->set('nombre', 'Nuevo Banner 2026')
        ->set('link', '/catalogo')
        ->set('img_computadora', $desktopFile)
        ->set('img_movil', $movilFile)
        ->set('orden', 1)
        ->set('activo', true)
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.sliders.index'));

    $this->assertDatabaseHas('slider_principals', [
        'nombre' => 'Nuevo Banner 2026',
        'link' => '/catalogo',
        'orden' => 1,
        'activo' => true,
    ]);

    $slider = SliderPrincipal::where('nombre', 'Nuevo Banner 2026')->first();
    expect($slider)->not->toBeNull();
    Storage::disk('public')->assertExists($slider->img_computadora);
    Storage::disk('public')->assertExists($slider->img_movil);
});

test('can reorder sliders and toggle status via livewire index', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user);

    $s1 = SliderPrincipal::factory()->create(['nombre' => 'Slider 1', 'orden' => 1, 'activo' => true]);
    $s2 = SliderPrincipal::factory()->create(['nombre' => 'Slider 2', 'orden' => 2, 'activo' => true]);

    Livewire::test('pages::erp.sliders.index')
        ->call('toggleActivo', $s1->id)
        ->assertHasNoErrors();

    expect($s1->fresh()->activo)->toBeFalse();

    // Reorder: Move s2 to position 0 (first)
    Livewire::test('pages::erp.sliders.index')
        ->call('reordenar', $s2->id, 0)
        ->assertHasNoErrors();

    expect($s2->fresh()->orden)->toBe(1)
        ->and($s1->fresh()->orden)->toBe(2);
});

test('can delete a slider via livewire index', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user);

    $slider = SliderPrincipal::create([
        'nombre' => 'Para Eliminar',
        'img_computadora' => 'sliders/fake_pc.jpg',
        'img_movil' => 'sliders/fake_mov.jpg',
        'orden' => 1,
        'activo' => true,
    ]);

    Storage::disk('public')->put('sliders/fake_pc.jpg', 'content');
    Storage::disk('public')->put('sliders/fake_mov.jpg', 'content');

    Livewire::test('pages::erp.sliders.index')
        ->set('idEliminar', $slider->id)
        ->call('ejecutarEliminacion')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('slider_principals', [
        'id' => $slider->id,
    ]);

    Storage::disk('public')->assertMissing('sliders/fake_pc.jpg');
    Storage::disk('public')->assertMissing('sliders/fake_mov.jpg');
});
