<?php

use App\Models\GuiaInventario;
use App\Models\GuiaInventarioDetalle;
use App\Models\Almacen;
use App\Models\Proveedor;
use App\Models\TipoDocumento;
use App\Models\Producto;
use App\Models\Variacion;
use App\Models\ProductoEmpaque;
use App\Models\Inventario;
use App\Models\Kardex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Registrar Guía de Inventario')] class extends Component {
    public string $tipo_movimiento = 'Entrada';
    public ?int $proveedor_id = null;
    public ?int $almacen_origen_id = null;
    public ?int $almacen_destino_id = null;
    public ?int $tipo_documento_id = null;
    public string $serie = '';
    public int $correlativo = 1;
    public string $fecha_movimiento = '';
    public string $motivo = 'Compra';
    
    public array $detalles = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->fecha_movimiento = now()->format('Y-m-d');
        
        // Cargar por defecto la primera serie si existe
        $this->actualizarSeriesPorDefecto();
        
        // Agregar la primera fila de detalle vacía
        $this->addDetalle();
    }

    /**
     * Load default series based on selected document type and warehouse.
     */
    public function actualizarSeriesPorDefecto(): void
    {
        // Buscar el tipo de documento adecuado
        $codigoSunat = $this->tipo_movimiento === 'Entrada' ? '99' : ($this->tipo_movimiento === 'Salida' ? '98' : '09');
        $tipoDoc = TipoDocumento::where('codigo_sunat', $codigoSunat)->first();
        
        if ($tipoDoc) {
            $this->tipo_documento_id = $tipoDoc->id;
            
            // Jalar la serie de la Sede del almacén destino/origen si existe
            $almacenId = $this->tipo_movimiento === 'Entrada' ? $this->almacen_destino_id : $this->almacen_origen_id;
            $almacen = Almacen::find($almacenId);
            
            if ($almacen) {
                $serie = DB::table('series')
                    ->where('sede_id', $almacen->sede_id)
                    ->where('tipo_documento_id', $tipoDoc->id)
                    ->where('activo', true)
                    ->first();
                    
                if ($serie) {
                    $this->serie = $serie->serie;
                    $this->correlativo = $serie->correlativo + 1;
                    return;
                }
            }
            
            // Valor de respaldo por defecto
            $this->serie = $this->tipo_movimiento === 'Entrada' ? 'GE01' : ($this->tipo_movimiento === 'Salida' ? 'GS01' : 'GT01');
            $this->correlativo = 1;
        }
    }

    /**
     * Triggered when Tipo Movimiento changes.
     */
    public function updatedTipoMovimiento(): void
    {
        $this->proveedor_id = null;
        $this->almacen_origen_id = null;
        $this->almacen_destino_id = null;
        
        if ($this->tipo_movimiento === 'Entrada') {
            $this->motivo = 'Compra';
        } elseif ($this->tipo_movimiento === 'Salida') {
            $this->motivo = 'Merma';
        } else {
            $this->motivo = 'Traslado entre almacenes';
        }

        $this->actualizarSeriesPorDefecto();
    }

    public function updatedAlmacenOrigenId(): void
    {
        $this->actualizarSeriesPorDefecto();
    }

    public function updatedAlmacenDestinoId(): void
    {
        $this->actualizarSeriesPorDefecto();
    }

    /**
     * Add a detail row.
     */
    public function addDetalle(): void
    {
        $this->detalles[] = [
            'producto_id' => '',
            'variacion_id' => '',
            'unidad_medida_id' => '',
            'cantidad' => 1,
            'factor_conversion' => 1,
            'cantidad_base' => 1,
            'costo_unitario' => 0.00,
            'costo_total' => 0.00,
            'variaciones' => [],
            'empaques' => [],
        ];
    }

    /**
     * Remove a detail row.
     */
    public function removeDetalle(int $index): void
    {
        unset($this->detalles[$index]);
        $this->detalles = array_values($this->detalles);
        
        if (empty($this->detalles)) {
            $this->addDetalle();
        }
    }

    /**
     * Update dynamic product values on a row.
     */
    public function updatedDetalles($value, $name): void
    {
        // $name tiene el formato "detalles.INDEX.KEY"
        if (preg_match('/^detalles\.(\d+)\.(producto_id|variacion_id|unidad_medida_id|cantidad|costo_unitario)$/', $name, $matches)) {
            $index = (int)$matches[1];
            $field = $matches[2];
            
            if ($field === 'producto_id') {
                $productId = $this->detalles[$index]['producto_id'];
                
                if ($productId) {
                    // Cargar variaciones asociadas al producto
                    $this->detalles[$index]['variaciones'] = Variacion::with('valores.atributo')
                        ->where('producto_id', $productId)
                        ->get()
                        ->map(function ($v) {
                            $desc = $v->valores->map(fn($val) => $val->atributo->nombre . ': ' . $val->valor)->implode(', ');
                            return [
                                'id' => $v->id,
                                'descripcion' => $v->sku . ' (' . $desc . ')',
                            ];
                        })->toArray();
                        
                    // Cargar empaques y sus factores de conversión
                    $this->detalles[$index]['empaques'] = ProductoEmpaque::with('unidadMedida')
                        ->where('producto_id', $productId)
                        ->get()
                        ->map(function ($pe) {
                            return [
                                'unidad_medida_id' => $pe->unidad_medida_id,
                                'nombre' => $pe->unidadMedida->nombre . ' (x' . $pe->factor_conversion . ')',
                                'factor' => $pe->factor_conversion,
                            ];
                        })->toArray();
                } else {
                    $this->detalles[$index]['variaciones'] = [];
                    $this->detalles[$index]['empaques'] = [];
                }
                
                $this->detalles[$index]['variacion_id'] = '';
                $this->detalles[$index]['unidad_medida_id'] = '';
                $this->detalles[$index]['factor_conversion'] = 1;
                $this->detalles[$index]['cantidad_base'] = 1;
            }
            
            if ($field === 'unidad_medida_id') {
                $umId = $this->detalles[$index]['unidad_medida_id'];
                $empaque = collect($this->detalles[$index]['empaques'])->firstWhere('unidad_medida_id', $umId);
                
                if ($empaque) {
                    $this->detalles[$index]['factor_conversion'] = $empaque['factor'];
                } else {
                    $this->detalles[$index]['factor_conversion'] = 1;
                }
            }
            
            // Recalcular cantidad base y costo total
            $cantidad = (int)($this->detalles[$index]['cantidad'] ?: 1);
            $factor = (int)($this->detalles[$index]['factor_conversion'] ?: 1);
            $this->detalles[$index]['cantidad_base'] = $cantidad * $factor;
            
            $costoUnitario = (float)($this->detalles[$index]['costo_unitario'] ?: 0);
            $this->detalles[$index]['costo_total'] = round($this->detalles[$index]['cantidad_base'] * $costoUnitario, 2);
        }
    }

    /**
     * Save/Process the guide.
     */
    public function guardar(string $estado): void
    {
        // Validaciones básicas de negocio
        $rules = [
            'tipo_movimiento' => 'required|in:Entrada,Salida,Transferencia',
            'fecha_movimiento' => 'required|date',
            'serie' => 'required|string|max:10',
            'correlativo' => 'required|integer|min:1',
            'motivo' => 'required|string',
        ];

        if ($this->tipo_movimiento === 'Entrada') {
            $rules['almacen_destino_id'] = 'required|integer';
        } elseif ($this->tipo_movimiento === 'Salida') {
            $rules['almacen_origen_id'] = 'required|integer';
        } else {
            $rules['almacen_origen_id'] = 'required|integer';
            $rules['almacen_destino_id'] = 'required|integer|different:almacen_origen_id';
        }

        $this->validate($rules);

        // Validar que haya al menos un detalle válido
        $detallesValidos = collect($this->detalles)->filter(function ($d) {
            return !empty($d['variacion_id']) && !empty($d['unidad_medida_id']) && $d['cantidad'] > 0;
        });

        if ($detallesValidos->isEmpty()) {
            Flux::toast(variant: 'danger', text: __('Debe agregar al menos un detalle de producto válido.'));
            return;
        }

        DB::transaction(function () use ($estado, $detallesValidos) {
            // Obtener las sedes asociadas a los almacenes
            $almacenOrigen = Almacen::find($this->almacen_origen_id);
            $almacenDestino = Almacen::find($this->almacen_destino_id);

            // Crear cabecera de la guía
            $guia = GuiaInventario::create([
                'tipo_movimiento' => $this->tipo_movimiento,
                'proveedor_id' => $this->proveedor_id,
                'sede_origen_id' => $almacenOrigen?->sede_id,
                'almacen_origen_id' => $this->almacen_origen_id,
                'sede_destino_id' => $almacenDestino?->sede_id,
                'almacen_destino_id' => $this->almacen_destino_id,
                'tipo_documento_id' => $this->tipo_documento_id,
                'serie' => $this->serie,
                'correlativo' => $this->correlativo,
                'fecha_movimiento' => $this->fecha_movimiento,
                'estado' => $estado,
                'motivo' => $this->motivo,
                'creado_por_usuario_id' => Auth::id(),
            ]);

            // Guardar detalles e impactar stock/Kardex si está Procesado
            foreach ($detallesValidos as $d) {
                $detalle = GuiaInventarioDetalle::create([
                    'guia_inventario_id' => $guia->id,
                    'variacion_id' => $d['variacion_id'],
                    'unidad_medida_id' => $d['unidad_medida_id'],
                    'cantidad' => $d['cantidad'],
                    'factor_conversion' => $d['factor_conversion'],
                    'cantidad_base' => $d['cantidad_base'],
                    'costo_unitario' => $d['costo_unitario'],
                    'costo_total' => $d['costo_total'],
                ]);

                if ($estado === 'Procesado') {
                    $this->procesarMovimientoStock($guia, $detalle);
                }
            }

            // Incrementar correlativo oficial en la tabla de series
            if ($estado === 'Procesado' && $almacenOrigen && $this->tipo_movimiento !== 'Entrada') {
                DB::table('series')
                    ->where('sede_id', $almacenOrigen->sede_id)
                    ->where('tipo_documento_id', $this->tipo_documento_id)
                    ->where('serie', $this->serie)
                    ->increment('correlativo');
            } elseif ($estado === 'Procesado' && $almacenDestino && $this->tipo_movimiento === 'Entrada') {
                DB::table('series')
                    ->where('sede_id', $almacenDestino->sede_id)
                    ->where('tipo_documento_id', $this->tipo_documento_id)
                    ->where('serie', $this->serie)
                    ->increment('correlativo');
            }
        });

        Flux::toast(variant: 'success', text: $estado === 'Procesado' ? __('Guía procesada y stock actualizado.') : __('Guía guardada en borrador.'));
        $this->redirect(route('admin.guias.index'), navigate: true);
    }

    /**
     * Process physical stock and write to Kardex.
     */
    protected function procesarMovimientoStock(GuiaInventario $guia, GuiaInventarioDetalle $detalle): void
    {
        // 1. Salida física (Origen)
        if ($guia->almacen_origen_id) {
            $invOrigen = Inventario::firstOrCreate(
                ['almacen_id' => $guia->almacen_origen_id, 'variacion_id' => $detalle->variacion_id],
                ['stock_base' => 0, 'stock_minimo' => 0]
            );

            $stockAnterior = $invOrigen->stock_base;
            $invOrigen->decrement('stock_base', $detalle->cantidad_base);
            $stockPosterior = $invOrigen->stock_base;

            // Registrar salida en Kardex
            Kardex::create([
                'almacen_id' => $guia->almacen_origen_id,
                'variacion_id' => $detalle->variacion_id,
                'tipo_transaccion' => 'Salida',
                'concepto' => $guia->tipo_movimiento === 'Transferencia' ? 'Transferencia - Salida' : $guia->motivo,
                'cantidad' => $detalle->cantidad_base,
                'stock_anterior' => $stockAnterior,
                'stock_posterior' => $stockPosterior,
                'costo_unitario' => $detalle->costo_unitario,
                'costo_total' => $detalle->costo_total,
                'valor_total_almacen' => $stockPosterior * ($detalle->costo_unitario ?: 0),
                'origen_documento_id' => $guia->id,
                'origen_documento_type' => GuiaInventario::class,
                'creado_por_usuario_id' => Auth::id(),
            ]);
        }

        // 2. Entrada física (Destino)
        if ($guia->almacen_destino_id) {
            $invDestino = Inventario::firstOrCreate(
                ['almacen_id' => $guia->almacen_destino_id, 'variacion_id' => $detalle->variacion_id],
                ['stock_base' => 0, 'stock_minimo' => 0]
            );

            $stockAnterior = $invDestino->stock_base;
            $invDestino->increment('stock_base', $detalle->cantidad_base);
            $stockPosterior = $invDestino->stock_base;

            // Registrar entrada en Kardex
            Kardex::create([
                'almacen_id' => $guia->almacen_destino_id,
                'variacion_id' => $detalle->variacion_id,
                'tipo_transaccion' => 'Entrada',
                'concepto' => $guia->tipo_movimiento === 'Transferencia' ? 'Transferencia - Entrada' : $guia->motivo,
                'cantidad' => $detalle->cantidad_base,
                'stock_anterior' => $stockAnterior,
                'stock_posterior' => $stockPosterior,
                'costo_unitario' => $detalle->costo_unitario,
                'costo_total' => $detalle->costo_total,
                'valor_total_almacen' => $stockPosterior * ($detalle->costo_unitario ?: 0),
                'origen_documento_id' => $guia->id,
                'origen_documento_type' => GuiaInventario::class,
                'creado_por_usuario_id' => Auth::id(),
            ]);
        }
    }

    /**
     * Computed properties for the selectors.
     */
    #[Computed]
    public function almacenes()
    {
        return Almacen::where('activo', true)->get();
    }

    #[Computed]
    public function proveedores()
    {
        return Proveedor::where('activo', true)->get();
    }

    #[Computed]
    public function productos()
    {
        return Producto::where('activo', true)->get();
    }
}; ?>

