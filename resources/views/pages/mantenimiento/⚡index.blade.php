<?php

use App\Models\Proveedor;
use App\Models\Almacen;
use App\Models\Sede;
use App\Models\Serie;
use App\Models\TipoDocumento;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Configuración y Mantenimiento')] class extends Component {
    public string $activeTab = 'proveedores';

    // Proveedor Form Properties
    public ?int $prov_id = null;
    public string $prov_razon_social = '';
    public string $prov_ruc = '';
    public string $prov_direccion = '';
    public string $prov_contacto_nombre = '';
    public string $prov_contacto_celular = '';
    public bool $prov_activo = true;

    // Almacen Form Properties
    public ?int $alm_id = null;
    public ?int $alm_sede_id = null;
    public string $alm_nombre = '';
    public string $alm_ubicacion = '';
    public bool $alm_activo = true;

    // Serie Form Properties
    public ?int $ser_id = null;
    public ?int $ser_sede_id = null;
    public ?int $ser_tipo_documento_id = null;
    public string $ser_serie = '';
    public int $ser_correlativo = 1;
    public bool $ser_activo = true;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $sede = Sede::first();
        if ($sede) {
            $this->alm_sede_id = $sede->id;
            $this->ser_sede_id = $sede->id;
        }

        $tipo = TipoDocumento::first();
        if ($tipo) {
            $this->ser_tipo_documento_id = $tipo->id;
        }
    }

    /**
     * Save Proveedor (Create or Update).
     */
    public function guardarProveedor(): void
    {
        $this->validate([
            'prov_razon_social' => 'required|string|max:150',
            'prov_ruc' => 'required|string|size:11|unique:proveedors,ruc,' . ($this->prov_id ?: 'NULL'),
            'prov_direccion' => 'nullable|string|max:200',
            'prov_contacto_nombre' => 'nullable|string|max:100',
            'prov_contacto_celular' => 'nullable|string|max:20',
            'prov_activo' => 'required|boolean',
        ]);

        if ($this->prov_id) {
            $prov = Proveedor::findOrFail($this->prov_id);
            $prov->update([
                'razon_social' => $this->prov_razon_social,
                'ruc' => $this->prov_ruc,
                'direccion' => $this->prov_direccion,
                'contacto_nombre' => $this->prov_contacto_nombre,
                'contacto_celular' => $this->prov_contacto_celular,
                'activo' => $this->prov_activo,
            ]);
            Flux::toast(variant: 'success', text: __('Proveedor actualizado.'));
        } else {
            Proveedor::create([
                'razon_social' => $this->prov_razon_social,
                'ruc' => $this->prov_ruc,
                'direccion' => $this->prov_direccion,
                'contacto_nombre' => $this->prov_contacto_nombre,
                'contacto_celular' => $this->prov_contacto_celular,
                'activo' => $this->prov_activo,
            ]);
            Flux::toast(variant: 'success', text: __('Proveedor registrado con éxito.'));
        }

        $this->limpiarProveedorForm();
    }

    public function editarProveedor(int $id): void
    {
        $prov = Proveedor::findOrFail($id);
        $this->prov_id = $prov->id;
        $this->prov_razon_social = $prov->razon_social;
        $this->prov_ruc = $prov->ruc;
        $this->prov_direccion = $prov->direccion ?? '';
        $this->prov_contacto_nombre = $prov->contacto_nombre ?? '';
        $this->prov_contacto_celular = $prov->contacto_celular ?? '';
        $this->prov_activo = (bool)$prov->activo;
    }

    public function eliminarProveedor(int $id): void
    {
        $prov = Proveedor::findOrFail($id);
        $prov->delete();
        Flux::toast(variant: 'success', text: __('Proveedor eliminado.'));
    }

    public function limpiarProveedorForm(): void
    {
        $this->prov_id = null;
        $this->prov_razon_social = '';
        $this->prov_ruc = '';
        $this->prov_direccion = '';
        $this->prov_contacto_nombre = '';
        $this->prov_contacto_celular = '';
        $this->prov_activo = true;
    }

    /**
     * Save Almacen (Create or Update).
     */
    public function guardarAlmacen(): void
    {
        $this->validate([
            'alm_sede_id' => 'required|integer|exists:sedes,id',
            'alm_nombre' => 'required|string|max:100',
            'alm_ubicacion' => 'nullable|string|max:150',
            'alm_activo' => 'required|boolean',
        ]);

        if ($this->alm_id) {
            $alm = Almacen::findOrFail($this->alm_id);
            $alm->update([
                'sede_id' => $this->alm_sede_id,
                'nombre' => $this->alm_nombre,
                'ubicacion' => $this->alm_ubicacion,
                'activo' => $this->alm_activo,
            ]);
            Flux::toast(variant: 'success', text: __('Almacén actualizado.'));
        } else {
            Almacen::create([
                'sede_id' => $this->alm_sede_id,
                'nombre' => $this->alm_nombre,
                'ubicacion' => $this->alm_ubicacion,
                'activo' => $this->alm_activo,
            ]);
            Flux::toast(variant: 'success', text: __('Almacén registrado con éxito.'));
        }

        $this->limpiarAlmacenForm();
    }

    public function editarAlmacen(int $id): void
    {
        $alm = Almacen::findOrFail($id);
        $this->alm_id = $alm->id;
        $this->alm_sede_id = $alm->sede_id;
        $this->alm_nombre = $alm->nombre;
        $this->alm_ubicacion = $alm->ubicacion ?? '';
        $this->alm_activo = (bool)$alm->activo;
    }

    public function eliminarAlmacen(int $id): void
    {
        $alm = Almacen::findOrFail($id);
        $alm->delete();
        Flux::toast(variant: 'success', text: __('Almacén eliminado.'));
    }

    public function limpiarAlmacenForm(): void
    {
        $this->alm_id = null;
        $this->alm_nombre = '';
        $this->alm_ubicacion = '';
        $this->alm_activo = true;
    }

    /**
     * Save Serie (Create or Update).
     */
    public function guardarSerie(): void
    {
        $this->validate([
            'ser_sede_id' => 'required|integer|exists:sedes,id',
            'ser_tipo_documento_id' => 'required|integer|exists:tipo_documentos,id',
            'ser_serie' => 'required|string|max:10',
            'ser_correlativo' => 'required|integer|min:0',
            'ser_activo' => 'required|boolean',
        ]);

        if ($this->ser_id) {
            $ser = Serie::findOrFail($this->ser_id);
            $ser->update([
                'sede_id' => $this->ser_sede_id,
                'tipo_documento_id' => $this->ser_tipo_documento_id,
                'serie' => strtoupper($this->ser_serie),
                'correlativo' => $this->ser_correlativo,
                'activo' => $this->ser_activo,
            ]);
            Flux::toast(variant: 'success', text: __('Serie de facturación actualizada.'));
        } else {
            Serie::create([
                'sede_id' => $this->ser_sede_id,
                'tipo_documento_id' => $this->ser_tipo_documento_id,
                'serie' => strtoupper($this->ser_serie),
                'correlativo' => $this->ser_correlativo,
                'activo' => $this->ser_activo,
            ]);
            Flux::toast(variant: 'success', text: __('Serie de facturación registrada con éxito.'));
        }

        $this->limpiarSerieForm();
    }

    public function editarSerie(int $id): void
    {
        $ser = Serie::findOrFail($id);
        $this->ser_id = $ser->id;
        $this->ser_sede_id = $ser->sede_id;
        $this->ser_tipo_documento_id = $ser->tipo_documento_id;
        $this->ser_serie = $ser->serie;
        $this->ser_correlativo = $ser->correlativo;
        $this->ser_activo = (bool)$ser->activo;
    }

    public function eliminarSerie(int $id): void
    {
        $ser = Serie::findOrFail($id);
        $ser->delete();
        Flux::toast(variant: 'success', text: __('Serie eliminada.'));
    }

    public function limpiarSerieForm(): void
    {
        $this->ser_id = null;
        $this->ser_serie = '';
        $this->ser_correlativo = 1;
        $this->ser_activo = true;
    }

    /**
     * Computed collections.
     */
    #[Computed]
    public function proveedores()
    {
        return Proveedor::orderBy('razon_social', 'asc')->get();
    }

    #[Computed]
    public function almacenes()
    {
        return Almacen::with('sede')->orderBy('nombre', 'asc')->get();
    }

    #[Computed]
    public function series()
    {
        return Serie::with(['sede', 'tipoDocumento'])->orderBy('serie', 'asc')->get();
    }

    #[Computed]
    public function sedes()
    {
        return Sede::all();
    }

    #[Computed]
    public function tiposDocumento()
    {
        return TipoDocumento::all();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div>
        <flux:heading size="xl">{{ __('Mantenimiento de Sistema') }}</flux:heading>
        <flux:subheading>{{ __('Administra los recursos estructurales para facturación, almacenes y proveedores.') }}</flux:subheading>
    </div>

    <!-- Pestañas (Tabs) -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-700">
        <button wire:click.prevent="$set('activeTab', 'proveedores')" class="px-6 py-3 font-semibold text-sm border-b-2 transition-colors {{ $activeTab === 'proveedores' ? 'border-black text-black dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
            {{ __('Proveedores') }}
        </button>
        <button wire:click.prevent="$set('activeTab', 'almacenes')" class="px-6 py-3 font-semibold text-sm border-b-2 transition-colors {{ $activeTab === 'almacenes' ? 'border-black text-black dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
            {{ __('Almacenes Físicos') }}
        </button>
        <button wire:click.prevent="$set('activeTab', 'series')" class="px-6 py-3 font-semibold text-sm border-b-2 transition-colors {{ $activeTab === 'series' ? 'border-black text-black dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
            {{ __('Series de Comprobante') }}
        </button>
    </div>

    <!-- Pestaña Proveedores -->
    @if($activeTab === 'proveedores')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulario -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $prov_id ? __('Editar Proveedor') : __('Nuevo Proveedor') }}</flux:heading>
                
                <form wire:submit.prevent="guardarProveedor" class="space-y-4">
                    <flux:input wire:model="prov_razon_social" :label="__('Razón Social / Nombre')" placeholder="Ej. Textiles Oxford S.A.C." required />
                    
                    <flux:input wire:model="prov_ruc" :label="__('RUC')" placeholder="11 dígitos" minlength="11" maxlength="11" required />

                    <flux:input wire:model="prov_direccion" :label="__('Dirección')" placeholder="Dirección comercial..." />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="prov_contacto_nombre" :label="__('Nombre Contacto')" placeholder="Ej. Juan Pérez" />
                        <flux:input wire:model="prov_contacto_celular" :label="__('Celular Contacto')" placeholder="999..." />
                    </div>

                    <flux:checkbox wire:model="prov_activo" :label="__('Proveedor activo')" />

                    <div class="flex gap-4 pt-2">
                        @if($prov_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarProveedorForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $prov_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>

            <!-- Tabla -->
            <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
                <flux:heading size="lg">{{ __('Proveedores Registrados') }}</flux:heading>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                                <th class="p-3">{{ __('Razón Social') }}</th>
                                <th class="p-3">{{ __('RUC') }}</th>
                                <th class="p-3">{{ __('Contacto') }}</th>
                                <th class="p-3 text-center">{{ __('Estado') }}</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse($this->proveedores as $prov)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="p-3 font-semibold text-zinc-900 dark:text-white">
                                        {{ $prov->razon_social }}
                                    </td>
                                    <td class="p-3 font-mono">
                                        {{ $prov->ruc }}
                                    </td>
                                    <td class="p-3">
                                        <div class="font-medium text-zinc-900 dark:text-white">{{ $prov->contacto_nombre ?: '-' }}</div>
                                        <div class="text-xxs text-zinc-500">{{ $prov->contacto_celular }}</div>
                                    </td>
                                    <td class="p-3 text-center">
                                        @if($prov->activo)
                                            <flux:badge color="success">{{ __('Activo') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editarProveedor({{ $prov->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarProveedor({{ $prov->id }})" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-zinc-400">
                                        {{ __('No hay proveedores registrados.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Pestaña Almacenes -->
    @if($activeTab === 'almacenes')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulario -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $alm_id ? __('Editar Almacén') : __('Nuevo Almacén') }}</flux:heading>
                
                <form wire:submit.prevent="guardarAlmacen" class="space-y-4">
                    <flux:select wire:model="alm_sede_id" :label="__('Sede Asociada')">
                        @foreach($this->sedes as $s)
                            <flux:select.option value="{{ $s->id }}">{{ $s->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="alm_nombre" :label="__('Nombre del Almacén')" placeholder="Ej. Almacén Devoluciones" required />

                    <flux:input wire:model="alm_ubicacion" :label="__('Ubicación Física')" placeholder="Ej. Piso 2 Pasillo B..." />

                    <flux:checkbox wire:model="alm_activo" :label="__('Almacén activo')" />

                    <div class="flex gap-4 pt-2">
                        @if($alm_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarAlmacenForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $alm_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>

            <!-- Tabla -->
            <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
                <flux:heading size="lg">{{ __('Almacenes Físicos') }}</flux:heading>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                                <th class="p-3">{{ __('Almacén') }}</th>
                                <th class="p-3">{{ __('Sede') }}</th>
                                <th class="p-3">{{ __('Ubicación') }}</th>
                                <th class="p-3 text-center">{{ __('Estado') }}</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse($this->almacenes as $alm)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="p-3 font-semibold text-zinc-900 dark:text-white">
                                        {{ $alm->nombre }}
                                    </td>
                                    <td class="p-3">
                                        {{ $alm->sede->nombre }}
                                    </td>
                                    <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                        {{ $alm->ubicacion ?: '-' }}
                                    </td>
                                    <td class="p-3 text-center">
                                        @if($alm->activo)
                                            <flux:badge color="success">{{ __('Activo') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editarAlmacen({{ $alm->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarAlmacen({{ $alm->id }})" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-8 text-zinc-400">
                                        {{ __('No hay almacenes registrados.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Pestaña Series -->
    @if($activeTab === 'series')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulario -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $ser_id ? __('Editar Serie') : __('Nueva Serie') }}</flux:heading>
                
                <form wire:submit.prevent="guardarSerie" class="space-y-4">
                    <flux:select wire:model="ser_sede_id" :label="__('Sede')">
                        @foreach($this->sedes as $s)
                            <flux:select.option value="{{ $s->id }}">{{ $s->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="ser_tipo_documento_id" :label="__('Tipo Documento')">
                        @foreach($this->tiposDocumento as $td)
                            <flux:select.option value="{{ $td->id }}">{{ $td->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="ser_serie" :label="__('Serie')" placeholder="Ej. F001" required />
                        <flux:input wire:model="ser_correlativo" type="number" min="0" :label="__('Siguiente Correlativo')" required />
                    </div>

                    <flux:checkbox wire:model="ser_activo" :label="__('Serie activa')" />

                    <div class="flex gap-4 pt-2">
                        @if($ser_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarSerieForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $ser_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>

            <!-- Tabla -->
            <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
                <flux:heading size="lg">{{ __('Series de Facturación') }}</flux:heading>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                                <th class="p-3">{{ __('Sede') }}</th>
                                <th class="p-3">{{ __('Tipo Documento') }}</th>
                                <th class="p-3 text-center">{{ __('Serie') }}</th>
                                <th class="p-3 text-center">{{ __('Correlativo Actual') }}</th>
                                <th class="p-3 text-center">{{ __('Estado') }}</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse($this->series as $ser)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="p-3 font-medium text-zinc-900 dark:text-white">
                                        {{ $ser->sede->nombre }}
                                    </td>
                                    <td class="p-3">
                                        {{ $ser->tipoDocumento->nombre }}
                                    </td>
                                    <td class="p-3 text-center font-bold">
                                        {{ $ser->serie }}
                                    </td>
                                    <td class="p-3 text-center font-mono">
                                        {{ str_pad($ser->correlativo, 6, '0', STR_PAD_LEFT) }}
                                    </td>
                                    <td class="p-3 text-center">
                                        @if($ser->activo)
                                            <flux:badge color="success">{{ __('Activa') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc">{{ __('Inactiva') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editarSerie({{ $ser->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarSerie({{ $ser->id }})" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-zinc-400">
                                        {{ __('No hay series configuradas.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
