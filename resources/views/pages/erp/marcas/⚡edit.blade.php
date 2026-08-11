<?php

use App\Models\Marca;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Str;

new #[Title('Editar Marca')] class extends Component {
    public Marca $marca;
    public string $nombre = '';
    public string $slug = '';
    public string $descripcion = '';
    public bool $activo = true;

    public function mount(Marca $marca)
    {
        $this->marca = $marca;
        $this->nombre = $marca->nombre;
        $this->slug = $marca->slug;
        $this->descripcion = $marca->descripcion ?? '';
        $this->activo = $marca->activo;
    }

    public function updatedNombre($value)
    {
        $this->slug = Str::slug($value);
    }

    public function guardar()
    {
        if (! auth()->user()->can('marcas.editar')) {
            abort(403);
        }

        $this->validate([
            'nombre' => 'required|string|max:255|unique:marcas,nombre,' . $this->marca->id,
            'slug' => 'required|string|max:255|unique:marcas,slug,' . $this->marca->id,
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $this->marca->update([
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Marca actualizada correctamente.');
        return redirect()->route('admin.marcas.index');
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Marca') }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos de la marca: ') }} {{ $marca->nombre }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.marcas.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <form wire:submit.prevent="guardar" class="grid grid-cols-1 gap-6">
            <flux:field>
                <flux:label>{{ __('Nombre') }}</flux:label>
                <flux:input wire:model.live="nombre" placeholder="Ej. Nike, Adidas..." required />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Slug') }}</flux:label>
                <flux:input wire:model="slug" required />
                <flux:error name="slug" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Descripción') }}</flux:label>
                <flux:textarea wire:model="descripcion" rows="3" placeholder="Descripción opcional..." />
                <flux:error name="descripcion" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Estado') }}</flux:label>
                <div class="flex items-center gap-3 h-10">
                    <flux:switch wire:model="activo" />
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $activo ? __('Activo') : __('Inactivo') }}
                    </span>
                </div>
            </flux:field>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" href="{{ route('admin.marcas.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    {{ __('Actualizar Marca') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
