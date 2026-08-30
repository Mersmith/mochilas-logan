<?php

use App\Models\Almacen;
use App\Models\GuiaInventario;
use App\Models\GuiaInventarioDetalle;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\ProductoEmpaque;
use App\Models\Proveedor;
use App\Models\Sede;
use App\Models\Serie;
use App\Models\TipoDocumento;
use App\Models\UnidadMedida;
use App\Models\Variacion;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Guía de Inventario')] class extends Component {
    public GuiaInventario $guia;
    public string $tipo_movimiento;
    public ?int $proveedor_id = null;
    public ?int $sede_origen_id = null;
    public ?int $almacen_origen_id = null;
    public ?int $sede_destino_id = null;
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

        $this->guia = $guia->load('detalles.variacion.producto', 'detalles.unidadMedida', 'almacenOrigen', 'almacenDestino');
        $this->tipo_movimiento = $guia->tipo_movimiento;
        $this->proveedor_id = $guia->proveedor_id;
        $this->almacen_origen_id = $guia->almacen_origen_id;
        $this->sede_origen_id = $guia->sede_origen_id ?? $guia->almacenOrigen?->sede_id;
        $this->almacen_destino_id = $guia->almacen_destino_id;
        $this->sede_destino_id = $guia->sede_destino_id ?? $guia->almacenDestino?->sede_id;
        $this->tipo_documento_id = $guia->tipo_documento_id;
        $this->serie = $guia->serie;
        $this->correlativo = (string) $guia->correlativo;
        $this->fecha_movimiento = $guia->fecha_movimiento->format('Y-m-d');
        $this->motivo = $guia->motivo ?? '';
    }

    #[Computed]
    public function proveedores()
    {
        return Proveedor::where('activo', true)->orderBy('razon_social')->get();
    }

    #[Computed]
    public function sedes()
    {
        return Sede::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function almacenesOrigen()
    {
        if (! $this->sede_origen_id) {
            return collect();
        }

        return Almacen::where('sede_id', $this->sede_origen_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    #[Computed]
    public function almacenesDestino()
    {
        if (! $this->sede_destino_id) {
            return collect();
        }

        return Almacen::where('sede_id', $this->sede_destino_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    #[Computed]
    public function tiposDocumento()
    {
        return TipoDocumento::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function variaciones()
    {
        return Variacion::with('producto')->where('activo', true)->get()->map(function ($v) {
            return [
                'id' => $v->id,
                'nombre' => $v->producto->nombre . ' - ' . $v->sku,
            ];
        });
    }

    public function updatedDetalleVariacionId()
    {
        $this->detalle_unidad_medida_id = null;
    }

    #[Computed]
    public function unidadesMedida()
    {
        if ($this->detalle_variacion_id) {
            $variacion = Variacion::with('producto.empaques.unidadMedida')->find($this->detalle_variacion_id);

            // Siempre incluimos la unidad base por defecto (ej. UNIDAD / U / UND / NIU)
            $unidadBase = UnidadMedida::where('nombre', 'like', '%UNIDAD%')
                ->orWhereIn('abreviacion', ['UND', 'U', 'NIU'])
                ->first();

            $unidades = collect($unidadBase ? [$unidadBase] : []);

            if ($variacion && $variacion->producto && $variacion->producto->empaques->count() > 0) {
                $empaques = $variacion->producto->empaques->map(fn ($e) => $e->unidadMedida)->filter();
                $unidades = $unidades->merge($empaques);
            }

            return $unidades->isNotEmpty() ? $unidades->unique('id') : UnidadMedida::where('activo', true)->get();
        }

        return UnidadMedida::where('activo', true)->get();
    }

    public function updatedTipoMovimiento()
    {
        $this->reset(['proveedor_id', 'sede_origen_id', 'almacen_origen_id', 'sede_destino_id', 'almacen_destino_id']);

        $primeraSede = Sede::where('activo', true)->first();
        if ($primeraSede) {
            if ($this->tipo_movimiento === 'Entrada') {
                $this->sede_destino_id = $primeraSede->id;
                $this->almacen_destino_id = Almacen::where('sede_id', $primeraSede->id)->where('activo', true)->first()?->id;
            } else {
                $this->sede_origen_id = $primeraSede->id;
                $this->almacen_origen_id = Almacen::where('sede_id', $primeraSede->id)->where('activo', true)->first()?->id;
            }
        }

        $this->actualizarSerieCorrelativo();
    }

    public function updatedSedeOrigenId(): void
    {
        $this->almacen_origen_id = $this->almacenesOrigen->first()?->id;
        $this->actualizarSerieCorrelativo();
    }

    public function updatedSedeDestinoId(): void
    {
        $this->almacen_destino_id = $this->almacenesDestino->first()?->id;
        $this->actualizarSerieCorrelativo();
    }

    public function updatedTipoDocumentoId(): void
    {
        $this->actualizarSerieCorrelativo();
    }

    public function actualizarSerieCorrelativo(): void
    {
        $sedeId = $this->tipo_movimiento === 'Entrada'
            ? $this->sede_destino_id
            : $this->sede_origen_id;

        if (! $sedeId || ! $this->tipo_documento_id) {
            return;
        }

        $serieConfig = Serie::where('sede_id', $sedeId)
            ->where('tipo_documento_id', $this->tipo_documento_id)
            ->where('activo', true)
            ->first();

        if ($serieConfig) {
            $this->serie = $serieConfig->serie;
            $this->correlativo = (string) $serieConfig->correlativo;
        } else {
            $this->serie = '';
            $this->correlativo = '';
        }
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

        // Validar que no se agregue la misma variación con la misma unidad de medida
        if ($this->guia->detalles()
            ->where('variacion_id', $this->detalle_variacion_id)
            ->where('unidad_medida_id', $this->detalle_unidad_medida_id)
            ->exists()) {
            Flux::toast(variant: 'danger', text: 'Esta variación con esta unidad de medida ya ha sido agregada a la guía.');
            return;
        }

        // Obtener factor de conversión
        $variacion = Variacion::find($this->detalle_variacion_id);
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

        if (is_numeric($this->correlativo)) {
            $this->correlativo = (string) (int) $this->correlativo;
        }

        $rules = [
            'tipo_movimiento' => 'required|in:Entrada,Salida,Transferencia',
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'serie' => 'required|string|max:10',
            'correlativo' => 'required|integer|min:1',
            'fecha_movimiento' => 'required|date',
            'motivo' => 'nullable|string',
        ];

        if ($this->tipo_movimiento === 'Entrada') {
            $rules['proveedor_id'] = 'required|exists:proveedores,id';
            $rules['sede_destino_id'] = 'required|exists:sedes,id';
            $rules['almacen_destino_id'] = 'required|exists:almacens,id';
            $this->sede_origen_id = null;
            $this->almacen_origen_id = null;
        } elseif ($this->tipo_movimiento === 'Salida') {
            $rules['sede_origen_id'] = 'required|exists:sedes,id';
            $rules['almacen_origen_id'] = 'required|exists:almacens,id';
            $this->proveedor_id = null;
            $this->sede_destino_id = null;
            $this->almacen_destino_id = null;
        } elseif ($this->tipo_movimiento === 'Transferencia') {
            $rules['sede_origen_id'] = 'required|exists:sedes,id';
            $rules['almacen_origen_id'] = 'required|exists:almacens,id';
            $rules['sede_destino_id'] = 'required|exists:sedes,id';
            $rules['almacen_destino_id'] = 'required|exists:almacens,id|different:almacen_origen_id';
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
            'sede_origen_id' => $this->sede_origen_id,
            'almacen_origen_id' => $this->almacen_origen_id,
            'sede_destino_id' => $this->sede_destino_id,
            'almacen_destino_id' => $this->almacen_destino_id,
            'tipo_documento_id' => $this->tipo_documento_id,
            'serie' => $this->serie,
            'correlativo' => (int) $this->correlativo,
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

                    if (! $inv || $inv->stock_base < $detalle->cantidad_base) {
                        throw new \Exception('Stock insuficiente para el producto ' . $detalle->variacion->sku . ' en el almacén de origen.');
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

                    if (! $invOrigen || $invOrigen->stock_base < $detalle->cantidad_base) {
                        throw new \Exception('Stock insuficiente para el producto ' . $detalle->variacion->sku . ' en el almacén de origen.');
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

            // Incrementar correlativo en Serie correspondiente a la Sede emisora
            $sedeEmisoraId = $this->tipo_movimiento === 'Entrada' ? $this->sede_destino_id : $this->sede_origen_id;
            if ($sedeEmisoraId) {
                $serieModel = Serie::where('sede_id', $sedeEmisoraId)
                    ->where('tipo_documento_id', $this->guia->tipo_documento_id)
                    ->where('serie', $this->guia->serie)
                    ->first();

                if ($serieModel && (int) $this->guia->correlativo >= $serieModel->correlativo) {
                    $serieModel->update(['correlativo' => (int) $this->guia->correlativo + 1]);
                }
            }

            $this->guia->update(['estado' => 'Procesado']);
            DB::commit();

            Flux::toast(variant: 'success', text: 'Guía procesada correctamente. Se ha actualizado el inventario y el correlativo de la serie.');

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
            <form wire:submit.prevent="guardar" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-5">
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
                        <flux:label>{{ __('Sede de Destino') }}</flux:label>
                        <flux:select wire:model.live="sede_destino_id">
                            <option value="">{{ __('Seleccione Sede...') }}</option>
                            @foreach($this->sedes as $sede)
                                <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="sede_destino_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Almacén de Destino') }}</flux:label>
                        <flux:select wire:model="almacen_destino_id" :disabled="!$sede_destino_id">
                            <option value="">{{ __('Seleccione Almacén...') }}</option>
                            @foreach($this->almacenesDestino as $almacen)
                                <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="almacen_destino_id" />
                    </flux:field>
                @elseif($tipo_movimiento === 'Salida')
                    <flux:field>
                        <flux:label>{{ __('Sede de Origen') }}</flux:label>
                        <flux:select wire:model.live="sede_origen_id">
                            <option value="">{{ __('Seleccione Sede...') }}</option>
                            @foreach($this->sedes as $sede)
                                <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="sede_origen_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Almacén de Origen') }}</flux:label>
                        <flux:select wire:model="almacen_origen_id" :disabled="!$sede_origen_id">
                            <option value="">{{ __('Seleccione Almacén...') }}</option>
                            @foreach($this->almacenesOrigen as $almacen)
                                <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="almacen_origen_id" />
                    </flux:field>
                @elseif($tipo_movimiento === 'Transferencia')
                    <div class="p-3 bg-zinc-50/50 dark:bg-zinc-800/30 rounded-lg space-y-3">
                        <span class="text-xs font-semibold uppercase text-zinc-500">{{ __('Origen (Emisor)') }}</span>
                        <flux:field>
                            <flux:label>{{ __('Sede de Origen') }}</flux:label>
                            <flux:select wire:model.live="sede_origen_id">
                                <option value="">{{ __('Seleccione Sede...') }}</option>
                                @foreach($this->sedes as $sede)
                                    <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="sede_origen_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Almacén de Origen') }}</flux:label>
                            <flux:select wire:model="almacen_origen_id" :disabled="!$sede_origen_id">
                                <option value="">{{ __('Seleccione Almacén...') }}</option>
                                @foreach($this->almacenesOrigen as $almacen)
                                    <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="almacen_origen_id" />
                        </flux:field>
                    </div>

                    <div class="p-3 bg-zinc-50/50 dark:bg-zinc-800/30 rounded-lg space-y-3">
                        <span class="text-xs font-semibold uppercase text-zinc-500">{{ __('Destino (Receptor)') }}</span>
                        <flux:field>
                            <flux:label>{{ __('Sede de Destino') }}</flux:label>
                            <flux:select wire:model.live="sede_destino_id">
                                <option value="">{{ __('Seleccione Sede...') }}</option>
                                @foreach($this->sedes as $sede)
                                    <option value="{{ $sede->id }}">{{ $sede->nombre }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="sede_destino_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Almacén de Destino') }}</flux:label>
                            <flux:select wire:model="almacen_destino_id" :disabled="!$sede_destino_id">
                                <option value="">{{ __('Seleccione Almacén...') }}</option>
                                @foreach($this->almacenesDestino as $almacen)
                                    <option value="{{ $almacen->id }}">{{ $almacen->nombre }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="almacen_destino_id" />
                        </flux:field>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    <flux:field class="col-span-2">
                        <flux:label>{{ __('Documento') }}</flux:label>
                        <flux:select wire:model.live="tipo_documento_id">
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

                    @if(empty($serie) && $tipo_documento_id && (($tipo_movimiento === 'Entrada' && $sede_destino_id) || ($tipo_movimiento !== 'Entrada' && $sede_origen_id)))
                        <div class="col-span-2">
                            <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">
                                ⚠️ {{ __('La sede seleccionada no tiene una serie activa registrada para este tipo de documento.') }}
                            </span>
                        </div>
                    @endif
                </div>

                <flux:field>
                    <flux:label>{{ __('Motivo') }}</flux:label>
                    <flux:textarea wire:model="motivo" rows="2" placeholder="Opcional..." />
                </flux:field>

                <div class="pt-2">
                    <flux:button type="submit" variant="primary" class="w-full">
                        {{ __('Guardar Encabezado') }}
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- COLUMNA DERECHA: DETALLES DE PRODUCTOS --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Formulario para Agregar Producto --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-4">
                <flux:heading size="lg">{{ __('Agregar Productos a la Guía') }}</flux:heading>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __('Producto / Variación') }}</flux:label>
                        <flux:select wire:model.live="detalle_variacion_id">
                            <option value="">{{ __('Seleccione Producto / SKU...') }}</option>
                            @foreach($this->variaciones as $var)
                                <option value="{{ $var['id'] }}">{{ $var['nombre'] }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="detalle_variacion_id" />
                    </flux:field>

                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __('Unidad de Medida / Presentación') }}</flux:label>
                        <flux:select wire:model="detalle_unidad_medida_id">
                            <option value="">{{ __('Seleccione Unidad...') }}</option>
                            @foreach($this->unidadesMedida as $um)
                                <option value="{{ $um->id }}">{{ $um->nombre }} ({{ $um->abreviacion }})</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="detalle_unidad_medida_id" />
                    </flux:field>

                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __('Cantidad') }}</flux:label>
                        <flux:input type="number" wire:model="detalle_cantidad" placeholder="Ej. 10" min="1" />
                        <flux:error name="detalle_cantidad" />
                    </flux:field>

                    @if($tipo_movimiento === 'Entrada')
                        <flux:field class="md:col-span-2">
                            <flux:label>{{ __('Costo Unitario') }}</flux:label>
                            <flux:input type="number" step="0.01" wire:model="detalle_costo_unitario" placeholder="Ej. 25.50" min="0.01" />
                            <flux:error name="detalle_costo_unitario" />
                        </flux:field>
                    @endif

                    <div class="{{ $tipo_movimiento === 'Entrada' ? 'md:col-span-4' : 'md:col-span-2' }} flex justify-end">
                        <flux:button type="button" wire:click="addDetalle" variant="primary" icon="plus" class="w-full md:w-auto">
                            {{ __('Agregar a la Lista') }}
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Tabla de Productos Agregados --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                    <flux:heading size="lg">{{ __('Items de la Guía') }} ({{ $guia->detalles->count() }})</flux:heading>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                                <th class="p-3">{{ __('Producto / SKU') }}</th>
                                <th class="p-3">{{ __('Presentación') }}</th>
                                <th class="p-3 text-right">{{ __('Cant.') }}</th>
                                <th class="p-3 text-right">{{ __('Cant. Base') }}</th>
                                @if($tipo_movimiento === 'Entrada')
                                    <th class="p-3 text-right">{{ __('Costo Unit.') }}</th>
                                    <th class="p-3 text-right">{{ __('Costo Total') }}</th>
                                @endif
                                <th class="p-3 text-center">{{ __('Acción') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse($guia->detalles as $det)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="p-3">
                                        <div class="font-medium text-zinc-900 dark:text-white">
                                            {{ $det->variacion->producto->nombre ?? 'N/A' }}
                                        </div>
                                        <div class="text-xs text-zinc-500 font-mono">{{ $det->variacion->sku ?? '' }}</div>
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex items-center gap-1.5 py-0.5 px-2 rounded text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">
                                            {{ $det->unidadMedida->nombre ?? '' }} (x{{ $det->factor_conversion }})
                                        </span>
                                    </td>
                                    <td class="p-3 text-right font-medium">{{ $det->cantidad }}</td>
                                    <td class="p-3 text-right text-zinc-500 font-medium">{{ $det->cantidad_base }} UND</td>
                                    @if($tipo_movimiento === 'Entrada')
                                        <td class="p-3 text-right">S/ {{ number_format($det->costo_unitario, 2) }}</td>
                                        <td class="p-3 text-right font-semibold">S/ {{ number_format($det->costo_total, 2) }}</td>
                                    @endif
                                    <td class="p-3 text-center">
                                        <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                            wire:click="removeDetalle({{ $det->id }})" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-8 text-zinc-400">
                                        {{ __('No has agregado ningún producto a esta guía todavía.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Botón de Acción Principal para Procesar --}}
            <div class="flex justify-end gap-3">
                <flux:button variant="ghost" href="{{ route('admin.guias.index') }}" wire:navigate>
                    {{ __('Guardar Borrador y Salir') }}
                </flux:button>
                <flux:button variant="primary" icon="check" wire:click="confirmarProcesar" :disabled="$guia->detalles->count() === 0">
                    {{ __('Confirmar y Procesar Movimiento') }}
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Modal de Confirmación para Procesar --}}
    <x-modal-eliminar name="modal-procesar" title="¿Procesar Guía de Inventario?"
        description="Esta acción actualizará el stock en los almacenes seleccionados y registrará el movimiento formal en el Kardex. La serie del documento avanzará automáticamente."
        action="procesar"
        buttonText="Sí, Procesar Guía" />
</div>
