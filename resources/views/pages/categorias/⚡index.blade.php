<?php

use App\Models\Categoria;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Categorías')] class extends Component {
    public ?int $categoria_id = null;
    public ?int $categoria_padre_id = null;
    public string $codigo = '';
    public string $nombre = '';
    public string $slug = '';
    public string $descripcion = '';
    public int $orden = 0;
    public bool $activo = true;

    // Automatically generate slug when name changes
    public function updatedNombre($value)
    {
        $this->slug = Str::slug($value);
    }

    public function guardar(): void
    {
        if (!auth()->user()->hasPermissionTo('categorias.editar')) {
            abort(403, 'No tienes permiso para editar categorías.');
        }

        $this->validate([
            'categoria_padre_id' => 'nullable|exists:categorias,id',
            'codigo' => 'nullable|string|max:255|unique:categorias,codigo,' . ($this->categoria_id ?: 'NULL'),
            'nombre' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categorias,slug,' . ($this->categoria_id ?: 'NULL'),
            'descripcion' => 'nullable|string',
            'orden' => 'required|integer',
            'activo' => 'boolean',
        ]);

        if ($this->categoria_id) {
            // Check self reference
            if ($this->categoria_id == $this->categoria_padre_id) {
                Flux::toast(variant: 'danger', text: __('Una categoría no puede ser padre de sí misma.'));
                return;
            }

            $categoria = Categoria::findOrFail($this->categoria_id);
            $categoria->update([
                'categoria_padre_id' => $this->categoria_padre_id ?: null,
                'codigo' => $this->codigo,
                'nombre' => $this->nombre,
                'slug' => $this->slug,
                'descripcion' => $this->descripcion,
                'orden' => $this->orden,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Categoría actualizada.'));
        } else {
            Categoria::create([
                'categoria_padre_id' => $this->categoria_padre_id ?: null,
                'codigo' => $this->codigo,
                'nombre' => $this->nombre,
                'slug' => $this->slug,
                'descripcion' => $this->descripcion,
                'orden' => $this->orden,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Categoría registrada.'));
        }

        $this->limpiarForm();
    }

    public function editar(int $id): void
    {
        $categoria = Categoria::findOrFail($id);
        $this->categoria_id = $categoria->id;
        $this->categoria_padre_id = $categoria->categoria_padre_id;
        $this->codigo = $categoria->codigo ?? '';
        $this->nombre = $categoria->nombre;
        $this->slug = $categoria->slug;
        $this->descripcion = $categoria->descripcion ?? '';
        $this->orden = $categoria->orden;
        $this->activo = $categoria->activo;
    }

    public function eliminar(int $id): void
    {
        if (!auth()->user()->hasPermissionTo('categorias.editar')) {
            abort(403, 'No tienes permiso para eliminar categorías.');
        }

        $categoria = Categoria::findOrFail($id);
        
        if (Categoria::where('categoria_padre_id', $id)->exists()) {
            Flux::toast(variant: 'danger', text: __('No se puede eliminar porque tiene sub-categorías.'));
            return;
        }

        // Add product check if needed
        
        $categoria->delete();
        Flux::toast(variant: 'success', text: __('Categoría eliminada.'));
    }

    public function limpiarForm(): void
    {
        $this->categoria_id = null;
        $this->categoria_padre_id = null;
        $this->codigo = '';
        $this->nombre = '';
        $this->slug = '';
        $this->descripcion = '';
        $this->orden = 0;
        $this->activo = true;
    }

    #[Computed]
    public function categoriasList()
    {
        return Categoria::with('categoriaPadre') 
            ->orderBy('orden', 'asc')
            ->orderBy('nombre', 'asc')
            ->get();
    }

    #[Computed]
    public function categoriasPadre()
    {
        $query = Categoria::where('activo', true)->orderBy('nombre');
        if ($this->categoria_id) {
            $query->where('id', '!=', $this->categoria_id); // Exclude self
        }
        return $query->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Categorías') }}</flux:heading>
            <flux:subheading>{{ __('Administra las categorías y sub-categorías de los productos.') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @can('categorias.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $categoria_id ? __('Editar Categoría') : __('Nueva Categoría') }}</flux:heading>
                
                <form wire:submit.prevent="guardar" class="space-y-4">
                    <flux:select wire:model="categoria_padre_id" :label="__('Categoría Padre (Opcional)')" placeholder="Ninguna (Categoría Principal)">
                        @foreach($this->categoriasPadre as $cat)
                            <flux:select.option :value="$cat->id">{{ $cat->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="codigo" :label="__('Código (Opcional)')" placeholder="Ej. CAT-01" />

                    <flux:input wire:model.live="nombre" :label="__('Nombre')" placeholder="Ej. Útiles Escolares" required />
                    
                    <flux:input wire:model="slug" :label="__('Slug (URL)')" placeholder="utiles-escolares" required />

                    <flux:textarea wire:model="descripcion" :label="__('Descripción')" placeholder="Detalles..." />

                    <flux:input type="number" wire:model="orden" :label="__('Orden de visualización')" />

                    <flux:checkbox wire:model="activo" :label="__('Activo')" />

                    <div class="flex gap-4 pt-2">
                        @if($categoria_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $categoria_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan

        <div class="{{ auth()->user()->can('categorias.editar') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Categorías Registradas') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Nombre') }}</th>
                            <th class="p-3">{{ __('Código') }}</th>
                            <th class="p-3">{{ __('Padre') }}</th>
                            <th class="p-3 text-center">{{ __('Orden') }}</th>
                            <th class="p-3 text-center">{{ __('Estado') }}</th>
                            @can('categorias.editar')
                                <th class="p-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->categoriasList as $cat)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">
                                    @if($cat->categoria_padre_id)
                                        <span class="text-zinc-300 dark:text-zinc-600">↳ </span>
                                    @endif
                                    {{ $cat->nombre }}
                                </td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">{{ $cat->codigo ?: '-' }}</td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $cat->categoriaPadre?->nombre ?? '-' }}
                                </td>
                                <td class="p-3 text-center text-zinc-600 dark:text-zinc-400">{{ $cat->orden }}</td>
                                <td class="p-3 text-center">
                                    @if($cat->activo)
                                        <flux:badge color="success">{{ __('Activo') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                    @endif
                                </td>
                                @can('categorias.editar')
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editar({{ $cat->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $cat->id }})" wire:confirm="¿Está seguro de eliminar esta categoría?" />
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('categorias.editar') ? 6 : 5 }}" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay categorías registradas.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
