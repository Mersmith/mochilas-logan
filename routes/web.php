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

        // Mantenimiento Base (solo admin)
        Route::livewire('mantenimiento', 'pages::mantenimiento.index')
            ->name('mantenimiento.index')
            ->middleware('permission:mantenimiento.ver');
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
        if (auth()->user()->hasPermissionTo('panel.acceder')) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('home');
    }

    return redirect()->route('login');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
