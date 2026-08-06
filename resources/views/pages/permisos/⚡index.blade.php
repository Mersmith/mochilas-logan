<?php

use Spatie\Permission\Models\Permission;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Permisos')] class extends Component {
    public ?int $permiso_id = null;
    public string $nombre = '';

    /**
     * Save Permission (Create or Update).
     */
    public function guardar(): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100|unique:permissions,name,' . ($this->permiso_id ?: 'NULL'),
        ]);

        if ($this->permiso_id) {
            $permiso = Permission::findOrFail($this->permiso_id);
            $permiso->update(['name' => $this->nombre]);
            Flux::toast(variant: 'success', text: __('Permiso actualizado.'));
        } else {
            Permission::create(['name' => $this->nombre, 'guard_name' => 'web']);
            Flux::toast(variant: 'success', text: __('Permiso registrado con éxito.'));
        }

        $this->limpiarForm();
    }

    public function editar(int $id): void
    {
        $permiso = Permission::findOrFail($id);
        $this->permiso_id = $permiso->id;
        $this->nombre = $permiso->name;
    }

    public function eliminar(int $id): void
    {
        $permiso = Permission::findOrFail($id);
        $permiso->delete();
        Flux::toast(variant: 'success', text: __('Permiso eliminado.'));
    }

    public function limpiarForm(): void
    {
        $this->permiso_id = null;
        $this->nombre = '';
    }

    #[Computed]
    public function permisos()
    {
        return Permission::orderBy('name', 'asc')->get();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Permisos') }}</flux:heading>
            <flux:subheading>{{ __('Administra los permisos del sistema que podrán ser asignados a los roles.') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Formulario -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
            <flux:heading size="lg">{{ $permiso_id ? __('Editar Permiso') : __('Nuevo Permiso') }}</flux:heading>
            
            <form wire:submit.prevent="guardar" class="space-y-4">
                <flux:input wire:model="nombre" :label="__('Nombre del Permiso')" placeholder="Ej. reportes.ver" required />

                <div class="flex gap-4 pt-2">
                    @if($permiso_id)
                        <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarForm">{{ __('Cancelar') }}</flux:button>
                    @endif
                    <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                        {{ $permiso_id ? __('Actualizar') : __('Guardar') }}
                    </flux:button>
                </div>
            </form>
        </div>

        <!-- Tabla -->
        <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Permisos Registrados') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Nombre') }}</th>
                            <th class="p-3 text-center">{{ __('Guard') }}</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->permisos as $permiso)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">
                                    {{ $permiso->name }}
                                </td>
                                <td class="p-3 text-center text-zinc-600 dark:text-zinc-400">
                                    {{ $permiso->guard_name }}
                                </td>
                                <td class="p-3 text-right space-x-2">
                                    <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editar({{ $permiso->id }})" />
                                    <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $permiso->id }})" wire:confirm="¿Está seguro de eliminar este permiso?" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay permisos registrados.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
