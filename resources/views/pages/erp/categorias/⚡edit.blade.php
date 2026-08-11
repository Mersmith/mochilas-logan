<?php

use App\Models\Categoria;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Illuminate\Support\Str;

new #[Title('Editar Categoría')] class extends Component {
    public Categoria $categoria;
    public ?int $categoria_padre_id = null;
    public string $codigo = '';
    public string $nombre = '';
    public string $slug = '';
    public string $descripcion = '';
    public int $orden = 0;
    public bool $activo = true;

    public function mount(Categoria $categoria)
    {
        $this->categoria = $categoria;
        $this->categoria_padre_id = $categoria->categoria_padre_id;
        $this->codigo = $categoria->codigo ?? '';
        $this->nombre = $categoria->nombre;
        $this->slug = $categoria->slug;
        $this->descripcion = $categoria->descripcion ?? '';
        $this->orden = $categoria->orden;
        $this->activo = $categoria->activo;
    }

    #[Computed]
    public function categoriasPadre()
    {
        return Categoria::where('activo', true)
            ->whereNull('categoria_padre_id')
            ->where('id', '!=', $this->categoria->id) // No puede ser padre de sí misma
            ->orderBy('nombre')
            ->get();
    }

    public function updatedNombre($value)
    {
        $this->slug = Str::slug($value);
    }

    public function guardar()
    {
        if (! auth()->user()->can('categorias.editar')) {
            abort(403);
        }

        $this->validate([
            'categoria_padre_id' => 'nullable|exists:categorias,id',
            'codigo' => 'nullable|string|max:50',
            'nombre' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:categorias,slug,' . $this->categoria->id,
            'descripcion' => 'nullable|string',
            'orden' => 'required|integer|min:0',
            'activo' => 'boolean',
        ]);

        $this->categoria->update([
            'categoria_padre_id' => $this->categoria_padre_id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'orden' => $this->orden,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Categoría actualizada correctamente.');
        return redirect()->route('admin.categorias.index');
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Categoría') }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos de la categoría: ') }} {{ $categoria->nombre }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.categorias.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <form wire:submit.prevent="guardar" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <flux:field>
                    <flux:label>{{ __('Categoría Padre') }}</flux:label>
                    <flux:select wire:model="categoria_padre_id">
                        <flux:select.option value="">{{ __('Ninguna (Categoría Principal)') }}</flux:select.option>
                        @foreach($this->categoriasPadre as $cat)
                            <flux:select.option :value="$cat->id">{{ $cat->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="categoria_padre_id" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Nombre') }}</flux:label>
                <flux:input wire:model.live="nombre" placeholder="Ej. Hombre, Mujer..." required />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Slug') }}</flux:label>
                <flux:input wire:model="slug" required />
                <flux:error name="slug" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Código') }}</flux:label>
                <flux:input wire:model="codigo" placeholder="Ej. CAT001" />
                <flux:error name="codigo" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Orden') }}</flux:label>
                <flux:input type="number" wire:model="orden" min="0" required />
                <flux:error name="orden" />
            </flux:field>

            <div class="md:col-span-2">
                <flux:field>
                    <flux:label>{{ __('Descripción') }}</flux:label>
                    <flux:textarea wire:model="descripcion" rows="3" placeholder="Descripción opcional..." />
                    <flux:error name="descripcion" />
                </flux:field>
            </div>

            <div class="md:col-span-2">
                <flux:field>
                    <flux:label>{{ __('Estado') }}</flux:label>
                    <div class="flex items-center gap-3 h-10">
                        <flux:switch wire:model="activo" />
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $activo ? __('Activo') : __('Inactivo') }}
                        </span>
                    </div>
                </flux:field>
            </div>

            <div class="md:col-span-2 flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" href="{{ route('admin.categorias.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    {{ __('Actualizar Categoría') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
