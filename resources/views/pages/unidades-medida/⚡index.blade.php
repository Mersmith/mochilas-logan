<?php

use App\Models\UnidadMedida;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Unidades de Medida')] class extends Component {
    public ?int $unidad_id = null;
    public string $nombre = '';
    public string $abreviacion = '';

    public function guardar(): void
    {
        if (!auth()->user()->can('unidades-medida.editar')) {
            abort(403, 'No tienes permiso para editar unidades de medida.');
        }

        $this->validate([
            'nombre' => 'required|string|max:255',
            'abreviacion' => 'required|string|max:10',
        ]);

        if ($this->unidad_id) {
            $unidad = UnidadMedida::findOrFail($this->unidad_id);
            $unidad->update([
                'nombre' => $this->nombre,
                'abreviacion' => $this->abreviacion,
            ]);
            Flux::toast(variant: 'success', text: __('Unidad de medida actualizada.'));
        } else {
            UnidadMedida::create([
                'nombre' => $this->nombre,
                'abreviacion' => $this->abreviacion,
            ]);
            Flux::toast(variant: 'success', text: __('Unidad de medida registrada.'));
        }

        $this->limpiarForm();
    }

    public function editar(int $id): void
    {
        $unidad = UnidadMedida::findOrFail($id);
        $this->unidad_id = $unidad->id;
        $this->nombre = $unidad->nombre;
        $this->abreviacion = $unidad->abreviacion;
    }

    public function eliminar(int $id): void
    {
        if (!auth()->user()->can('unidades-medida.editar')) {
            abort(403, 'No tienes permiso para eliminar unidades de medida.');
        }

        $unidad = UnidadMedida::findOrFail($id);
        
        // Add check if used in products if needed later
        
        $unidad->delete();
        Flux::toast(variant: 'success', text: __('Unidad de medida eliminada.'));
    }

    public function limpiarForm(): void
    {
        $this->unidad_id = null;
        $this->nombre = '';
        $this->abreviacion = '';
    }

    #[Computed]
    public function unidades()
    {
        return UnidadMedida::orderBy('nombre', 'asc')->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Unidades de Medida') }}</flux:heading>
            <flux:subheading>{{ __('Administra las unidades en las que se venden los productos (Ej. Unidades, Cajas, Docenas).') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @can('unidades-medida.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $unidad_id ? __('Editar Unidad') : __('Nueva Unidad') }}</flux:heading>
                
                <form wire:submit.prevent="guardar" class="space-y-4">
                    <flux:input wire:model="nombre" :label="__('Nombre')" placeholder="Ej. Docena" required />
                    
                    <flux:input wire:model="abreviacion" :label="__('Abreviación')" placeholder="Ej. DZ" required maxlength="10" />

                    <div class="flex gap-4 pt-2">
                        @if($unidad_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $unidad_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan

        <div class="{{ auth()->user()->can('unidades-medida.editar') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Unidades Registradas') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Nombre') }}</th>
                            <th class="p-3">{{ __('Abreviación') }}</th>
                            @can('unidades-medida.editar')
                                <th class="p-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->unidades as $unidad)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">{{ $unidad->nombre }}</td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400 font-mono">{{ $unidad->abreviacion }}</td>
                                @can('unidades-medida.editar')
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editar({{ $unidad->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $unidad->id }})" wire:confirm="¿Está seguro de eliminar esta unidad?" />
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('unidades-medida.editar') ? 3 : 2 }}" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay unidades de medida registradas.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
