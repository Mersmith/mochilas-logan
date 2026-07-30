<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('guias', 'pages::guias.index')->name('guias.index');
    Route::livewire('guias/crear', 'pages::guias.create')->name('guias.create');
    Route::livewire('kardex', 'pages::kardex.index')->name('kardex.index');

    // Rutas de Catálogo de Productos
    Route::livewire('productos', 'pages::productos.index')->name('productos.index');
    Route::livewire('productos/crear', 'pages::productos.create')->name('productos.create');
    Route::livewire('productos/{producto}/gestionar', 'pages::productos.manage')->name('productos.manage');

    // Rutas del Módulo de Ventas
    Route::livewire('ventas', 'pages::ventas.index')->name('ventas.index');
    Route::livewire('ventas/nueva', 'pages::ventas.create')->name('ventas.create');

    // Rutas de Descuentos y Cupones
    Route::livewire('promociones', 'pages::promociones.index')->name('promociones.index');

    // Rutas de Mantenimiento Base
    Route::livewire('mantenimiento', 'pages::mantenimiento.index')->name('mantenimiento.index');
});

require __DIR__.'/settings.php';
