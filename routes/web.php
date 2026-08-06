<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// =============================================
// ZONA ADMINISTRATIVA (requiere permiso: panel.acceder)
// =============================================
Route::middleware(['auth', 'verified', 'permission:panel.acceder'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard (admin y supervisor)
        Route::livewire('dashboard', 'pages::dashboard')
            ->name('dashboard')
            ->middleware('permission:dashboard.ver');

        // Guías de Inventario (almacen, logistica, supervisor, admin)
        Route::livewire('guias', 'pages::guias.index')
            ->name('guias.index')
            ->middleware('permission:guias.ver');
        Route::livewire('guias/crear', 'pages::guias.create')
            ->name('guias.create')
            ->middleware('permission:guias.crear');

        // Kardex (almacen, supervisor, admin)
        Route::livewire('kardex', 'pages::kardex.index')
            ->name('kardex.index')
            ->middleware('permission:kardex.ver');

        // Catálogo de Productos
        Route::livewire('productos', 'pages::productos.index')
            ->name('productos.index')
            ->middleware('permission:productos.ver');
        Route::livewire('productos/crear', 'pages::productos.create')
            ->name('productos.create')
            ->middleware('permission:productos.crear');
        Route::livewire('productos/{producto}/gestionar', 'pages::productos.manage')
            ->name('productos.manage')
            ->middleware('permission:productos.editar');

        // Ventas / POS (vendedor, supervisor, admin)
        Route::livewire('ventas', 'pages::ventas.index')
            ->name('ventas.index')
            ->middleware('permission:ventas.ver');
        Route::livewire('ventas/nueva', 'pages::ventas.create')
            ->name('ventas.create')
            ->middleware('permission:ventas.crear');

        // Promociones y Cupones (supervisor, admin)
        Route::livewire('promociones', 'pages::promociones.index')
            ->name('promociones.index')
            ->middleware('permission:promociones.ver');

        // =============================================
        // MÓDULOS DE CONFIGURACIÓN Y MANTENIMIENTO
        // =============================================

        Route::livewire('almacenes', 'pages::almacenes.index')
            ->name('almacenes.index')
            ->middleware('permission:almacenes.ver');

        Route::livewire('series', 'pages::series.index')
            ->name('series.index')
            ->middleware('permission:series.ver');

        Route::livewire('tipos-documento', 'pages::tipos-documento.index')
            ->name('tipos-documento.index')
            ->middleware('permission:tipos-documento.ver');

        Route::livewire('tipos-producto', 'pages::tipos-producto.index')
            ->name('tipos-producto.index')
            ->middleware('permission:tipos-producto.ver');

        Route::livewire('marcas', 'pages::marcas.index')
            ->name('marcas.index')
            ->middleware('permission:marcas.ver');

        Route::livewire('categorias', 'pages::categorias.index')
            ->name('categorias.index')
            ->middleware('permission:categorias.ver');

        Route::livewire('atributos', 'pages::atributos.index')
            ->name('atributos.index')
            ->middleware('permission:atributos.ver');

        Route::livewire('unidades-medida', 'pages::unidades-medida.index')
            ->name('unidades-medida.index')
            ->middleware('permission:unidades-medida.ver');

        Route::livewire('lista-precios', 'pages::lista-precios.index')
            ->name('lista-precios.index')
            ->middleware('permission:lista-precios.ver');

        Route::livewire('proveedores', 'pages::proveedores.index')
            ->name('proveedores.index')
            ->middleware('permission:proveedores.ver');

        // Sedes (Mantenimiento)
        Route::livewire('sedes', 'pages::sedes.index')
            ->name('sedes.index')
            ->middleware('permission:sedes.ver');

        // Seguridad (Roles y Permisos - admin)
        Route::livewire('roles', 'pages::roles.index')
            ->name('roles.index')
            ->middleware('permission:roles.ver');

        Route::livewire('permisos', 'pages::permisos.index')
            ->name('permisos.index')
            ->middleware('permission:permisos.ver');
    });

// =============================================
// ZONA PÚBLICA: E-Commerce (sin autenticación)
// =============================================

// Catálogo Público
Route::livewire('catalogo', 'pages::catalogo')->name('catalogo');

// Ficha de Producto
Route::livewire('producto/{producto}/{slug}', 'pages::producto')->name('producto.detalle');

// Bolsa de Compras
Route::livewire('carrito', 'pages::carrito')->name('carrito');

// Checkout (requiere estar autenticado)
Route::livewire('checkout', 'pages::checkout')
    ->name('checkout')
    ->middleware(['auth', 'verified']);

// =============================================
// DESPACHADOR DE DASHBOARD SEGÚN ROL
// =============================================
Route::get('dashboard', function () {
    if (auth()->check()) {
        $user = auth()->user();

        if ($user->hasPermissionTo('dashboard.ver')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasPermissionTo('ventas.ver')) {
            return redirect()->route('admin.ventas.index');
        }

        if ($user->hasPermissionTo('guias.ver')) {
            return redirect()->route('admin.guias.index');
        }

        if ($user->hasPermissionTo('productos.ver')) {
            return redirect()->route('admin.productos.index');
        }

        if ($user->hasPermissionTo('panel.acceder')) {
            return redirect()->route('admin.dashboard'); // Fallback in case of weird permissions
        }

        return redirect()->route('home');
    }

    return redirect()->route('login');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
