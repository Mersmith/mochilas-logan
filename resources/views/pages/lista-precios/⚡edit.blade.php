<?php

use App\Models\ListaPrecio;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Lista de Precios')] class extends Component {
    public ListaPrecio $listaPrecio;
    public string $nombre = '';
    public bool $activo = true;

    public function mount(ListaPrecio $listaPrecio)
    {
        $this->listaPrecio = $listaPrecio;
        $this->nombre = $listaPrecio->nombre;
        $this->activo = $listaPrecio->activo;
    }

    public function guardar()
    {
        if (! auth()->user()->can('lista-precios.editar')) {
            abort(403);
        }

        $this->validate([
            'nombre' => 'required|string|max:255|unique:lista_precios,nombre,' . $this->listaPrecio->id,
            'activo' => 'boolean',
        ]);

        $this->listaPrecio->update([
            'nombre' => $this->nombre,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Registro actualizado correctamente.');
        return redirect()->route('admin.lista-precios.index');
    }
}; ?>

<div class="space-y-6 max-w-2xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Lista') }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos de la lista de precios.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.lista-precios.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardar" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        <flux:field>
            <flux:label>{{ __('Nombre') }}</flux:label>
            <flux:input wire:model="nombre" placeholder="Ej. Precio Mayorista..." required />
            <flux:error name="nombre" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Estado') }}</flux:label>
            <div class="flex items-center gap-3 h-10 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3">
                <flux:switch wire:model="activo" />
                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $activo ? __('Activo') : __('Inactivo') }}
                </span>
            </div>
        </flux:field>

        <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button variant="ghost" href="{{ route('admin.lista-precios.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
            <flux:button variant="primary" type="submit" icon="check">{{ __('Actualizar') }}</flux:button>
        </div>
    </form>
</div>
