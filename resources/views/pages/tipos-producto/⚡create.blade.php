<?php

use App\Models\TipoProducto;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Str;

new #[Title('Nuevo Tipo de Producto')] class extends Component {
    public string $nombre = '';
    public string $slug = '';
    public bool $activo = true;

    public function updatedNombre($value)
    {
        $this->slug = Str::slug($value);
    }

    public function guardar()
    {
        if (! auth()->user()->can('tipos-producto.editar')) {
            abort(403);
        }

        $this->validate([
            'nombre' => 'required|string|max:255|unique:tipo_productos,nombre',
            'slug' => 'required|string|max:255|unique:tipo_productos,slug',
            'activo' => 'boolean',
        ]);

        TipoProducto::create([
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Tipo de Producto creado correctamente.');
        return redirect()->route('admin.tipos-producto.index');
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo Tipo de Producto') }}</flux:heading>
            <flux:subheading>{{ __('Registra un nuevo tipo de producto para el sistema.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.tipos-producto.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <form wire:submit.prevent="guardar" class="grid grid-cols-1 gap-6">
            <flux:field>
                <flux:label>{{ __('Nombre') }}</flux:label>
                <flux:input wire:model.live="nombre" placeholder="Ej. Mochilas, Maletines..." required />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Slug') }}</flux:label>
                <flux:input wire:model="slug" required />
                <flux:error name="slug" />
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
                <flux:button variant="ghost" href="{{ route('admin.tipos-producto.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    {{ __('Crear Tipo de Producto') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
