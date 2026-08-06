<?php

use App\Models\Marca;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Marcas')] class extends Component {
    public ?int $marca_id = null;
    public string $nombre = '';
    public string $slug = '';
    public string $descripcion = '';
    public bool $activo = true;

    // Automatically generate slug when name changes
    public function updatedNombre($value)
    {
        $this->slug = Str::slug($value);
    }

    public function guardar(): void
    {
        if (!auth()->user()->can('marcas.editar')) {
            abort(403, 'No tienes permiso para editar marcas.');
        }

        $this->validate([
            'nombre' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:marcas,slug,' . ($this->marca_id ?: 'NULL'),
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        if ($this->marca_id) {
            $marca = Marca::findOrFail($this->marca_id);
            $marca->update([
                'nombre' => $this->nombre,
                'slug' => $this->slug,
                'descripcion' => $this->descripcion,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Marca actualizada.'));
        } else {
            Marca::create([
                'nombre' => $this->nombre,
                'slug' => $this->slug,
                'descripcion' => $this->descripcion,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Marca registrada.'));
        }

        $this->limpiarForm();
    }

    public function editar(int $id): void
    {
        $marca = Marca::findOrFail($id);
        $this->marca_id = $marca->id;
        $this->nombre = $marca->nombre;
        $this->slug = $marca->slug;
        $this->descripcion = $marca->descripcion ?? '';
        $this->activo = $marca->activo;
    }

    public function eliminar(int $id): void
    {
        if (!auth()->user()->can('marcas.editar')) {
            abort(403, 'No tienes permiso para eliminar marcas.');
        }

        $marca = Marca::findOrFail($id);
        
        // Block deletion if relations exist (add logic here if needed later when Products exist)
        
        $marca->delete();
        Flux::toast(variant: 'success', text: __('Marca eliminada.'));
    }

    public function limpiarForm(): void
    {
        $this->marca_id = null;
        $this->nombre = '';
        $this->slug = '';
        $this->descripcion = '';
        $this->activo = true;
    }

    #[Computed]
    public function marcas()
    {
        return Marca::orderBy('nombre', 'asc')->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Marcas') }}</flux:heading>
            <flux:subheading>{{ __('Administra las marcas de los productos.') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @can('marcas.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $marca_id ? __('Editar Marca') : __('Nueva Marca') }}</flux:heading>
                
                <form wire:submit.prevent="guardar" class="space-y-4">
                    <flux:input wire:model.live="nombre" :label="__('Nombre')" placeholder="Ej. Porta, Artesco..." required />
                    
                    <flux:input wire:model="slug" :label="__('Slug (URL)')" placeholder="porta" required />

                    <flux:textarea wire:model="descripcion" :label="__('Descripción')" placeholder="Detalles de la marca..." />

                    <flux:checkbox wire:model="activo" :label="__('Activo')" />

                    <div class="flex gap-4 pt-2">
                        @if($marca_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $marca_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan

        <div class="{{ auth()->user()->can('marcas.editar') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Marcas Registradas') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Nombre') }}</th>
                            <th class="p-3">{{ __('Slug') }}</th>
                            <th class="p-3 text-center">{{ __('Estado') }}</th>
                            @can('marcas.editar')
                                <th class="p-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->marcas as $marca)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">
                                    {{ $marca->nombre }}
                                    @if($marca->descripcion)
                                        <div class="text-xs text-zinc-500 font-normal mt-0.5 line-clamp-1" title="{{ $marca->descripcion }}">{{ $marca->descripcion }}</div>
                                    @endif
                                </td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400 font-mono text-xs">{{ $marca->slug }}</td>
                                <td class="p-3 text-center">
                                    @if($marca->activo)
                                        <flux:badge color="success">{{ __('Activo') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                    @endif
                                </td>
                                @can('marcas.editar')
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editar({{ $marca->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $marca->id }})" wire:confirm="¿Está seguro de eliminar esta marca?" />
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('marcas.editar') ? 4 : 3 }}" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay marcas registradas.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
