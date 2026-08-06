<?php

use App\Models\Almacen;
use App\Models\Sede;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Almacenes')] class extends Component {
    public ?int $alm_id = null;
    public ?int $sede_id = null;
    public string $nombre = '';
    public string $ubicacion = '';
    public bool $activo = true;

    public function mount(): void
    {
        $sede = Sede::first();
        if ($sede) {
            $this->sede_id = $sede->id;
        }
    }

    public function guardar(): void
    {
        if (!auth()->user()->hasPermissionTo('almacenes.editar')) {
            abort(403, 'No tienes permiso para editar almacenes.');
        }

        $this->validate([
            'sede_id' => 'required|exists:sedes,id',
            'nombre' => 'required|string|max:255',
            'ubicacion' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]);

        if ($this->alm_id) {
            $alm = Almacen::findOrFail($this->alm_id);
            $alm->update([
                'sede_id' => $this->sede_id,
                'nombre' => $this->nombre,
                'ubicacion' => $this->ubicacion,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Almacén actualizado.'));
        } else {
            Almacen::create([
                'sede_id' => $this->sede_id,
                'nombre' => $this->nombre,
                'ubicacion' => $this->ubicacion,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Almacén registrado con éxito.'));
        }

        $this->limpiarForm();
    }

    public function editar(int $id): void
    {
        $alm = Almacen::findOrFail($id);
        $this->alm_id = $alm->id;
        $this->sede_id = $alm->sede_id;
        $this->nombre = $alm->nombre;
        $this->ubicacion = $alm->ubicacion ?? '';
        $this->activo = $alm->activo;
    }

    public function eliminar(int $id): void
    {
        if (!auth()->user()->hasPermissionTo('almacenes.editar')) {
            abort(403, 'No tienes permiso para eliminar almacenes.');
        }

        $alm = Almacen::findOrFail($id);
        
        // Block deletion if relations exist (add logic here if needed later)
        
        $alm->delete();
        Flux::toast(variant: 'success', text: __('Almacén eliminado.'));
    }

    public function limpiarForm(): void
    {
        $this->alm_id = null;
        $this->nombre = '';
        $this->ubicacion = '';
        $this->activo = true;
    }

    #[Computed]
    public function almacenes()
    {
        return Almacen::with('sede')->orderBy('nombre', 'asc')->get();
    }

    #[Computed]
    public function sedes()
    {
        return Sede::where('activo', true)->orderBy('nombre')->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Almacenes') }}</flux:heading>
            <flux:subheading>{{ __('Administra los almacenes físicos y asígnalos a una sede.') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @can('almacenes.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $alm_id ? __('Editar Almacén') : __('Nuevo Almacén') }}</flux:heading>
                
                <form wire:submit.prevent="guardar" class="space-y-4">
                    <flux:select wire:model="sede_id" :label="__('Sede Perteneciente')" required>
                        @foreach($this->sedes as $sede)
                            <flux:select.option :value="$sede->id">{{ $sede->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="nombre" :label="__('Nombre del Almacén')" placeholder="Ej. Almacén Principal" required />

                    <flux:input wire:model="ubicacion" :label="__('Ubicación Física')" placeholder="Piso 1, Sección A..." />

                    <flux:checkbox wire:model="activo" :label="__('Almacén activo')" />

                    <div class="flex gap-4 pt-2">
                        @if($alm_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $alm_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan

        <div class="{{ auth()->user()->can('almacenes.editar') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Almacenes Registrados') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Nombre') }}</th>
                            <th class="p-3">{{ __('Sede') }}</th>
                            <th class="p-3">{{ __('Ubicación') }}</th>
                            <th class="p-3 text-center">{{ __('Estado') }}</th>
                            @can('almacenes.editar')
                                <th class="p-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->almacenes as $alm)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">{{ $alm->nombre }}</td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">{{ $alm->sede->nombre ?? '-' }}</td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">{{ $alm->ubicacion ?: '-' }}</td>
                                <td class="p-3 text-center">
                                    @if($alm->activo)
                                        <flux:badge color="success">{{ __('Activo') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                    @endif
                                </td>
                                @can('almacenes.editar')
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editar({{ $alm->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $alm->id }})" wire:confirm="¿Está seguro de eliminar este almacén?" />
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('almacenes.editar') ? 5 : 4 }}" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay almacenes registrados.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
