<?php

use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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

        Route::get('productos/exportar-catalogo-pdf', function (Request $request) {
            $tipo = $request->query('tipo', 'minorista'); // minorista o mayorista
            $productos = Producto::with(['variacions.precios.listaPrecio', 'variacions.valores.atributo'])
                ->where('activo', true)
                ->orderBy('nombre')
                ->get();

            $pdf = Pdf::loadView('pdf.catalogo', compact('productos', 'tipo'));
            $pdf->setPaper('A4', 'portrait');

            return $pdf->download('catalogo-mochilas-'.$tipo.'.pdf');
        })->name('productos.exportar-pdf')
            ->middleware('can:productos.ver');

        // Guías de Inventario (almacen, logistica, supervisor, admin)
        Route::livewire('guias', 'pages::guias.index')
            ->name('guias.index')
            ->middleware('can:guias.ver');
        Route::livewire('guias/crear', 'pages::guias.create')
            ->name('guias.create')
            ->middleware('can:guias.crear');
        Route::livewire('guias/{guia}/editar', 'pages::guias.edit')
            ->name('guias.edit')
            ->middleware('can:guias.crear'); // Crear/Editar borrador requieren lo mismo usualmente
        Route::livewire('guias/{guia}', 'pages::guias.show')
            ->name('guias.show')
            ->middleware('can:guias.ver');

        // Kardex (almacen, supervisor, admin)
        Route::livewire('kardex', 'pages::kardex.index')
            ->name('kardex.index')
            ->middleware('can:kardex.ver');

        // Inventario (control de stock por SKU)
        Route::livewire('inventario', 'pages::inventario.index')
            ->name('inventario.index')
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

        // Descuentos y Cupones (supervisor, admin)
        Route::livewire('descuentos', 'pages::descuentos.index')
            ->name('descuentos.index')
            ->middleware('can:promociones.ver');
        Route::livewire('descuentos/crear', 'pages::descuentos.create')
            ->name('descuentos.create')
            ->middleware('can:promociones.crear');
        Route::livewire('descuentos/{descuento}/editar', 'pages::descuentos.edit')
            ->name('descuentos.edit')
            ->middleware('can:promociones.editar');

        Route::livewire('cupones', 'pages::cupones.index')
            ->name('cupones.index')
            ->middleware('can:promociones.ver');
        Route::livewire('cupones/crear', 'pages::cupones.create')
            ->name('cupones.create')
            ->middleware('can:promociones.crear');
        Route::livewire('cupones/{cupon}/editar', 'pages::cupones.edit')
            ->name('cupones.edit')
            ->middleware('can:promociones.editar');

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
        Route::livewire('atributos/crear', 'pages::atributos.create')
            ->name('atributos.create')
            ->middleware('can:atributos.editar');
        Route::livewire('atributos/{atributo}/editar', 'pages::atributos.edit')
            ->name('atributos.edit')
            ->middleware('can:atributos.editar');

        Route::livewire('unidades-medida', 'pages::unidades-medida.index')
            ->name('unidades-medida.index')
            ->middleware('can:unidades-medida.ver');
        Route::livewire('unidades-medida/crear', 'pages::unidades-medida.create')
            ->name('unidades-medida.create')
            ->middleware('can:unidades-medida.editar');
        Route::livewire('unidades-medida/{unidadMedida}/editar', 'pages::unidades-medida.edit')
            ->name('unidades-medida.edit')
            ->middleware('can:unidades-medida.editar');

        // =============================================
        // MANTENIMIENTO: Comercial y Documentos
        // =============================================

        Route::livewire('proveedores', 'pages::proveedores.index')
            ->name('proveedores.index')
            ->middleware('can:proveedores.ver');
        Route::livewire('proveedores/crear', 'pages::proveedores.create')
            ->name('proveedores.create')
            ->middleware('can:proveedores.editar');
        Route::livewire('proveedores/{proveedor}/editar', 'pages::proveedores.edit')
            ->name('proveedores.edit')
            ->middleware('can:proveedores.editar');

        Route::livewire('lista-precios', 'pages::lista-precios.index')
            ->name('lista-precios.index')
            ->middleware('can:lista-precios.ver');
        Route::livewire('lista-precios/crear', 'pages::lista-precios.create')
            ->name('lista-precios.create')
            ->middleware('can:lista-precios.editar');
        Route::livewire('lista-precios/{listaPrecio}/editar', 'pages::lista-precios.edit')
            ->name('lista-precios.edit')
            ->middleware('can:lista-precios.editar');

        Route::livewire('clientes', 'pages::clientes.index')
            ->name('clientes.index')
            ->middleware('can:clientes.ver');
        Route::livewire('clientes/crear', 'pages::clientes.create')
            ->name('clientes.create')
            ->middleware('can:clientes.crear');
        Route::livewire('clientes/{cliente}/editar', 'pages::clientes.edit')
            ->name('clientes.edit')
            ->middleware('can:clientes.editar');

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
        // ANALÍTICA Y REPORTES
        // =============================================
        Route::livewire('reportes/skus', 'pages::reportes.skus.index')
            ->name('reportes.skus.index')
            ->middleware('can:productos.ver');

        Route::livewire('reportes/inventario', 'pages::reportes.inventario.index')
            ->name('reportes.inventario.index')
            ->middleware('can:kardex.ver');

        Route::livewire('reportes/comercial', 'pages::reportes.comercial.index')
            ->name('reportes.comercial.index')
            ->middleware('can:ventas.ver');

        Route::livewire('reportes/clientes', 'pages::reportes.clientes.index')
            ->name('reportes.clientes.index')
            ->middleware('can:ventas.ver');

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
