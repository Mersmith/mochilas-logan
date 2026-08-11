<?php

use App\Models\GuiaInventario;
use App\Models\Almacen;
use App\Models\Proveedor;
use App\Models\TipoDocumento;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nueva Guía de Inventario')] class extends Component {
    public string $tipo_movimiento = 'Entrada';
    public ?int $proveedor_id = null;
    public ?int $almacen_origen_id = null;
    public ?int $almacen_destino_id = null;
    public ?int $tipo_documento_id = null;
    public string $serie = '';
    public string $correlativo = '';
    public string $fecha_movimiento = '';
    public string $motivo = '';

    public function mount()
    {
        $this->fecha_movimiento = now()->format('Y-m-d');
        // Seleccionar por defecto la primera guía del tipo 'inventario' si existe, o similar
        $this->tipo_documento_id = TipoDocumento::first()->id ?? null;
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

    public function updatedTipoMovimiento()
    {
        $this->reset(['proveedor_id', 'almacen_origen_id', 'almacen_destino_id']);
    }

    public function guardarComoBorrador()
    {
        $this->guardar('Borrador');
    }

    public function guardarYProcesar()
    {
        $this->guardar('Procesado');
    }

    private function guardar($estado)
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

        // Validación unique serie-correlativo por documento
        $exists = GuiaInventario::where('tipo_documento_id', $this->tipo_documento_id)
            ->where('serie', $this->serie)
            ->where('correlativo', $this->correlativo)
            ->exists();

        if ($exists) {
            $this->addError('correlativo', 'Esta serie y correlativo ya existe para el documento seleccionado.');
            return;
        }

        if ($estado === 'Procesado') {
            // Lógica de procesamiento (Aún no tenemos detalles implementados en la vista,
            // pero normalmente validarías que tenga al menos 1 detalle y que no genere stock negativo)
            Flux::toast(variant: 'warning', text: 'Nota: Para procesar una guía primero debe añadir detalles en modo borrador. (Mock)');
            // Por seguridad, forzamos a borrador en este prototipo si no hay detalles.
            $estado = 'Borrador';
        }

        $guia = GuiaInventario::create([
            'tipo_movimiento' => $this->tipo_movimiento,
            'proveedor_id' => $this->proveedor_id,
            'almacen_origen_id' => $this->almacen_origen_id,
            'almacen_destino_id' => $this->almacen_destino_id,
            'tipo_documento_id' => $this->tipo_documento_id,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'fecha_movimiento' => $this->fecha_movimiento,
            'motivo' => $this->motivo,
            'estado' => $estado,
        ]);

        Flux::toast(variant: 'success', text: 'Guía creada correctamente.');
        
        // Redirigir a edit para que pueda agregar detalles
        return redirect()->route('admin.guias.edit', $guia->id);
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nueva Guía') }}</flux:heading>
            <flux:subheading>{{ __('Registra un nuevo movimiento de inventario.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.guias.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        
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

            <flux:field class="md:col-span-1">
                <flux:label>{{ __('Fecha del Movimiento') }}</flux:label>
                <flux:input type="date" wire:model="fecha_movimiento" required />
                <flux:error name="fecha_movimiento" />
            </flux:field>
        </div>

        <hr class="border-zinc-200 dark:border-zinc-700">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                    <flux:label>{{ __('Almacén de Destino') }}</flux:label>
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
                    <flux:label>{{ __('Almacén de Origen') }}</flux:label>
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
                    <flux:label>{{ __('Almacén de Origen') }}</flux:label>
                    <flux:select wire:model="almacen_origen_id">
                        <option value="">{{ __('Seleccione Origen...') }}</option>
                        @foreach($this->almacenes as $almacen)
                            <option value="{{ $almacen['id'] }}">{{ $almacen['nombre'] }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="almacen_origen_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Almacén de Destino') }}</flux:label>
                    <flux:select wire:model="almacen_destino_id">
                        <option value="">{{ __('Seleccione Destino...') }}</option>
                        @foreach($this->almacenes as $almacen)
                            <option value="{{ $almacen['id'] }}">{{ $almacen['nombre'] }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="almacen_destino_id" />
                </flux:field>
            @endif
        </div>

        <hr class="border-zinc-200 dark:border-zinc-700">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <flux:field>
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
            <flux:label>{{ __('Motivo / Observación') }}</flux:label>
            <flux:textarea wire:model="motivo" rows="2" placeholder="Opcional..." />
            <flux:error name="motivo" />
        </flux:field>

        <div class="flex items-center justify-between pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button variant="ghost" href="{{ route('admin.guias.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
            <div class="flex gap-2">
                <flux:button variant="primary" type="button" wire:click="guardarComoBorrador" icon="document">{{ __('Crear y Agregar Detalles') }}</flux:button>
            </div>
        </div>
    </form>
</div>
