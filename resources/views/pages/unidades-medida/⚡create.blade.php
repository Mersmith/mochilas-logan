<?php

use App\Models\UnidadMedida;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nueva Unidad de Medida')] class extends Component {
    public string $nombre = '';
    public string $abreviacion = '';
    public bool $activo = true;

    public function guardar()
    {
        if (! auth()->user()->can('unidades-medida.editar')) {
            abort(403);
        }

        $this->validate([
            'nombre' => 'required|string|max:255',
            'abreviacion' => 'required|string|max:10',
            'activo' => 'boolean',
        ]);

        UnidadMedida::create([
            'nombre' => $this->nombre,
            'abreviacion' => $this->abreviacion,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Registro creado correctamente.');
        return redirect()->route('admin.unidades-medida.index');
    }
}; ?>

<div class="space-y-6 max-w-2xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nueva Unidad') }}</flux:heading>
            <flux:subheading>{{ __('Registra una nueva unidad de medida.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.unidades-medida.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardar" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        <flux:field>
            <flux:label>{{ __('Nombre') }}</flux:label>
            <flux:input wire:model="nombre" placeholder="Ej. Costal..." required />
            <flux:error name="nombre" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Abreviación') }}</flux:label>
            <flux:input wire:model="abreviacion" placeholder="Ej. COS..." required />
            <flux:error name="abreviacion" />
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
            <flux:button variant="ghost" href="{{ route('admin.unidades-medida.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
            <flux:button variant="primary" type="submit" icon="check">{{ __('Guardar') }}</flux:button>
        </div>
    </form>
</div>
