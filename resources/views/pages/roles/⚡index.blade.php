<?php

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Roles')] class extends Component {
    public ?int $rol_id = null;
    public string $nombre = '';
    public array $permisosSeleccionados = [];

    /**
     * Save Role (Create or Update) and sync permissions.
     */
    public function guardar(): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100|unique:roles,name,' . ($this->rol_id ?: 'NULL'),
        ]);

        if ($this->rol_id) {
            $rol = Role::findOrFail($this->rol_id);
            if ($rol->name === 'admin' && $this->nombre !== 'admin') {
                Flux::toast(variant: 'danger', text: __('No puedes cambiar el nombre del rol admin.'));
                return;
            }
            $rol->update(['name' => $this->nombre]);
            $rol->syncPermissions($this->permisosSeleccionados);
            Flux::toast(variant: 'success', text: __('Rol y permisos actualizados.'));
        } else {
            $rol = Role::create(['name' => $this->nombre, 'guard_name' => 'web']);
            $rol->syncPermissions($this->permisosSeleccionados);
            Flux::toast(variant: 'success', text: __('Rol registrado con éxito.'));
        }

        $this->limpiarForm();
    }

    public function editar(int $id): void
    {
        $rol = Role::with('permissions')->findOrFail($id);
        $this->rol_id = $rol->id;
        $this->nombre = $rol->name;
        $this->permisosSeleccionados = $rol->permissions->pluck('name')->toArray();
    }

    public function eliminar(int $id): void
    {
        $rol = Role::findOrFail($id);
        if ($rol->name === 'admin') {
            Flux::toast(variant: 'danger', text: __('El rol admin no puede ser eliminado.'));
            return;
        }
        $rol->delete();
        Flux::toast(variant: 'success', text: __('Rol eliminado.'));
    }

    public function limpiarForm(): void
    {
        $this->rol_id = null;
        $this->nombre = '';
        $this->permisosSeleccionados = [];
    }

    #[Computed]
    public function roles()
    {
        return Role::with('permissions')->orderBy('name', 'asc')->get();
    }

    #[Computed]
    public function todosPermisos()
    {
        return Permission::orderBy('name', 'asc')->get();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Roles') }}</flux:heading>
            <flux:subheading>{{ __('Administra los roles del sistema y asígnales permisos específicos.') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Formulario -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm h-fit">
            <flux:heading size="lg" class="mb-6">{{ $rol_id ? __('Editar Rol') : __('Nuevo Rol') }}</flux:heading>
            
            <form wire:submit.prevent="guardar" class="space-y-6">
                <flux:input wire:model="nombre" :label="__('Nombre del Rol')" placeholder="Ej. vendedor" required />

                <div>
                    <flux:label class="mb-3">{{ __('Permisos Asignados') }}</flux:label>
                    <div class="space-y-2 max-h-96 overflow-y-auto border border-zinc-200 dark:border-zinc-700 p-4 rounded-lg bg-zinc-50 dark:bg-zinc-800/40">
                        @foreach($this->todosPermisos as $permiso)
                            <flux:checkbox wire:model="permisosSeleccionados" value="{{ $permiso->name }}" :label="$permiso->name" />
                        @endforeach
                    </div>
                </div>

                <div class="flex gap-4 pt-2">
                    @if($rol_id)
                        <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarForm">{{ __('Cancelar') }}</flux:button>
                    @endif
                    <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                        {{ $rol_id ? __('Actualizar') : __('Guardar') }}
                    </flux:button>
                </div>
            </form>
        </div>

        <!-- Tabla -->
        <div class="xl:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Roles Registrados') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Rol') }}</th>
                            <th class="p-3">{{ __('Permisos') }}</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->roles as $rol)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-medium text-zinc-900 dark:text-white capitalize whitespace-nowrap">
                                    {{ $rol->name }}
                                </td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($rol->permissions as $permiso)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-300">
                                                {{ $permiso->name }}
                                            </span>
                                        @empty
                                            <span class="text-zinc-400 italic">{{ __('Sin permisos') }}</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="p-3 text-right space-x-2 whitespace-nowrap align-top">
                                    <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editar({{ $rol->id }})" />
                                    @if($rol->name !== 'admin')
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $rol->id }})" wire:confirm="¿Está seguro de eliminar este rol?" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay roles registrados.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
