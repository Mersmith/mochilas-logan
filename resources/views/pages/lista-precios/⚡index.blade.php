<?php

use App\Models\ListaPrecio;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Listas de Precios')] class extends Component {
    public ?int $lista_id = null;
    public string $nombre = '';
    public bool $activo = true;

    public function guardar(): void
    {
        if (!auth()->user()->can('lista-precios.editar')) {
            abort(403, 'No tienes permiso para editar listas de precios.');
        }

        $this->validate([
            'nombre' => 'required|string|max:255|unique:lista_precios,nombre,' . ($this->lista_id ?: 'NULL'),
            'activo' => 'boolean',
        ]);

        if ($this->lista_id) {
            $lista = ListaPrecio::findOrFail($this->lista_id);
            $lista->update([
                'nombre' => $this->nombre,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Lista de precios actualizada.'));
        } else {
            ListaPrecio::create([
                'nombre' => $this->nombre,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Lista de precios registrada.'));
        }

        $this->limpiarForm();
    }

    public function editar(int $id): void
    {
        $lista = ListaPrecio::findOrFail($id);
        $this->lista_id = $lista->id;
        $this->nombre = $lista->nombre;
        $this->activo = $lista->activo;
    }

    public function eliminar(int $id): void
    {
        if (!auth()->user()->can('lista-precios.editar')) {
            abort(403, 'No tienes permiso para eliminar listas de precios.');
        }

        $lista = ListaPrecio::findOrFail($id);
        
        // Block deletion if prices are registered (optional based on future needs)
        
        $lista->delete();
        Flux::toast(variant: 'success', text: __('Lista de precios eliminada.'));
    }

    public function limpiarForm(): void
    {
        $this->lista_id = null;
        $this->nombre = '';
        $this->activo = true;
    }

    #[Computed]
    public function listas()
    {
        return ListaPrecio::orderBy('nombre', 'asc')->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Listas de Precios') }}</flux:heading>
            <flux:subheading>{{ __('Administra las listas de precios aplicables a tus productos (Ej. Precio Mayorista, Precio Normal).') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @can('lista-precios.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $lista_id ? __('Editar Lista') : __('Nueva Lista') }}</flux:heading>
                
                <form wire:submit.prevent="guardar" class="space-y-4">
                    <flux:input wire:model="nombre" :label="__('Nombre de la Lista')" placeholder="Ej. Precio Mayorista" required />
                    
                    <flux:checkbox wire:model="activo" :label="__('Activo')" />

                    <div class="flex gap-4 pt-2">
                        @if($lista_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $lista_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan

        <div class="{{ auth()->user()->can('lista-precios.editar') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Listas Registradas') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Nombre') }}</th>
                            <th class="p-3 text-center">{{ __('Estado') }}</th>
                            @can('lista-precios.editar')
                                <th class="p-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->listas as $lista)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">{{ $lista->nombre }}</td>
                                <td class="p-3 text-center">
                                    @if($lista->activo)
                                        <flux:badge color="success">{{ __('Activo') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                    @endif
                                </td>
                                @can('lista-precios.editar')
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editar({{ $lista->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $lista->id }})" wire:confirm="¿Está seguro de eliminar esta lista?" />
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('lista-precios.editar') ? 3 : 2 }}" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay listas registradas.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
