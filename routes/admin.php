<?php

use Illuminate\Support\Facades\Route;

// =============================================
// ZONA ADMINISTRATIVA (requiere permiso: panel.acceder)
// =============================================
Route::middleware(['auth', 'verified', 'can:panel.acceder'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard (admin y supervisor)
        Route::livewire('dashboard', 'pages::dashboard')
            ->name('dashboard')
            ->middleware('can:dashboard.ver');

        // =============================================
        // LOGÍSTICA
        // =============================================

        // Catálogo de Productos
        Route::livewire('productos', 'pages::productos.index')
            ->name('productos.index')
            ->middleware('can:productos.ver');
        Route::livewire('productos/crear', 'pages::productos.create')
            ->name('productos.create')
            ->middleware('can:productos.crear');
        Route::livewire('productos/{producto}/editar', 'pages::productos.edit')
            ->name('productos.edit')
            ->middleware('can:productos.editar');

        // Guías de Inventario (almacen, logistica, supervisor, admin)
        Route::livewire('guias', 'pages::guias.index')
            ->name('guias.index')
            ->middleware('can:guias.ver');
        Route::livewire('guias/crear', 'pages::guias.create')
            ->name('guias.create')
            ->middleware('can:guias.crear');

        // Kardex (almacen, supervisor, admin)
        Route::livewire('kardex', 'pages::kardex.index')
            ->name('kardex.index')
            ->middleware('can:kardex.ver');

        // =============================================
        // PUNTO DE VENTA
        // =============================================

        // Ventas / POS (vendedor, supervisor, admin)
        Route::livewire('ventas', 'pages::ventas.index')
            ->name('ventas.index')
            ->middleware('can:ventas.ver');
        Route::livewire('ventas/nueva', 'pages::ventas.create')
            ->name('ventas.create')
            ->middleware('can:ventas.crear');

        // Promociones y Cupones (supervisor, admin)
        Route::livewire('promociones', 'pages::promociones.index')
            ->name('promociones.index')
            ->middleware('can:promociones.ver');

        Route::livewire('descuentos', 'pages::descuentos.index')
            ->name('descuentos.index')
            ->middleware('can:descuentos.ver');

        // =============================================
        // MANTENIMIENTO: Organización
        // =============================================

        Route::livewire('sedes', 'pages::sedes.index')
            ->name('sedes.index')
            ->middleware('can:sedes.ver');
        Route::livewire('sedes/crear', 'pages::sedes.create')
            ->name('sedes.create')
            ->middleware('can:sedes.crear');
        Route::livewire('sedes/{sede}/editar', 'pages::sedes.edit')
            ->name('sedes.edit')
            ->middleware('can:sedes.editar');

        Route::livewire('almacenes', 'pages::almacenes.index')
            ->name('almacenes.index')
            ->middleware('can:almacenes.ver');
        Route::livewire('almacenes/crear', 'pages::almacenes.create')
            ->name('almacenes.create')
            ->middleware('can:almacenes.editar');
        Route::livewire('almacenes/{almacen}/editar', 'pages::almacenes.edit')
            ->name('almacenes.edit')
            ->middleware('can:almacenes.editar');

        // =============================================
        // MANTENIMIENTO: Clasificación
        // =============================================

        Route::livewire('tipos-producto', 'pages::tipos-producto.index')
            ->name('tipos-producto.index')
            ->middleware('can:tipos-producto.ver');
        Route::livewire('tipos-producto/crear', 'pages::tipos-producto.create')
            ->name('tipos-producto.create')
            ->middleware('can:tipos-producto.editar');
        Route::livewire('tipos-producto/{tipoProducto}/editar', 'pages::tipos-producto.edit')
            ->name('tipos-producto.edit')
            ->middleware('can:tipos-producto.editar');

        Route::livewire('categorias', 'pages::categorias.index')
            ->name('categorias.index')
            ->middleware('can:categorias.ver');
        Route::livewire('categorias/crear', 'pages::categorias.create')
            ->name('categorias.create')
            ->middleware('can:categorias.editar');
        Route::livewire('categorias/{categoria}/editar', 'pages::categorias.edit')
            ->name('categorias.edit')
            ->middleware('can:categorias.editar');

        Route::livewire('marcas', 'pages::marcas.index')
            ->name('marcas.index')
            ->middleware('can:marcas.ver');
        Route::livewire('marcas/crear', 'pages::marcas.create')
            ->name('marcas.create')
            ->middleware('can:marcas.editar');
        Route::livewire('marcas/{marca}/editar', 'pages::marcas.edit')
            ->name('marcas.edit')
            ->middleware('can:marcas.editar');

        Route::livewire('atributos', 'pages::atributos.index')
            ->name('atributos.index')
            ->middleware('can:atributos.ver');

        Route::livewire('unidades-medida', 'pages::unidades-medida.index')
            ->name('unidades-medida.index')
            ->middleware('can:unidades-medida.ver');

        // =============================================
        // MANTENIMIENTO: Comercial y Documentos
        // =============================================

        Route::livewire('proveedores', 'pages::proveedores.index')
            ->name('proveedores.index')
            ->middleware('can:proveedores.ver');

        Route::livewire('lista-precios', 'pages::lista-precios.index')
            ->name('lista-precios.index')
            ->middleware('can:lista-precios.ver');

        Route::livewire('tipos-documento', 'pages::tipos-documento.index')
            ->name('tipos-documento.index')
            ->middleware('can:tipos-documento.ver');
        Route::livewire('tipos-documento/crear', 'pages::tipos-documento.create')
            ->name('tipos-documento.create')
            ->middleware('can:tipos-documento.editar');
        Route::livewire('tipos-documento/{tipoDocumento}/editar', 'pages::tipos-documento.edit')
            ->name('tipos-documento.edit')
            ->middleware('can:tipos-documento.editar');

        Route::livewire('series', 'pages::series.index')
            ->name('series.index')
            ->middleware('can:series.ver');
        Route::livewire('series/crear', 'pages::series.create')
            ->name('series.create')
            ->middleware('can:series.editar');
        Route::livewire('series/{serie}/editar', 'pages::series.edit')
            ->name('series.edit')
            ->middleware('can:series.editar');

        // =============================================
        // SEGURIDAD: Roles y Permisos (admin)
        // =============================================

        Route::livewire('usuarios', 'pages::usuarios.index')
            ->name('usuarios.index')
            ->middleware('can:usuarios.ver');
        Route::livewire('usuarios/crear', 'pages::usuarios.create')
            ->name('usuarios.create')
            ->middleware('can:usuarios.editar');
        Route::livewire('usuarios/{usuario}/editar', 'pages::usuarios.edit')
            ->name('usuarios.edit')
            ->middleware('can:usuarios.editar');

        Route::livewire('roles', 'pages::roles.index')
            ->name('roles.index')
            ->middleware('can:roles.ver');

        Route::livewire('permisos', 'pages::permisos.index')
            ->name('permisos.index')
            ->middleware('can:permisos.ver');
    });