<div class="space-y-6 max-w-5xl mx-auto">
    <div>
        <flux:heading size="xl">{{ __('Registrar Guía de Inventario') }}</flux:heading>
        <flux:subheading>{{ __('Crea un nuevo movimiento de entrada, salida o traslado entre almacenes.') }}</flux:subheading>
    </div>

    <form wire:submit.prevent="guardar('Procesado')" class="space-y-6">
        <!-- Tarjeta de Datos Generales -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6">
            <flux:heading size="lg">{{ __('Datos Generales') }}</flux:heading>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Tipo de Movimiento -->
                <flux:select wire:model.live="tipo_movimiento" :label="__('Tipo de Movimiento')">
                    <flux:select.option value="Entrada">{{ __('Entrada (Ingreso)') }}</flux:select.option>
                    <flux:select.option value="Salida">{{ __('Salida (Retiro)') }}</flux:select.option>
                    <flux:select.option value="Transferencia">{{ __('Transferencia (Traslado)') }}</flux:select.option>
                </flux:select>

                <!-- Fecha Movimiento -->
                <flux:input wire:model="fecha_movimiento" type="date" :label="__('Fecha del Movimiento')" />

                <!-- Motivo -->
                <flux:input wire:model="motivo" type="text" :label="__('Motivo / Concepto')" placeholder="Ej. Compra, Ajuste, Merma..." />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Serie -->
                <flux:input wire:model="serie" type="text" :label="__('Serie')" placeholder="Ej. GE01" />

                <!-- Correlativo -->
                <flux:input wire:model="correlativo" type="number" :label="__('Correlativo')" />

                <!-- Proveedor (Solo si es Entrada) -->
                @if($tipo_movimiento === 'Entrada')
                    <flux:select wire:model="proveedor_id" :label="__('Proveedor')" placeholder="Seleccionar Proveedor...">
                        @foreach($this->proveedores as $prov)
                            <flux:select.option value="{{ $prov->id }}">{{ $prov->razon_social }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Almacén Origen (Si es Salida o Transferencia) -->
                @if($tipo_movimiento !== 'Entrada')
                    <flux:select wire:model.live="almacen_origen_id" :label="__('Almacén Origen')" placeholder="Seleccionar origen...">
                        @foreach($this->almacenes as $alm)
                            <flux:select.option value="{{ $alm->id }}">{{ $alm->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <!-- Almacén Destino (Si es Entrada o Transferencia) -->
                @if($tipo_movimiento !== 'Salida')
                    <flux:select wire:model.live="almacen_destino_id" :label="__('Almacén Destino')" placeholder="Seleccionar destino...">
                        @foreach($this->almacenes as $alm)
                            <flux:select.option value="{{ $alm->id }}">{{ $alm->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </div>
        </div>

        <!-- Tarjeta de Detalle de Productos (Estilo Tailwind para Tabla) -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3">
                <flux:heading size="lg">{{ __('Detalles de Mercadería') }}</flux:heading>
                
                <flux:button variant="ghost" size="sm" icon="plus" wire:click.prevent="addDetalle">
                    {{ __('Añadir Fila') }}
                </flux:button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium">
                            <th class="pb-3 w-1/3">{{ __('Producto') }}</th>
                            <th class="pb-3 w-1/4">{{ __('Variación (Color/Talla)') }}</th>
                            <th class="pb-3 w-1/5">{{ __('Empaque/Presentación') }}</th>
                            <th class="pb-3 w-16 text-center">{{ __('Cant.') }}</th>
                            <th class="pb-3 w-20 text-center">{{ __('Equiv. Base') }}</th>
                            <th class="pb-3 w-24 text-right">{{ __('Costo Unit.') }}</th>
                            <th class="pb-3 w-24 text-right">{{ __('Costo Total') }}</th>
                            <th class="pb-3 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach($detalles as $index => $detalle)
                            <tr class="align-top py-2">
                                <td class="py-3 pr-2">
                                    <flux:select wire:model.live="detalles.{{ $index }}.producto_id" placeholder="Producto...">
                                        @foreach($this->productos as $p)
                                            <flux:select.option value="{{ $p->id }}">{{ $p->nombre }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </td>

                                <td class="py-3 pr-2">
                                    <flux:select wire:model.live="detalles.{{ $index }}.variacion_id" placeholder="Variación...">
                                        @foreach($detalle['variaciones'] as $var)
                                            <flux:select.option value="{{ $var['id'] }}">{{ $var['descripcion'] }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </td>

                                <td class="py-3 pr-2">
                                    <flux:select wire:model.live="detalles.{{ $index }}.unidad_medida_id" placeholder="Empaque...">
                                        @foreach($detalle['empaques'] as $emp)
                                            <flux:select.option value="{{ $emp['unidad_medida_id'] }}">{{ $emp['nombre'] }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </td>

                                <td class="py-3 pr-2">
                                    <flux:input wire:model.live="detalles.{{ $index }}.cantidad" type="number" min="1" class="text-center" />
                                </td>

                                <td class="py-3 pr-2 text-center align-middle font-semibold text-zinc-700 dark:text-zinc-300">
                                    {{ $detalle['cantidad_base'] }}
                                </td>

                                <td class="py-3 pr-2">
                                    <flux:input wire:model.live="detalles.{{ $index }}.costo_unitario" type="number" step="0.01" class="text-right" />
                                </td>

                                <td class="py-3 pr-2 text-right align-middle font-semibold text-zinc-900 dark:text-white">
                                    S/ {{ number_format($detalle['costo_total'], 2) }}
                                </td>

                                <td class="py-3 text-right align-middle">
                                    <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="removeDetalle({{ $index }})" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="flex items-center justify-end gap-4">
            <flux:button variant="ghost" :href="route('admin.guias.index')" wire:navigate>
                {{ __('Cancelar') }}
            </flux:button>
            
            <flux:button variant="ghost" wire:click.prevent="guardar('Borrador')">
                {{ __('Guardar Borrador') }}
            </flux:button>
            
            <flux:button variant="primary" type="submit">
                {{ __('Procesar Guía') }}
            </flux:button>
        </div>
    </form>
</div>
