<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Atributo;
use App\Models\AtributoValor;
use App\Models\Categoria;
use App\Models\GuiaInventario;
use App\Models\GuiaInventarioDetalle;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\ListaPrecio;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoEmpaque;
use App\Models\Proveedor;
use App\Models\Sede;
use App\Models\Serie;
use App\Models\TipoDocumento;
use App\Models\TipoProducto;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Variacion;
use App\Models\VariacionPrecio;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 0. Crear Roles y Permisos primero
        $this->call(RolesAndPermissionsSeeder::class);

        // 1. Crear Usuario Admin por defecto
        $admin = User::factory()->create([
            'name' => 'Administrador Logan',
            'email' => 'admin@logan.com',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        // 2. Crear Sedes
        $sedeCentral = Sede::create([
            'nombre' => 'Sede Central Lima',
            'direccion' => 'Av. Javier Prado 1234, San Isidro',
            'activo' => true,
        ]);

        $sedeNorte = Sede::create([
            'nombre' => 'Sede Sucursal Norte',
            'direccion' => 'Av. Alfredo Mendiola 4567, Los Olivos',
            'activo' => true,
        ]);

        // 3. Crear Almacenes
        $almacenCentral = Almacen::create([
            'sede_id' => $sedeCentral->id,
            'nombre' => 'Almacén Central Principal',
            'ubicacion' => 'Sótano 1, Lima',
            'activo' => true,
        ]);

        $almacenNorte = Almacen::create([
            'sede_id' => $sedeNorte->id,
            'nombre' => 'Almacén Exhibición Norte',
            'ubicacion' => 'Piso 1, Los Olivos',
            'activo' => true,
        ]);

        // 4. Crear Tipos de Documentos
        $docBoleta = TipoDocumento::create(['nombre' => 'Boleta de Venta', 'codigo_sunat' => '03']);
        $docFactura = TipoDocumento::create(['nombre' => 'Factura', 'codigo_sunat' => '01']);
        $docGuiaEntrada = TipoDocumento::create(['nombre' => 'Guía de Entrada', 'codigo_sunat' => '99']);
        $docGuiaSalida = TipoDocumento::create(['nombre' => 'Guía de Salida', 'codigo_sunat' => '98']);
        $docGuiaTransferencia = TipoDocumento::create(['nombre' => 'Guía de Transferencia', 'codigo_sunat' => '09']);

        // 5. Crear Series
        Serie::create(['sede_id' => $sedeCentral->id, 'tipo_documento_id' => $docBoleta->id, 'serie' => 'B001', 'correlativo' => 100]);
        Serie::create(['sede_id' => $sedeCentral->id, 'tipo_documento_id' => $docFactura->id, 'serie' => 'F001', 'correlativo' => 100]);
        Serie::create(['sede_id' => $sedeCentral->id, 'tipo_documento_id' => $docGuiaEntrada->id, 'serie' => 'GE01', 'correlativo' => 1]);
        Serie::create(['sede_id' => $sedeCentral->id, 'tipo_documento_id' => $docGuiaSalida->id, 'serie' => 'GS01', 'correlativo' => 1]);
        Serie::create(['sede_id' => $sedeCentral->id, 'tipo_documento_id' => $docGuiaTransferencia->id, 'serie' => 'GT01', 'correlativo' => 1]);

        // 6. Crear Tipos de Productos
        $tipoMochila = TipoProducto::create(['nombre' => 'Mochila', 'slug' => 'mochila']);
        $tipoCartera = TipoProducto::create(['nombre' => 'Cartera', 'slug' => 'cartera']);
        $tipoMaletin = TipoProducto::create(['nombre' => 'Maletín', 'slug' => 'maletin']);

        // 7. Crear Marcas
        $marcaLogan = Marca::create(['nombre' => 'Logan', 'slug' => 'logan', 'descripcion' => 'Mochilas Logan Original']);
        $marcaSamsonite = Marca::create(['nombre' => 'Samsonite', 'slug' => 'samsonite', 'descripcion' => 'Maletas y carteras Samsonite']);

        // 8. Crear Categorías
        $catMochilas = Categoria::create(['nombre' => 'Mochilas', 'slug' => 'mochilas', 'descripcion' => 'Todo tipo de mochilas']);
        $subEscolares = Categoria::create(['categoria_padre_id' => $catMochilas->id, 'nombre' => 'Escolares', 'slug' => 'escolares', 'descripcion' => 'Mochilas para el colegio']);
        $subUrbanas = Categoria::create(['categoria_padre_id' => $catMochilas->id, 'nombre' => 'Urbanas', 'slug' => 'urbanas', 'descripcion' => 'Mochilas de uso diario']);

        // 9. Crear Unidades de Medida
        $uniUnidad = UnidadMedida::create(['nombre' => 'Unidad', 'abreviacion' => 'UND']);
        $uniCaja = UnidadMedida::create(['nombre' => 'Caja', 'abreviacion' => 'CJ']);
        $uniCostal = UnidadMedida::create(['nombre' => 'Costal', 'abreviacion' => 'CST']);

        // 10. Crear Atributos y Valores
        $attrColor = Atributo::create(['nombre' => 'Color']);
        $colorNegro = AtributoValor::create(['atributo_id' => $attrColor->id, 'valor' => 'Negro', 'codigo_color_hex' => '#000000']);
        $colorAzul = AtributoValor::create(['atributo_id' => $attrColor->id, 'valor' => 'Azul', 'codigo_color_hex' => '#0000FF']);
        $colorRojo = AtributoValor::create(['atributo_id' => $attrColor->id, 'valor' => 'Rojo', 'codigo_color_hex' => '#FF0000']);

        $attrTalla = Atributo::create(['nombre' => 'Talla']);
        $tallaM = AtributoValor::create(['atributo_id' => $attrTalla->id, 'valor' => 'M']);
        $tallaL = AtributoValor::create(['atributo_id' => $attrTalla->id, 'valor' => 'L']);

        // 11. Crear Listas de Precios
        $listaMenor = ListaPrecio::create(['nombre' => 'Precio Menor']);
        $listaMayor = ListaPrecio::create(['nombre' => 'Precio Mayor']);

        // 12. Crear Proveedores
        $provLogan = Proveedor::create([
            'razon_social' => 'Corporación Logan Import S.A.C.',
            'ruc' => '20601234567',
            'direccion' => 'Calle Los Álamos 450, San Isidro',
            'contacto_nombre' => 'Carlos Logan',
            'contacto_celular' => '999888777',
            'activo' => true,
        ]);

        // 13. Crear un Producto Base
        $prodOxford = Producto::create([
            'tipo_producto_id' => $tipoMochila->id,
            'marca_id' => $marcaLogan->id,
            'categoria_id' => $subUrbanas->id,
            'nombre' => 'Mochila Logan Oxford',
            'slug' => 'mochila-logan-oxford',
            'descripcion' => 'Mochila urbana de lona Oxford impermeable con compartimento para laptop de 15.6 pulgadas.',
            'activo' => true,
        ]);

        // Registrar empaques para este producto
        ProductoEmpaque::create(['producto_id' => $prodOxford->id, 'unidad_medida_id' => $uniUnidad->id, 'factor_conversion' => 1]);
        ProductoEmpaque::create(['producto_id' => $prodOxford->id, 'unidad_medida_id' => $uniCaja->id, 'factor_conversion' => 100]);
        ProductoEmpaque::create(['producto_id' => $prodOxford->id, 'unidad_medida_id' => $uniCostal->id, 'factor_conversion' => 50]);

        // 14. Crear Variaciones para el Producto
        // Variación 1: Negro / M
        $varNegroM = Variacion::create([
            'producto_id' => $prodOxford->id,
            'sku' => 'LOG-OXF-NEG-M',
            'codigo_barras' => '7750123456789',
            'activo' => true,
        ]);
        $varNegroM->valores()->attach([$colorNegro->id, $tallaM->id]);

        // Variación 2: Negro / L
        $varNegroL = Variacion::create([
            'producto_id' => $prodOxford->id,
            'sku' => 'LOG-OXF-NEG-L',
            'codigo_barras' => '7750123456790',
            'activo' => true,
        ]);
        $varNegroL->valores()->attach([$colorNegro->id, $tallaL->id]);

        // Variación 3: Azul / M
        $varAzulM = Variacion::create([
            'producto_id' => $prodOxford->id,
            'sku' => 'LOG-OXF-AZU-M',
            'codigo_barras' => '7750123456791',
            'activo' => true,
        ]);
        $varAzulM->valores()->attach([$colorAzul->id, $tallaM->id]);

        // 15. Registrar precios por variación
        VariacionPrecio::create(['variacion_id' => $varNegroM->id, 'lista_precio_id' => $listaMenor->id, 'precio' => 120.00, 'simbolo' => 'S/']);
        VariacionPrecio::create(['variacion_id' => $varNegroM->id, 'lista_precio_id' => $listaMayor->id, 'precio' => 95.00, 'simbolo' => 'S/']);

        VariacionPrecio::create(['variacion_id' => $varNegroL->id, 'lista_precio_id' => $listaMenor->id, 'precio' => 140.00, 'simbolo' => 'S/']);
        VariacionPrecio::create(['variacion_id' => $varNegroL->id, 'lista_precio_id' => $listaMayor->id, 'precio' => 110.00, 'simbolo' => 'S/']);

        VariacionPrecio::create(['variacion_id' => $varAzulM->id, 'lista_precio_id' => $listaMenor->id, 'precio' => 120.00, 'simbolo' => 'S/']);
        VariacionPrecio::create(['variacion_id' => $varAzulM->id, 'lista_precio_id' => $listaMayor->id, 'precio' => 95.00, 'simbolo' => 'S/']);

        // 16. Registrar una Guía de Entrada Inicial (Alimentación de Inventario)
        $guiaEntrada = GuiaInventario::create([
            'tipo_movimiento' => 'Entrada',
            'proveedor_id' => $provLogan->id,
            'sede_destino_id' => $sedeCentral->id,
            'almacen_destino_id' => $almacenCentral->id,
            'tipo_documento_id' => $docGuiaEntrada->id,
            'serie' => 'GE01',
            'correlativo' => 1,
            'fecha_movimiento' => now(),
            'estado' => 'Procesado',
            'motivo' => 'Compra',
            'creado_por_usuario_id' => $admin->id,
        ]);

        // Detalle 1: 5 costales de Negro / M (5 * 50 = 250 unidades)
        $detalle1 = GuiaInventarioDetalle::create([
            'guia_inventario_id' => $guiaEntrada->id,
            'variacion_id' => $varNegroM->id,
            'unidad_medida_id' => $uniCostal->id,
            'cantidad' => 5,
            'factor_conversion' => 50,
            'cantidad_base' => 250,
            'costo_unitario' => 45.00,
            'costo_total' => 11250.00,
        ]);

        // Detalle 2: 2 cajas de Azul / M (2 * 100 = 200 unidades)
        $detalle2 = GuiaInventarioDetalle::create([
            'guia_inventario_id' => $guiaEntrada->id,
            'variacion_id' => $varAzulM->id,
            'unidad_medida_id' => $uniCaja->id,
            'cantidad' => 2,
            'factor_conversion' => 100,
            'cantidad_base' => 200,
            'costo_unitario' => 45.00,
            'costo_total' => 9000.00,
        ]);

        // 17. Procesar el stock en Inventarios y Kardex
        // Para Negro M
        $invNegroM = Inventario::create([
            'almacen_id' => $almacenCentral->id,
            'variacion_id' => $varNegroM->id,
            'stock_base' => 250,
            'stock_minimo' => 10,
        ]);

        Kardex::create([
            'almacen_id' => $almacenCentral->id,
            'variacion_id' => $varNegroM->id,
            'tipo_transaccion' => 'Entrada',
            'concepto' => 'Compra',
            'cantidad' => 250,
            'stock_anterior' => 0,
            'stock_posterior' => 250,
            'costo_unitario' => 45.00,
            'costo_total' => 11250.00,
            'valor_total_almacen' => 11250.00,
            'origen_documento_id' => $guiaEntrada->id,
            'origen_documento_type' => GuiaInventario::class,
            'creado_por_usuario_id' => $admin->id,
        ]);

        // Para Azul M
        $invAzulM = Inventario::create([
            'almacen_id' => $almacenCentral->id,
            'variacion_id' => $varAzulM->id,
            'stock_base' => 200,
            'stock_minimo' => 10,
        ]);

        Kardex::create([
            'almacen_id' => $almacenCentral->id,
            'variacion_id' => $varAzulM->id,
            'tipo_transaccion' => 'Entrada',
            'concepto' => 'Compra',
            'cantidad' => 200,
            'stock_anterior' => 0,
            'stock_posterior' => 200,
            'costo_unitario' => 45.00,
            'costo_total' => 9000.00,
            'valor_total_almacen' => 9000.00,
            'origen_documento_id' => $guiaEntrada->id,
            'origen_documento_type' => GuiaInventario::class,
            'creado_por_usuario_id' => $admin->id,
        ]);
    }
}
