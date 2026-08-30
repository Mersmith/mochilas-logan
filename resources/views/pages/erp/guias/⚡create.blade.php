<?php

use App\Models\Almacen;
use App\Models\GuiaInventario;
use App\Models\Proveedor;
use App\Models\Sede;
use App\Models\Serie;
use App\Models\TipoDocumento;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nueva Guía de Inventario')] class extends Component {
    public string $tipo_movimiento = 'Entrada';
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

    public function mount(): void
    {
        $this->fecha_movimiento = now()->format('Y-m-d');
        $this->tipo_documento_id = TipoDocumento::where('activo', true)->first()?->id;

        $primeraSede = Sede::where('activo', true)->first();
        if ($primeraSede) {
            $this->sede_destino_id = $primeraSede->id;
            $this->almacen_destino_id = Almacen::where('sede_id', $primeraSede->id)->where('activo', true)->first()?->id;
        }

        $this->actualizarSerieCorrelativo();
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

    public function updatedTipoMovimiento(): void
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
            $this->serie = '';
            $this->correlativo = '';
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

    public function guardarComoBorrador()
    {
        $this->guardar('Borrador');
    }

    private function guardar($estado)
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

        // Validación unique serie-correlativo por documento
        $exists = GuiaInventario::where('tipo_documento_id', $this->tipo_documento_id)
            ->where('serie', $this->serie)
            ->where('correlativo', (int) $this->correlativo)
            ->exists();

        if ($exists) {
            $this->addError('correlativo', 'Esta serie y correlativo ya existe para el documento seleccionado.');
            return;
        }

        $guia = GuiaInventario::create([
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
            'estado' => $estado,
        ]);

        Flux::toast(variant: 'success', text: __('Guía creada correctamente en estado Borrador.'));

        return redirect()->route('admin.guias.edit', $guia->id);
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nueva Guía') }}</flux:heading>
            <flux:subheading>{{ __('Registra un nuevo movimiento de inventario seleccionando la Sede para cargar sus almacenes y serie oficial.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.guias.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardarComoBorrador" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:field class="md:col-span-1">
                <flux:label>{{ __('Tipo de Movimiento') }}</flux:label>
                <flux:select wire:model.live="tipo_movimiento">
                    <option value="Entrada">{{ __('Entrada') }}</option>
                    <option value="Salida">{{ __('Salida') }}</option>
                    <option value="Transferencia">{{ __('Transferencia') }}</option>
                </flux:select>
                <flux:error name="tipo_movimiento" />
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>{{ __('Fecha del Movimiento') }}</flux:label>
                <flux:input type="date" wire:model="fecha_movimiento" required />
                <flux:error name="fecha_movimiento" />
            </flux:field>
        </div>

        <hr class="border-zinc-200 dark:border-zinc-700">

        {{-- Selección de Sedes y Almacenes según Tipo de Movimiento --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @if($tipo_movimiento === 'Entrada')
                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6">
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
                </div>
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
                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 p-4 rounded-xl bg-zinc-50/50 dark:bg-zinc-800/20 border border-zinc-200/80 dark:border-zinc-700/80">
                    <div class="space-y-4">
                        <span class="text-xs font-semibold uppercase text-zinc-500 tracking-wider">{{ __('Origen (Emisor)') }}</span>
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

                    <div class="space-y-4">
                        <span class="text-xs font-semibold uppercase text-zinc-500 tracking-wider">{{ __('Destino (Receptor)') }}</span>
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
                </div>
            @endif
        </div>

        <hr class="border-zinc-200 dark:border-zinc-700">

        {{-- Documento, Serie y Correlativo --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:field>
                <flux:label>{{ __('Tipo de Documento') }}</flux:label>
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
                <div class="md:col-span-3 -mt-3">
                    <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">
                        ⚠️ {{ __('La sede seleccionada no tiene una serie activa registrada para este tipo de documento. Puedes digitarla manualmente o crearla en Gestión de Series.') }}
                    </span>
                </div>
            @endif
        </div>

        <flux:field>
            <flux:label>{{ __('Motivo / Observación') }}</flux:label>
            <flux:textarea wire:model="motivo" rows="2" placeholder="Opcional..." />
            <flux:error name="motivo" />
        </flux:field>

        <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button variant="ghost" href="{{ route('admin.guias.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
            <flux:button variant="primary" type="submit" icon="document">
                {{ __('Crear y Agregar Detalles') }}
            </flux:button>
        </div>
    </form>
</div>
