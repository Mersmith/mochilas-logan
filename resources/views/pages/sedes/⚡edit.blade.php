<?php

use App\Models\Sede;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Sede')] class extends Component {
    public Sede $sede;
    public string $nombre = '';
    public string $direccion = '';
    public bool $activo = true;

    public function mount(Sede $sede)
    {
        $this->sede = $sede;
        $this->nombre = $sede->nombre;
        $this->direccion = $sede->direccion ?? '';
        $this->activo = $sede->activo;
    }

    public function guardar()
    {
        if (! auth()->user()->can('sedes.editar')) {
            abort(403);
        }

        $this->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]);

        $this->sede->update([
            'nombre' => $this->nombre,
            'direccion' => $this->direccion,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Sede actualizada correctamente.');
        return redirect()->route('admin.sedes.index');
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Sede') }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos de la sede: ') }} {{ $sede->nombre }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.sedes.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <form wire:submit.prevent="guardar" class="grid grid-cols-1 gap-6">
            <flux:field>
                <flux:label>{{ __('Nombre de la Sede') }}</flux:label>
                <flux:input wire:model="nombre" placeholder="Ej. Sede Principal" required />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Dirección') }}</flux:label>
                <flux:input wire:model="direccion" placeholder="Av. Principal 123..." />
                <flux:error name="direccion" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Estado') }}</flux:label>
                <div class="flex items-center gap-3 h-10">
                    <flux:switch wire:model="activo" />
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $activo ? __('Sede activa') : __('Sede inactiva') }}
                    </span>
                </div>
            </flux:field>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" href="{{ route('admin.sedes.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    {{ __('Actualizar Sede') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
