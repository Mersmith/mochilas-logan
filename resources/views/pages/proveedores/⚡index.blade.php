<?php

use App\Models\Proveedor;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Proveedores')] class extends Component {
    public ?int $prov_id = null;
    public string $razon_social = '';
    public string $ruc = '';
    public string $direccion = '';
    public string $contacto_nombre = '';
    public string $contacto_celular = '';
    public bool $activo = true;

    public function guardar(): void
    {
        if (!auth()->user()->hasPermissionTo('proveedores.editar')) {
            abort(403, 'No tienes permiso para editar proveedores.');
        }

        $this->validate([
            'razon_social' => 'required|string|max:150',
            'ruc' => 'required|string|size:11|unique:proveedors,ruc,' . ($this->prov_id ?: 'NULL'),
            'direccion' => 'nullable|string|max:200',
            'contacto_nombre' => 'nullable|string|max:100',
            'contacto_celular' => 'nullable|string|max:20',
            'activo' => 'boolean',
        ]);

        if ($this->prov_id) {
            $prov = Proveedor::findOrFail($this->prov_id);
            $prov->update([
                'razon_social' => $this->razon_social,
                'ruc' => $this->ruc,
                'direccion' => $this->direccion,
                'contacto_nombre' => $this->contacto_nombre,
                'contacto_celular' => $this->contacto_celular,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Proveedor actualizado.'));
        } else {
            Proveedor::create([
                'razon_social' => $this->razon_social,
                'ruc' => $this->ruc,
                'direccion' => $this->direccion,
                'contacto_nombre' => $this->contacto_nombre,
                'contacto_celular' => $this->contacto_celular,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Proveedor registrado con éxito.'));
        }

        $this->limpiarForm();
    }

    public function editar(int $id): void
    {
        $prov = Proveedor::findOrFail($id);
        $this->prov_id = $prov->id;
        $this->razon_social = $prov->razon_social;
        $this->ruc = $prov->ruc;
        $this->direccion = $prov->direccion ?? '';
        $this->contacto_nombre = $prov->contacto_nombre ?? '';
        $this->contacto_celular = $prov->contacto_celular ?? '';
        $this->activo = $prov->activo;
    }

    public function eliminar(int $id): void
    {
        if (!auth()->user()->hasPermissionTo('proveedores.editar')) {
            abort(403, 'No tienes permiso para eliminar proveedores.');
        }

        $prov = Proveedor::findOrFail($id);
        
        // Relaciones con ingresos (si existen en el futuro)
        if ($prov->ingresos()->count() > 0) {
            Flux::toast(variant: 'danger', text: __('No se puede eliminar porque tiene ingresos o guías asociadas.'));
            return;
        }

        $prov->delete();
        Flux::toast(variant: 'success', text: __('Proveedor eliminado.'));
    }

    public function limpiarForm(): void
    {
        $this->prov_id = null;
        $this->razon_social = '';
        $this->ruc = '';
        $this->direccion = '';
        $this->contacto_nombre = '';
        $this->contacto_celular = '';
        $this->activo = true;
    }

    #[Computed]
    public function proveedores()
    {
        return Proveedor::orderBy('razon_social', 'asc')->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Proveedores') }}</flux:heading>
            <flux:subheading>{{ __('Administra los proveedores de productos y mercadería.') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        @can('proveedores.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $prov_id ? __('Editar Proveedor') : __('Nuevo Proveedor') }}</flux:heading>
                
                <form wire:submit.prevent="guardar" class="space-y-4">
                    <flux:input wire:model="ruc" :label="__('RUC')" placeholder="Ej. 20123456789" required maxlength="11" />
                    
                    <flux:input wire:model="razon_social" :label="__('Razón Social')" placeholder="Ej. Corporación SAC" required />

                    <flux:input wire:model="direccion" :label="__('Dirección')" placeholder="Av. Los Pinos..." />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="contacto_nombre" :label="__('Contacto')" placeholder="Juan Pérez" />
                        <flux:input wire:model="contacto_celular" :label="__('Celular')" placeholder="987654321" />
                    </div>

                    <flux:checkbox wire:model="activo" :label="__('Proveedor activo')" />

                    <div class="flex gap-4 pt-2">
                        @if($prov_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarForm">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $prov_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan

        <div class="{{ auth()->user()->can('proveedores.editar') ? 'xl:col-span-2' : 'xl:col-span-3' }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Proveedores Registrados') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('RUC') }}</th>
                            <th class="p-3">{{ __('Razón Social') }}</th>
                            <th class="p-3">{{ __('Contacto') }}</th>
                            <th class="p-3 text-center">{{ __('Estado') }}</th>
                            @can('proveedores.editar')
                                <th class="p-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->proveedores as $prov)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-mono text-zinc-600 dark:text-zinc-400">{{ $prov->ruc }}</td>
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">
                                    {{ $prov->razon_social }}
                                    @if($prov->direccion)
                                        <div class="text-xs font-normal text-zinc-500 mt-0.5">{{ $prov->direccion }}</div>
                                    @endif
                                </td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $prov->contacto_nombre ?: '-' }}
                                    @if($prov->contacto_celular)
                                        <span class="text-xs text-zinc-500 block">{{ $prov->contacto_celular }}</span>
                                    @endif
                                </td>
                                <td class="p-3 text-center">
                                    @if($prov->activo)
                                        <flux:badge color="success">{{ __('Activo') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                    @endif
                                </td>
                                @can('proveedores.editar')
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editar({{ $prov->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $prov->id }})" wire:confirm="¿Está seguro de eliminar este proveedor?" />
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('proveedores.editar') ? 5 : 4 }}" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay proveedores registrados.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
