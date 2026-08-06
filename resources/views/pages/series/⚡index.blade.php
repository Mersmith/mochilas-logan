<?php

use App\Models\Serie;
use App\Models\Sede;
use App\Models\TipoDocumento;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Series')] class extends Component {
    public ?int $ser_id = null;
    public ?int $sede_id = null;
    public ?int $tipo_documento_id = null;
    public string $serie = '';
    public int $correlativo = 1;
    public bool $activo = true;

    public function mount(): void
    {
        $sede = Sede::first();
        if ($sede) {
            $this->sede_id = $sede->id;
        }

        $tipo = TipoDocumento::first();
        if ($tipo) {
            $this->tipo_documento_id = $tipo->id;
        }
    }

    public function guardar(): void
    {
        if (!auth()->user()->hasPermissionTo('series.editar')) {
            abort(403, 'No tienes permiso para editar series.');
        }

        $this->validate([
            'sede_id' => 'required|exists:sedes,id',
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'serie' => 'required|string|max:10',
            'correlativo' => 'required|integer|min:1',
            'activo' => 'boolean',
        ]);

        if ($this->ser_id) {
            $ser = Serie::findOrFail($this->ser_id);
            $ser->update([
                'sede_id' => $this->sede_id,
                'tipo_documento_id' => $this->tipo_documento_id,
                'serie' => strtoupper($this->serie),
                'correlativo' => $this->correlativo,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Serie actualizada.'));
        } else {
            Serie::create([
                'sede_id' => $this->sede_id,
                'tipo_documento_id' => $this->tipo_documento_id,
                'serie' => strtoupper($this->serie),
                'correlativo' => $this->correlativo,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Serie registrada con éxito.'));
        }

        $this->limpiarForm();
    }

    public function editar(int $id): void
    {
        $ser = Serie::findOrFail($id);
        $this->ser_id = $ser->id;
        $this->sede_id = $ser->sede_id;
        $this->tipo_documento_id = $ser->tipo_documento_id;
        $this->serie = $ser->serie;
        $this->correlativo = $ser->correlativo;
        $this->activo = $ser->activo;
    }

    public function eliminar(int $id): void
    {
        if (!auth()->user()->hasPermissionTo('series.editar')) {
            abort(403, 'No tienes permiso para eliminar series.');
        }

        $ser = Serie::findOrFail($id);
        $ser->delete();
        Flux::toast(variant: 'success', text: __('Serie eliminada.'));
    }

    public function limpiarForm(): void
    {
        $this->ser_id = null;
        $this->serie = '';
        $this->correlativo = 1;
        $this->activo = true;
    }

    #[Computed]
    public function seriesList()
    {
        return Serie::with(['sede', 'tipoDocumento'])->orderBy('serie', 'asc')->get();
    }

    #[Computed]
    public function sedes()
    {
        return Sede::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function tiposDocumento()
    {
        return TipoDocumento::where('activo', true)->orderBy('nombre')->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Series') }}</flux:heading>
            <flux:subheading>{{ __('Administra las series de comprobantes (Facturas, Boletas, etc.) por sede.') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @can('series.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $ser_id ? __('Editar Serie') : __('Nueva Serie') }}</flux:heading>
                
                <form wire:submit.prevent="guardar" class="space-y-4">
                    <flux:select wire:model="sede_id" :label="__('Sede')" required>
                        @foreach($this->sedes as $sede)
                            <flux:select.option :value="$sede->id">{{ $sede->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="tipo_documento_id" :label="__('Tipo de Documento')" required>
                        @foreach($this->tiposDocumento as $tipo)
                            <flux:select.option :value="$tipo->id">{{ $tipo->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="serie" :label="__('Serie')" placeholder="Ej. F001" required />
                    
                    <flux:input type="number" wire:model="correlativo" :label="__('Correlativo Actual')" min="1" required />

                    <flux:checkbox wire:model="activo" :label="__('Serie activa')" />

                    <div class="flex gap-4 pt-2">
                        @if($ser_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $ser_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan

        <div class="{{ auth()->user()->can('series.editar') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Series Registradas') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Sede') }}</th>
                            <th class="p-3">{{ __('Documento') }}</th>
                            <th class="p-3">{{ __('Serie') }}</th>
                            <th class="p-3">{{ __('Correlativo') }}</th>
                            <th class="p-3 text-center">{{ __('Estado') }}</th>
                            @can('series.editar')
                                <th class="p-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->seriesList as $ser)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">{{ $ser->sede->nombre ?? '-' }}</td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">{{ $ser->tipoDocumento->nombre ?? '-' }}</td>
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">{{ $ser->serie }}</td>
                                <td class="p-3 font-mono text-zinc-600 dark:text-zinc-400">{{ str_pad($ser->correlativo, 8, '0', STR_PAD_LEFT) }}</td>
                                <td class="p-3 text-center">
                                    @if($ser->activo)
                                        <flux:badge color="success">{{ __('Activo') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                    @endif
                                </td>
                                @can('series.editar')
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editar({{ $ser->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $ser->id }})" wire:confirm="¿Está seguro de eliminar esta serie?" />
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('series.editar') ? 6 : 5 }}" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay series registradas.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
