<?php

use App\Models\Sede;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Sedes')] class extends Component {
    public ?int $sede_id = null;
    public string $nombre = '';
    public string $direccion = '';
    public bool $activo = true;

    /**
     * Save Sede (Create or Update).
     */
    public function guardar(): void
    {
        // Require sedes.editar or sedes.crear depending on the action, or just sedes.editar.
        // If they can see the page (sedes.ver), they might not be able to edit.
        if (!auth()->user()->hasPermissionTo('sedes.editar')) {
            abort(403, 'No tienes permiso para editar sedes.');
        }

        $this->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]);

        if ($this->sede_id) {
            $sede = Sede::findOrFail($this->sede_id);
            $sede->update([
                'nombre' => $this->nombre,
                'direccion' => $this->direccion,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Sede actualizada.'));
        } else {
            Sede::create([
                'nombre' => $this->nombre,
                'direccion' => $this->direccion,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Sede registrada con éxito.'));
        }

        $this->limpiarForm();
    }

    public function editar(int $id): void
    {
        $sede = Sede::findOrFail($id);
        $this->sede_id = $sede->id;
        $this->nombre = $sede->nombre;
        $this->direccion = $sede->direccion ?? '';
        $this->activo = $sede->activo;
    }

    public function eliminar(int $id): void
    {
        if (!auth()->user()->hasPermissionTo('sedes.editar')) {
            abort(403, 'No tienes permiso para eliminar sedes.');
        }

        $sede = Sede::findOrFail($id);
        
        // Prevent deletion if it has relations like almacenes or series
        if ($sede->almacenes()->count() > 0 || $sede->series()->count() > 0) {
            Flux::toast(variant: 'danger', text: __('No se puede eliminar la sede porque tiene almacenes o series asignados.'));
            return;
        }

        $sede->delete();
        Flux::toast(variant: 'success', text: __('Sede eliminada.'));
    }

    public function limpiarForm(): void
    {
        $this->sede_id = null;
        $this->nombre = '';
        $this->direccion = '';
        $this->activo = true;
    }

    #[Computed]
    public function sedes()
    {
        return Sede::withCount(['almacenes', 'series'])->orderBy('nombre', 'asc')->get();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Sedes') }}</flux:heading>
            <flux:subheading>{{ __('Administra las sedes físicas o sucursales de la empresa.') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Formulario (Solo visible si tiene permiso para editar) -->
        @can('sedes.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $sede_id ? __('Editar Sede') : __('Nueva Sede') }}</flux:heading>
                
                <form wire:submit.prevent="guardar" class="space-y-4">
                    <flux:input wire:model="nombre" :label="__('Nombre de la Sede')" placeholder="Ej. Sede Principal" required />

                    <flux:input wire:model="direccion" :label="__('Dirección')" placeholder="Av. Principal 123..." />

                    <flux:checkbox wire:model="activo" :label="__('Sede activa')" />

                    <div class="flex gap-4 pt-2">
                        @if($sede_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $sede_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan

        <!-- Tabla -->
        <div class="{{ auth()->user()->can('sedes.editar') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Sedes Registradas') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Nombre') }}</th>
                            <th class="p-3">{{ __('Dirección') }}</th>
                            <th class="p-3 text-center">{{ __('Almacenes') }}</th>
                            <th class="p-3 text-center">{{ __('Estado') }}</th>
                            @can('sedes.editar')
                                <th class="p-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->sedes as $sede)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">
                                    {{ $sede->nombre }}
                                </td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $sede->direccion ?: '-' }}
                                </td>
                                <td class="p-3 text-center text-zinc-600 dark:text-zinc-400">
                                    {{ $sede->almacenes_count }}
                                </td>
                                <td class="p-3 text-center">
                                    @if($sede->activo)
                                        <flux:badge color="success">{{ __('Activa') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Inactiva') }}</flux:badge>
                                    @endif
                                </td>
                                @can('sedes.editar')
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editar({{ $sede->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $sede->id }})" wire:confirm="¿Está seguro de eliminar esta sede?" />
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('sedes.editar') ? 5 : 4 }}" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay sedes registradas.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
