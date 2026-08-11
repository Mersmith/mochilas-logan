<?php

use App\Models\GuiaInventario;
use App\Models\GuiaInventarioDetalle;
use App\Models\Almacen;
use App\Models\Proveedor;
use App\Models\TipoDocumento;
use App\Models\Variacion;
use App\Models\UnidadMedida;
use App\Models\ProductoEmpaque;
use App\Models\Inventario;
use App\Models\Kardex;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

new #[Title('Editar Guía de Inventario')] class extends Component {
    public GuiaInventario $guia;
    public string $tipo_movimiento;
    public ?int $proveedor_id = null;
    public ?int $almacen_origen_id = null;
    public ?int $almacen_destino_id = null;
    public ?int $tipo_documento_id = null;
    public string $serie = '';
    public string $correlativo = '';
    public string $fecha_movimiento = '';
    public string $motivo = '';

    // Formulario de Detalles
    public ?int $detalle_variacion_id = null;
    public ?int $detalle_unidad_medida_id = null;
    public string $detalle_cantidad = '';
    public string $detalle_costo_unitario = '';

    public function mount(GuiaInventario $guia)
    {
        if ($guia->estado !== 'Borrador') {
            Flux::toast(variant: 'warning', text: 'Esta guía no puede ser editada porque no está en estado Borrador.');
            return redirect()->route('admin.guias.show', $guia->id);
        }

        $this->guia = $guia->load('detalles.variacion.producto', 'detalles.unidadMedida');
        $this->tipo_movimiento = $guia->tipo_movimiento;
        $this->proveedor_id = $guia->proveedor_id;
        $this->almacen_origen_id = $guia->almacen_origen_id;
        $this->almacen_destino_id = $guia->almacen_destino_id;
        $this->tipo_documento_id = $guia->tipo_documento_id;
        $this->serie = $guia->serie;
        $this->correlativo = $guia->correlativo;
        $this->fecha_movimiento = $guia->fecha_movimiento->format('Y-m-d');
        $this->motivo = $guia->motivo ?? '';
    }

    public function getProveedoresProperty()
    {
        return Proveedor::where('activo', true)->orderBy('razon_social')->get();
    }

    public function getAlmacenesProperty()
    {
        return Almacen::with('sede')->where('activo', true)->get()->map(function ($a) {
            return [
                'id' => $a->id,
                'nombre' => $a->nombre . ' (' . $a->sede->nombre . ')'
            ];
        });
    }

    public function getTiposDocumentoProperty()
    {
        return TipoDocumento::where('activo', true)->get();
    }

    public function getVariacionesProperty()
    {
        return Variacion::with('producto')->where('activo', true)->get()->map(function($v) {
            return [
                'id' => $v->id,
                'nombre' => $v->producto->nombre . ' - ' . $v->sku
            ];
        });
    }

    public function getUnidadesMedidaProperty()
    {
        return UnidadMedida::where('activo', true)->get();
    }

    public function updatedTipoMovimiento()
    {
        $this->reset(['proveedor_id', 'almacen_origen_id', 'almacen_destino_id']);
    }

    public function addDetalle()
    {
        $rules = [
            'detalle_variacion_id' => 'required|exists:variacions,id',
            'detalle_unidad_medida_id' => 'required|exists:unidades_medida,id',
            'detalle_cantidad' => 'required|integer|min:1',
        ];

        if ($this->tipo_movimiento === 'Entrada') {
            $rules['detalle_costo_unitario'] = 'required|numeric|min:0.01';
        }

        $this->validate($rules);

        // Validar que no se agregue la misma variación dos veces
        if ($this->guia->detalles()->where('variacion_id', $this->detalle_variacion_id)->exists()) {
            Flux::toast(variant: 'danger', text: 'Esta variación ya ha sido agregada a la guía.');
            return;
        }

        // Obtener factor de conversión
        $variacion = Variacion::find($this->detalle_variacion_id);
        // Si hay una configuración de empaque para este producto y esta UM, la usamos. Si no, asumimos factor 1 (Unidad)
        $empaque = ProductoEmpaque::where('producto_id', $variacion->producto_id)
            ->where('unidad_medida_id', $this->detalle_unidad_medida_id)
            ->first();

        $factor_conversion = $empaque ? $empaque->factor_conversion : 1;
        $cantidad = (int) $this->detalle_cantidad;
        $cantidad_base = $cantidad * $factor_conversion;
        
        $costo_unitario = $this->tipo_movimiento === 'Entrada' ? (float) $this->detalle_costo_unitario : 0;
        $costo_total = $costo_unitario > 0 ? $cantidad * $costo_unitario : 0;

        GuiaInventarioDetalle::create([
            'guia_inventario_id' => $this->guia->id,
            'variacion_id' => $this->detalle_variacion_id,
            'unidad_medida_id' => $this->detalle_unidad_medida_id,
            'cantidad' => $cantidad,
            'factor_conversion' => $factor_conversion,
            'cantidad_base' => $cantidad_base,
            'costo_unitario' => $costo_unitario > 0 ? $costo_unitario : null,
            'costo_total' => $costo_total > 0 ? $costo_total : null,
        ]);

        $this->guia->load('detalles.variacion.producto', 'detalles.unidadMedida');
        $this->reset(['detalle_variacion_id', 'detalle_unidad_medida_id', 'detalle_cantidad', 'detalle_costo_unitario']);
        Flux::toast(variant: 'success', text: 'Producto agregado.');
    }

    public function removeDetalle($id)
    {
        GuiaInventarioDetalle::where('id', $id)->where('guia_inventario_id', $this->guia->id)->delete();
        $this->guia->load('detalles.variacion.producto', 'detalles.unidadMedida');
        Flux::toast(variant: 'success', text: 'Producto eliminado.');
    }

    public function guardar()
    {
        if (! auth()->user()->can('guias.crear')) {
            abort(403);
        }

        $rules = [
            'tipo_movimiento' => 'required|in:Entrada,Salida,Transferencia',
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'serie' => 'required|string|max:10',
            'correlativo' => 'required|integer',
            'fecha_movimiento' => 'required|date',
            'motivo' => 'nullable|string',
        ];

        if ($this->tipo_movimiento === 'Entrada') {
            $rules['proveedor_id'] = 'required|exists:proveedores,id';
            $rules['almacen_destino_id'] = 'required|exists:almacens,id';
            $this->almacen_origen_id = null;
        } elseif ($this->tipo_movimiento === 'Salida') {
            $rules['almacen_origen_id'] = 'required|exists:almacens,id';
            $this->proveedor_id = null;
            $this->almacen_destino_id = null;
        } elseif ($this->tipo_movimiento === 'Transferencia') {
            $rules['almacen_origen_id'] = 'required|exists:almacens,id|different:almacen_destino_id';
            $rules['almacen_destino_id'] = 'required|exists:almacens,id';
            $this->proveedor_id = null;
        }

        $this->validate($rules);

        $exists = GuiaInventario::where('tipo_documento_id', $this->tipo_documento_id)
            ->where('serie', $this->serie)
            ->where('correlativo', $this->correlativo)
            ->where('id', '!=', $this->guia->id)
            ->exists();

        if ($exists) {
            $this->addError('correlativo', 'Esta serie y correlativo ya existe para el documento seleccionado.');
            return;
        }

        $this->guia->update([
            'tipo_movimiento' => $this->tipo_movimiento,
            'proveedor_id' => $this->proveedor_id,
            'almacen_origen_id' => $this->almacen_origen_id,
            'almacen_destino_id' => $this->almacen_destino_id,
            'tipo_documento_id' => $this->tipo_documento_id,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'fecha_movimiento' => $this->fecha_movimiento,
            'motivo' => $this->motivo,
        ]);

        Flux::toast(variant: 'success', text: 'Encabezado de la guía actualizado correctamente.');
    }

    public function confirmarProcesar()
    {
        $this->guardar(); // Asegurar que el encabezado está guardado
        
        if ($this->guia->detalles()->count() === 0) {
            Flux::toast(variant: 'danger', text: 'No se puede procesar una guía sin productos.');
            return;
        }

        $this->modal('modal-procesar')->show();
    }

    public function procesar()
    {
        $this->modal('modal-procesar')->close();

        try {
            DB::beginTransaction();

            foreach ($this->guia->detalles as $detalle) {
                if ($this->tipo_movimiento === 'Entrada') {
                    $inv = Inventario::firstOrCreate(
                        ['almacen_id' => $this->almacen_destino_id, 'variacion_id' => $detalle->variacion_id],
                        ['stock_base' => 0, 'stock_minimo' => 0]
                    );
                    $stockAnterior = $inv->stock_base;
                    $inv->stock_base += $detalle->cantidad_base;
                    $inv->save();

                    Kardex::create([
                        'almacen_id' => $this->almacen_destino_id,
                        'variacion_id' => $detalle->variacion_id,
                        'tipo_transaccion' => 'Entrada',
                        'concepto' => $this->motivo ?: 'Guía de Entrada',
                        'cantidad' => $detalle->cantidad_base,
                        'stock_anterior' => $stockAnterior,
                        'stock_posterior' => $inv->stock_base,
                        'costo_unitario' => $detalle->costo_unitario,
                        'costo_total' => $detalle->costo_total,
                        'origen_documento_id' => $this->guia->id,
                        'origen_documento_type' => GuiaInventario::class,
                        'creado_por_usuario_id' => auth()->id(),
                    ]);

                } elseif ($this->tipo_movimiento === 'Salida') {
                    $inv = Inventario::where('almacen_id', $this->almacen_origen_id)
                        ->where('variacion_id', $detalle->variacion_id)
                        ->first();

                    if (!$inv || $inv->stock_base < $detalle->cantidad_base) {
                        throw new \Exception("Stock insuficiente para el producto " . $detalle->variacion->sku . " en el almacén de origen.");
                    }

                    $stockAnterior = $inv->stock_base;
                    $inv->stock_base -= $detalle->cantidad_base;
                    $inv->save();

                    Kardex::create([
                        'almacen_id' => $this->almacen_origen_id,
                        'variacion_id' => $detalle->variacion_id,
                        'tipo_transaccion' => 'Salida',
                        'concepto' => $this->motivo ?: 'Guía de Salida',
                        'cantidad' => $detalle->cantidad_base,
                        'stock_anterior' => $stockAnterior,
                        'stock_posterior' => $inv->stock_base,
                        'origen_documento_id' => $this->guia->id,
                        'origen_documento_type' => GuiaInventario::class,
                        'creado_por_usuario_id' => auth()->id(),
                    ]);

                } elseif ($this->tipo_movimiento === 'Transferencia') {
                    // Restar de Origen
                    $invOrigen = Inventario::where('almacen_id', $this->almacen_origen_id)
                        ->where('variacion_id', $detalle->variacion_id)
                        ->first();

                    if (!$invOrigen || $invOrigen->stock_base < $detalle->cantidad_base) {
                        throw new \Exception("Stock insuficiente para el producto " . $detalle->variacion->sku . " en el almacén de origen.");
                    }

                    $stockAnteriorOrigen = $invOrigen->stock_base;
                    $invOrigen->stock_base -= $detalle->cantidad_base;
                    $invOrigen->save();

                    Kardex::create([
                        'almacen_id' => $this->almacen_origen_id,
                        'variacion_id' => $detalle->variacion_id,
                        'tipo_transaccion' => 'Salida',
                        'concepto' => 'Transferencia - Salida',
                        'cantidad' => $detalle->cantidad_base,
                        'stock_anterior' => $stockAnteriorOrigen,
                        'stock_posterior' => $invOrigen->stock_base,
                        'origen_documento_id' => $this->guia->id,
                        'origen_documento_type' => GuiaInventario::class,
                        'creado_por_usuario_id' => auth()->id(),
                    ]);

                    // Sumar a Destino
                    $invDestino = Inventario::firstOrCreate(
                        ['almacen_id' => $this->almacen_destino_id, 'variacion_id' => $detalle->variacion_id],
                        ['stock_base' => 0, 'stock_minimo' => 0]
                    );
                    
                    $stockAnteriorDestino = $invDestino->stock_base;
                    $invDestino->stock_base += $detalle->cantidad_base;
                    $invDestino->save();

                    Kardex::create([
                        'almacen_id' => $this->almacen_destino_id,
                        'variacion_id' => $detalle->variacion_id,
                        'tipo_transaccion' => 'Entrada',
                        'concepto' => 'Transferencia - Ingreso',
                        'cantidad' => $detalle->cantidad_base,
                        'stock_anterior' => $stockAnteriorDestino,
                        'stock_posterior' => $invDestino->stock_base,
                        'origen_documento_id' => $this->guia->id,
                        'origen_documento_type' => GuiaInventario::class,
                        'creado_por_usuario_id' => auth()->id(),
                    ]);
                }
            }

            $this->guia->update(['estado' => 'Procesado']);
            DB::commit();

            Flux::toast(variant: 'success', text: 'Guía procesada correctamente. Se ha actualizado el inventario.');
            return redirect()->route('admin.guias.show', $this->guia->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }
}; ?>

<div class="space-y-6 max-w-6xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Borrador de Guía') }} #{{ $guia->id }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos del encabezado y los productos de la guía.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.guias.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- COLUMNA IZQUIERDA: ENCABEZADO --}}
        <div class="lg:col-span-1">
            <form class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-5">
                <flux:heading size="lg" class="mb-4">{{ __('Encabezado') }}</flux:heading>
                
                <flux:field>
                    <flux:label>{{ __('Tipo de Movimiento') }}</flux:label>
                    <flux:select wire:model.live="tipo_movimiento">
                        <option value="Entrada">{{ __('Entrada') }}</option>
                        <option value="Salida">{{ __('Salida') }}</option>
                        <option value="Transferencia">{{ __('Transferencia') }}</option>
                    </flux:select>
                    <flux:error name="tipo_movimiento" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fecha') }}</flux:label>
                    <flux:input type="date" wire:model="fecha_movimiento" required />
                    <flux:error name="fecha_movimiento" />
                </flux:field>

                @if($tipo_movimiento === 'Entrada')
                    <flux:field>
                        <flux:label>{{ __('Proveedor') }}</flux:label>
                        <flux:select wire:model="proveedor_id">
                            <option value="">{{ __('Seleccione Proveedor...') }}</option>
                            @foreach($this->proveedores as $prov)
                                <option value="{{ $prov->id }}">{{ $prov->razon_social }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="proveedor_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Destino') }}</flux:label>
                        <flux:select wire:model="almacen_destino_id">
                            <option value="">{{ __('Seleccione Almacén...') }}</option>
                            @foreach($this->almacenes as $almacen)
                                <option value="{{ $almacen['id'] }}">{{ $almacen['nombre'] }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="almacen_destino_id" />
                    </flux:field>
                @elseif($tipo_movimiento === 'Salida')
                    <flux:field>
                        <flux:label>{{ __('Origen') }}</flux:label>
                        <flux:select wire:model="almacen_origen_id">
                            <option value="">{{ __('Seleccione Almacén...') }}</option>
                            @foreach($this->almacenes as $almacen)
                                <option value="{{ $almacen['id'] }}">{{ $almacen['nombre'] }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="almacen_origen_id" />
                    </flux:field>
                @elseif($tipo_movimiento === 'Transferencia')
                    <flux:field>
                        <flux:label>{{ __('Origen') }}</flux:label>
                        <flux:select wire:model="almacen_origen_id">
                            <option value="">{{ __('Seleccione Origen...') }}</option>
                            @foreach($this->almacenes as $almacen)
                                <option value="{{ $almacen['id'] }}">{{ $almacen['nombre'] }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="almacen_origen_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Destino') }}</flux:label>
                        <flux:select wire:model="almacen_destino_id">
                            <option value="">{{ __('Seleccione Destino...') }}</option>
                            @foreach($this->almacenes as $almacen)
                                <option value="{{ $almacen['id'] }}">{{ $almacen['nombre'] }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="almacen_destino_id" />
                    </flux:field>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    <flux:field class="col-span-2">
                        <flux:label>{{ __('Documento') }}</flux:label>
                        <flux:select wire:model="tipo_documento_id">
                            <option value="">{{ __('Seleccione...') }}</option>
                            @foreach($this->tiposDocumento as $td)
                                <option value="{{ $td->id }}">{{ $td->nombre }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="tipo_documento_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Serie') }}</flux:label>
                        <flux:input wire:model="serie" placeholder="Ej. F001" required />
                        <flux:error name="serie" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Correlativo') }}</flux:label>
                        <flux:input type="number" wire:model="correlativo" placeholder="Ej. 123" required />
                        <flux:error name="correlativo" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Motivo') }}</flux:label>
                    <flux:textarea wire:model="motivo" rows="2" />
                    <flux:error name="motivo" />
                </flux:field>

                <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button type="button" class="w-full" wire:click="guardar" icon="check">{{ __('Guardar Encabezado') }}</flux:button>
                </div>
            </form>
        </div>
        
        {{-- COLUMNA DERECHA: DETALLES --}}
        <div class="lg:col-span-2 space-y-6">
            
            {{-- Formulario para Agregar Detalle --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
                <flux:heading size="lg" class="mb-4">{{ __('Agregar Producto') }}</flux:heading>
                
                <form wire:submit.prevent="addDetalle" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <flux:field class="md:col-span-6">
                            <flux:label>{{ __('Producto / Variación') }}</flux:label>
                            <flux:select wire:model="detalle_variacion_id" placeholder="Seleccione Variación...">
                                <option value="">{{ __('Seleccione...') }}</option>
                                @foreach($this->variaciones as $var)
                                    <option value="{{ $var['id'] }}">{{ $var['nombre'] }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="detalle_variacion_id" />
                        </flux:field>

                        <flux:field class="md:col-span-3">
                            <flux:label>{{ __('U. Medida') }}</flux:label>
                            <flux:select wire:model="detalle_unidad_medida_id">
                                <option value="">{{ __('Seleccione...') }}</option>
                                @foreach($this->unidadesMedida as $um)
                                    <option value="{{ $um->id }}">{{ $um->nombre }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="detalle_unidad_medida_id" />
                        </flux:field>

                        <flux:field class="md:col-span-3">
                            <flux:label>{{ __('Cantidad') }}</flux:label>
                            <flux:input type="number" wire:model="detalle_cantidad" min="1" required />
                            <flux:error name="detalle_cantidad" />
                        </flux:field>
                    </div>

                    <div class="flex items-end gap-4 justify-between">
                        <div class="w-1/3">
                            @if($tipo_movimiento === 'Entrada')
                                <flux:field>
                                    <flux:label>{{ __('Costo Unitario ($)') }}</flux:label>
                                    <flux:input type="number" step="0.01" wire:model="detalle_costo_unitario" required />
                                    <flux:error name="detalle_costo_unitario" />
                                </flux:field>
                            @endif
                        </div>
                        <flux:button variant="primary" type="submit" icon="plus">{{ __('Agregar') }}</flux:button>
                    </div>
                </form>
            </div>

            {{-- Tabla de Detalles --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center bg-zinc-50 dark:bg-zinc-800/40">
                    <flux:heading size="md">{{ __('Productos en la Guía') }}</flux:heading>
                    <span class="text-sm text-zinc-500 font-medium">{{ $guia->detalles->count() }} ítems</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                                <th class="p-3">{{ __('Producto') }}</th>
                                <th class="p-3 text-center">{{ __('Cant. ingresada') }}</th>
                                <th class="p-3 text-center">{{ __('UM Base (Unidades)') }}</th>
                                @if($tipo_movimiento === 'Entrada')
                                    <th class="p-3 text-right">{{ __('C. Unitario') }}</th>
                                    <th class="p-3 text-right">{{ __('Subtotal') }}</th>
                                @endif
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse($guia->detalles as $detalle)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="p-3">
                                        <div class="font-medium text-zinc-900 dark:text-white">
                                            {{ optional(optional($detalle->variacion)->producto)->nombre }}
                                        </div>
                                        <div class="text-xs text-zinc-500">
                                            SKU: {{ optional($detalle->variacion)->sku }}
                                        </div>
                                    </td>
                                    <td class="p-3 text-center font-medium">
                                        {{ $detalle->cantidad }} {{ optional($detalle->unidadMedida)->nombre }}
                                    </td>
                                    <td class="p-3 text-center text-zinc-600 dark:text-zinc-400 text-xs">
                                        {{ $detalle->cantidad_base }} 
                                        @if($detalle->factor_conversion > 1)
                                            <span class="text-zinc-400 italic">(Factor: x{{ $detalle->factor_conversion }})</span>
                                        @endif
                                    </td>
                                    @if($tipo_movimiento === 'Entrada')
                                        <td class="p-3 text-right text-zinc-600 dark:text-zinc-400">
                                            ${{ number_format($detalle->costo_unitario, 2) }}
                                        </td>
                                        <td class="p-3 text-right font-medium text-emerald-600 dark:text-emerald-400">
                                            ${{ number_format($detalle->costo_total, 2) }}
                                        </td>
                                    @endif
                                    <td class="p-3">
                                        <div class="flex justify-end">
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click="removeDetalle({{ $detalle->id }})" title="Eliminar" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $tipo_movimiento === 'Entrada' ? 6 : 4 }}" class="text-center py-12 text-zinc-500">
                                        {{ __('No hay productos agregados a esta guía.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($tipo_movimiento === 'Entrada' && $guia->detalles->count() > 0)
                            <tfoot>
                                <tr class="border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40">
                                    <td colspan="4" class="p-3 text-right font-semibold text-zinc-900 dark:text-white">
                                        {{ __('Total General:') }}
                                    </td>
                                    <td class="p-3 text-right font-bold text-emerald-600 dark:text-emerald-400 text-lg">
                                        ${{ number_format($guia->detalles->sum('costo_total'), 2) }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
            
            <div class="flex justify-end pt-4">
                <flux:button variant="primary" wire:click="confirmarProcesar" icon="play" class="w-full sm:w-auto">
                    {{ __('Procesar Guía y Actualizar Stock') }}
                </flux:button>
            </div>
            
        </div>
    </div>

    <x-modal-confirmar 
        name="modal-procesar"
        title="¿Procesar Guía?"
        description="Esta acción actualizará los inventarios y generará movimientos en el Kardex. Una guía procesada no se puede deshacer, solo anularse."
        action="procesar"
        buttonText="Procesar Guía"
        buttonVariant="primary"
    />
</div>
