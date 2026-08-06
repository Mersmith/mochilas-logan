<?php

use App\Models\TipoDocumento;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Tipos de Documento')] class extends Component {
    public ?int $tipo_id = null;
    public string $nombre = '';
    public string $abreviatura = '';
    public bool $activo = true;

    public function guardar(): void
    {
        if (!auth()->user()->hasPermissionTo('tipos-documento.editar')) {
            abort(403, 'No tienes permiso para editar tipos de documento.');
        }

        $this->validate([
            'nombre' => 'required|string|max:255',
            'abreviatura' => 'nullable|string|max:10',
            'activo' => 'boolean',
        ]);

        if ($this->tipo_id) {
            $tipo = TipoDocumento::findOrFail($this->tipo_id);
            $tipo->update([
                'nombre' => $this->nombre,
                'abreviatura' => strtoupper($this->abreviatura),
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Tipo de documento actualizado.'));
        } else {
            TipoDocumento::create([
                'nombre' => $this->nombre,
                'abreviatura' => strtoupper($this->abreviatura),
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Tipo de documento registrado.'));
        }

        $this->limpiarForm();
    }

    public function editar(int $id): void
    {
        $tipo = TipoDocumento::findOrFail($id);
        $this->tipo_id = $tipo->id;
        $this->nombre = $tipo->nombre;
        $this->abreviatura = $tipo->abreviatura ?? '';
        $this->activo = $tipo->activo;
    }

    public function eliminar(int $id): void
    {
        if (!auth()->user()->hasPermissionTo('tipos-documento.editar')) {
            abort(403, 'No tienes permiso para eliminar tipos de documento.');
        }

        $tipo = TipoDocumento::findOrFail($id);
        $tipo->delete();
        Flux::toast(variant: 'success', text: __('Tipo de documento eliminado.'));
    }

    public function limpiarForm(): void
    {
        $this->tipo_id = null;
        $this->nombre = '';
        $this->abreviatura = '';
        $this->activo = true;
    }

    #[Computed]
    public function tipos()
    {
        return TipoDocumento::orderBy('nombre', 'asc')->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Tipos de Documento') }}</flux:heading>
            <flux:subheading>{{ __('Administra los tipos de documentos válidos (Facturas, Boletas, Guías, etc.)') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @can('tipos-documento.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $tipo_id ? __('Editar Tipo') : __('Nuevo Tipo') }}</flux:heading>
                
                <form wire:submit.prevent="guardar" class="space-y-4">
                    <flux:input wire:model="nombre" :label="__('Nombre del Documento')" placeholder="Ej. Factura Electrónica" required />
                    
                    <flux:input wire:model="abreviatura" :label="__('Abreviatura / Código')" placeholder="Ej. FAC" />

                    <flux:checkbox wire:model="activo" :label="__('Activo')" />

                    <div class="flex gap-4 pt-2">
                        @if($tipo_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $tipo_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan

        <div class="{{ auth()->user()->can('tipos-documento.editar') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Tipos Registrados') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Nombre') }}</th>
                            <th class="p-3">{{ __('Abreviatura') }}</th>
                            <th class="p-3 text-center">{{ __('Estado') }}</th>
                            @can('tipos-documento.editar')
                                <th class="p-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->tipos as $tipo)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">{{ $tipo->nombre }}</td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">{{ $tipo->abreviatura ?: '-' }}</td>
                                <td class="p-3 text-center">
                                    @if($tipo->activo)
                                        <flux:badge color="success">{{ __('Activo') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                    @endif
                                </td>
                                @can('tipos-documento.editar')
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editar({{ $tipo->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $tipo->id }})" wire:confirm="¿Está seguro?" />
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('tipos-documento.editar') ? 4 : 3 }}" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay tipos registrados.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
