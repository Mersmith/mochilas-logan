<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\GuiaInventario;
use App\Models\GuiaInventarioDetalle;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\Proveedor;
use App\Models\Sede;
use App\Models\TipoDocumento;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Variacion;
use Illuminate\Database\Seeder;

class GuiaInventarioSeeder extends Seeder
{
    public function run(): void
    {
        // 16. Registrar una Guía de Entrada Inicial (Alimentación de Inventario)
        $admin = User::where('email', 'admin@logan.com')->firstOrFail();
        $provLogan = Proveedor::where('ruc', '20601234567')->firstOrFail();
        $sedeCentral = Sede::where('nombre', 'Sede Central Lima')->firstOrFail();
        $almacenCentral = Almacen::where('nombre', 'Almacén Central Principal')->firstOrFail();
        $docGuiaEntrada = TipoDocumento::where('nombre', 'Guía de Entrada')->firstOrFail();

        $guiaEntrada = GuiaInventario::firstOrCreate(
            [
                'serie' => 'GE01',
                'correlativo' => 1,
            ],
            [
                'tipo_movimiento' => 'Entrada',
                'proveedor_id' => $provLogan->id,
                'sede_destino_id' => $sedeCentral->id,
                'almacen_destino_id' => $almacenCentral->id,
                'tipo_documento_id' => $docGuiaEntrada->id,
                'fecha_movimiento' => now(),
                'estado' => 'Procesado',
                'motivo' => 'Compra',
                'creado_por_usuario_id' => $admin->id,
            ]
        );

        $varNegroM = Variacion::where('sku', 'LOG-OXF-NEG-M')->firstOrFail();
        $varAzulM = Variacion::where('sku', 'LOG-OXF-AZU-M')->firstOrFail();

        $uniCaja = UnidadMedida::where('nombre', 'Caja')->firstOrFail();
        $uniCostal = UnidadMedida::where('nombre', 'Costal')->firstOrFail();

        // Detalle 1: 5 costales de Negro / M (5 * 50 = 250 unidades)
        GuiaInventarioDetalle::firstOrCreate(
            ['guia_inventario_id' => $guiaEntrada->id, 'variacion_id' => $varNegroM->id],
            [
                'unidad_medida_id' => $uniCostal->id,
                'cantidad' => 5,
                'factor_conversion' => 50,
                'cantidad_base' => 250,
                'costo_unitario' => 45.00,
                'costo_total' => 11250.00,
            ]
        );

        // Detalle 2: 2 cajas de Azul / M (2 * 100 = 200 unidades)
        GuiaInventarioDetalle::firstOrCreate(
            ['guia_inventario_id' => $guiaEntrada->id, 'variacion_id' => $varAzulM->id],
            [
                'unidad_medida_id' => $uniCaja->id,
                'cantidad' => 2,
                'factor_conversion' => 100,
                'cantidad_base' => 200,
                'costo_unitario' => 45.00,
                'costo_total' => 9000.00,
            ]
        );

        // 17. Procesar el stock en Inventarios y Kardex
        // Para Negro M
        Inventario::firstOrCreate(
            ['almacen_id' => $almacenCentral->id, 'variacion_id' => $varNegroM->id],
            ['stock_base' => 250, 'stock_minimo' => 10]
        );

        Kardex::firstOrCreate(
            [
                'almacen_id' => $almacenCentral->id,
                'variacion_id' => $varNegroM->id,
                'origen_documento_id' => $guiaEntrada->id,
                'origen_documento_type' => GuiaInventario::class,
            ],
            [
                'tipo_transaccion' => 'Entrada',
                'concepto' => 'Compra',
                'cantidad' => 250,
                'stock_anterior' => 0,
                'stock_posterior' => 250,
                'costo_unitario' => 45.00,
                'costo_total' => 11250.00,
                'valor_total_almacen' => 11250.00,
                'creado_por_usuario_id' => $admin->id,
            ]
        );

        // Para Azul M
        Inventario::firstOrCreate(
            ['almacen_id' => $almacenCentral->id, 'variacion_id' => $varAzulM->id],
            ['stock_base' => 200, 'stock_minimo' => 10]
        );

        Kardex::firstOrCreate(
            [
                'almacen_id' => $almacenCentral->id,
                'variacion_id' => $varAzulM->id,
                'origen_documento_id' => $guiaEntrada->id,
                'origen_documento_type' => GuiaInventario::class,
            ],
            [
                'tipo_transaccion' => 'Entrada',
                'concepto' => 'Compra',
                'cantidad' => 200,
                'stock_anterior' => 0,
                'stock_posterior' => 200,
                'costo_unitario' => 45.00,
                'costo_total' => 9000.00,
                'valor_total_almacen' => 9000.00,
                'creado_por_usuario_id' => $admin->id,
            ]
        );
    }
}
