<?php

use App\Models\User;
use App\Exports\UsuariosExport;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

new #[Title('Gestión de Usuarios')] class extends Component {
    use WithPagination;

    // ── Filtros ──────────────────────────────────────────
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $filtroRol = '';
    #[Url]
    public string $filtroEstado = 'todos'; // activos | desactivados | todos
    #[Url]
    public string $filtroPapelera = 'admitidos'; // admitidos | eliminados | todos
    #[Url]
    public string $desde = '';
    #[Url]
    public string $hasta = '';
    #[Url]
    public int $perPage = 10;

    // ── Modal Cambiar Contraseña ──────────────────────────
    public ?int $cambiarPass_usuario_id = null;
    public string $nueva_password = '';
    public string $nueva_password_confirmation = '';

    /**
     * Resetea la paginación cuando cambia algún filtro
     */
    public function updating($property)
    {
        if (in_array($property, ['search', 'filtroRol', 'filtroEstado', 'filtroPapelera', 'desde', 'hasta', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'filtroRol', 'filtroEstado', 'filtroPapelera', 'desde', 'hasta']);
        $this->perPage = 10;
        $this->resetPage();
    }

    /**
     * Devuelve el query builder base con los filtros aplicados.
     */
    protected function getBaseQuery()
    {
        $query = User::with('roles')
            ->where('is_super_admin', false) // super admin is invisible
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->when($this->filtroRol, fn ($q) => $q->role($this->filtroRol))
            ->orderBy('name');

        // Filtro de estado de cuenta (Activo / Inactivo)
        if ($this->filtroEstado === 'activos') {
            $query->where('activo', true);
        } elseif ($this->filtroEstado === 'desactivados') {
            $query->where('activo', false);
        }

        // Filtro de papelera (Soft Deletes)
        if ($this->filtroPapelera === 'eliminados') {
            $query->onlyTrashed();
        } elseif ($this->filtroPapelera === 'todos') {
            $query->withTrashed();
        } // Si es 'admitidos', no se aplica nada (usa el default de sin trashed)

        // Filtro de Fechas
        $query->when($this->desde, fn($q) => $q->whereDate('created_at', '>=', $this->desde))
              ->when($this->hasta, fn($q) => $q->whereDate('created_at', '<=', $this->hasta));

        return $query;
    }

    /**
     * Lista de usuarios paginada.
     */
    #[Computed]
    public function usuarios()
    {
        return $this->getBaseQuery()->paginate($this->perPage);
    }

    /**
     * Lista de roles disponibles para el select.
     */
    #[Computed]
    public function roles()
    {
        return Role::orderBy('name')->get();
    }

    /**
     * Eliminación lógica (soft delete).
     */
    public function eliminar(int $id): void
    {
        if (! auth()->user()->hasPermissionTo('usuarios.editar')) {
            abort(403);
        }

        $usuario = User::findOrFail($id);

        if ($usuario->is_super_admin) {
            abort(403, 'Este usuario no puede ser modificado.');
        }

        if ($usuario->id === auth()->id()) {
            Flux::toast(variant: 'warning', text: 'No puedes eliminar tu propia cuenta.');
            return;
        }

        $usuario->delete();
        Flux::toast(variant: 'success', text: 'Usuario eliminado (soft delete).');
    }

    /**
     * Activar / Desactivar usuario.
     */
    public function toggleActivo(int $id): void
    {
        if (! auth()->user()->hasPermissionTo('usuarios.editar')) {
            abort(403);
        }

        $usuario = User::findOrFail($id);

        if ($usuario->is_super_admin) {
            abort(403, 'Este usuario no puede ser modificado.');
        }

        if ($usuario->id === auth()->id()) {
            Flux::toast(variant: 'warning', text: 'No puedes desactivarte a ti mismo.');
            return;
        }

        $usuario->update(['activo' => ! $usuario->activo]);
        Flux::toast(
            variant: $usuario->activo ? 'success' : 'warning',
            text: $usuario->activo ? 'Usuario activado.' : 'Usuario desactivado.'
        );
    }

    /**
     * Restaurar usuario eliminado.
     */
    public function restaurar(int $id): void
    {
        if (! auth()->user()->hasPermissionTo('usuarios.editar')) {
            abort(403);
        }

        User::withTrashed()->findOrFail($id)->restore();
        Flux::toast(variant: 'success', text: 'Usuario restaurado correctamente.');
    }

    /**
     * Abrir modal para cambiar contraseña.
     */
    public function abrirCambiarPassword(int $id): void
    {
        $this->cambiarPass_usuario_id = $id;
        $this->nueva_password = '';
        $this->nueva_password_confirmation = '';
        Flux::modal('cambiar-password')->show();
    }

    /**
     * Guardar nueva contraseña.
     */
    public function cambiarPassword(): void
    {
        if (! auth()->user()->hasPermissionTo('usuarios.editar')) {
            abort(403);
        }

        $this->validate([
            'nueva_password' => 'required|string|min:8|confirmed',
        ], [], [
            'nueva_password'              => 'nueva contraseña',
            'nueva_password_confirmation' => 'confirmación',
        ]);

        User::withTrashed()->findOrFail($this->cambiarPass_usuario_id)
            ->update(['password' => bcrypt($this->nueva_password)]);

        Flux::toast(variant: 'success', text: 'Contraseña actualizada correctamente.');
        Flux::modal('cambiar-password')->close();
        $this->cambiarPass_usuario_id = null;
        $this->nueva_password = '';
        $this->nueva_password_confirmation = '';
    }

    // ── Exportaciones ──────────────────────────────────────────

    public function exportarTodos()
    {
        // Query sin filtros (pero excluyendo al super admin siempre)
        $query = User::with('roles')->where('is_super_admin', false)->orderBy('name');
        return Excel::download(new UsuariosExport($query), 'todos_los_usuarios.xlsx');
    }

    public function exportarFiltrados()
    {
        $query = $this->getBaseQuery();
        return Excel::download(new UsuariosExport($query), 'usuarios_filtrados.xlsx');
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Usuarios') }}</flux:heading>
            <flux:subheading>{{ __('Administra los usuarios del sistema y sus roles de acceso.') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:dropdown>
                <flux:button variant="subtle" icon="arrow-down-tray">{{ __('Exportar') }}</flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportarTodos" icon="document-text">{{ __('Todos los usuarios') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportarFiltrados" icon="funnel">{{ __('Resultados filtrados') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            @can('usuarios.editar')
                <flux:button variant="primary" icon="plus" href="{{ route('admin.usuarios.create') }}" wire:navigate>
                    {{ __('Nuevo Usuario') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="{{ __('Buscar por nombre o email...') }}"
                />
            </div>
            <flux:select wire:model.live="filtroRol" class="sm:w-48">
                <option value="">{{ __('Todos los roles') }}</option>
                @foreach($this->roles as $r)
                    <option value="{{ $r->name }}">{{ ucfirst($r->name) }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="filtroEstado" class="sm:w-44">
                <option value="todos">{{ __('Todos los estados') }}</option>
                <option value="activos">{{ __('Activos') }}</option>
                <option value="desactivados">{{ __('Desactivados') }}</option>
            </flux:select>
            <flux:select wire:model.live="filtroPapelera" class="sm:w-44">
                <option value="admitidos">{{ __('Admitidos') }}</option>
                <option value="eliminados">{{ __('Eliminados') }}</option>
                <option value="todos">{{ __('Papelera + Admitidos') }}</option>
            </flux:select>
        </div>

        <div class="flex flex-col sm:flex-row items-end gap-3">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live="desde" type="date" label="{{ __('Desde') }}" class="w-full sm:w-40" />
                <flux:input wire:model.live="hasta" type="date" label="{{ __('Hasta') }}" class="w-full sm:w-40" />
            </div>
            <div class="flex-1 sm:text-right">
                <flux:button variant="ghost" wire:click="resetFiltros" icon="arrow-path">
                    {{ __('Limpiar Filtros') }}
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden flex flex-col">
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-3">{{ __('Usuario') }}</th>
                        <th class="p-3">{{ __('Email') }}</th>
                        <th class="p-3 text-center">{{ __('Rol') }}</th>
                        <th class="p-3 text-center">{{ __('Estado') }}</th>
                        <th class="p-3 text-center">{{ __('Registrado') }}</th>
                        @can('usuarios.editar')
                            <th class="p-3"></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->usuarios as $usuario)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors {{ $usuario->deleted_at ? 'opacity-50' : '' }}">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-700 dark:text-indigo-300 font-semibold text-xs">
                                        {{ $usuario->initials() }}
                                    </div>
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ $usuario->name }}</span>
                                    @if($usuario->id === auth()->id())
                                        <flux:badge color="indigo" size="sm">{{ __('Tú') }}</flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3 text-zinc-600 dark:text-zinc-400">{{ $usuario->email }}</td>
                            <td class="p-3 text-center">
                                @if($usuario->roles->isNotEmpty())
                                    <flux:badge color="purple">{{ ucfirst($usuario->roles->first()->name) }}</flux:badge>
                                @else
                                    <span class="text-zinc-400 text-xs">—</span>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                @if($usuario->deleted_at)
                                    <flux:badge color="red">{{ __('Eliminado') }}</flux:badge>
                                @elseif($usuario->activo)
                                    <flux:badge color="green">{{ __('Activo') }}</flux:badge>
                                @else
                                    <flux:badge color="orange">{{ __('Inactivo') }}</flux:badge>
                                @endif
                            </td>
                            <td class="p-3 text-center text-zinc-500 text-xs">
                                {{ $usuario->created_at->format('d/m/Y') }}
                            </td>
                            @can('usuarios.editar')
                                <td class="p-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @if($usuario->deleted_at)
                                            <flux:button
                                                variant="ghost"
                                                icon="arrow-path"
                                                size="sm"
                                                wire:click="restaurar({{ $usuario->id }})"
                                                wire:confirm="¿Restaurar a este usuario?"
                                                title="{{ __('Restaurar') }}"
                                            />
                                        @else
                                            <flux:button
                                                variant="ghost"
                                                icon="key"
                                                size="sm"
                                                wire:click="abrirCambiarPassword({{ $usuario->id }})"
                                                title="{{ __('Cambiar contraseña') }}"
                                            />
                                            <flux:button
                                                variant="ghost"
                                                icon="pencil-square"
                                                size="sm"
                                                href="{{ route('admin.usuarios.edit', $usuario->id) }}"
                                                wire:navigate
                                                title="{{ __('Editar') }}"
                                            />
                                            @if($usuario->id !== auth()->id())
                                                <flux:button
                                                    variant="ghost"
                                                    icon="{{ $usuario->activo ? 'pause-circle' : 'play-circle' }}"
                                                    size="sm"
                                                    class="{{ $usuario->activo ? 'text-orange-500 hover:text-orange-600' : 'text-green-500 hover:text-green-600' }}"
                                                    wire:click="toggleActivo({{ $usuario->id }})"
                                                    wire:confirm="{{ $usuario->activo ? '¿Desactivar este usuario?' : '¿Activar este usuario?' }}"
                                                    title="{{ $usuario->activo ? __('Desactivar') : __('Activar') }}"
                                                />
                                                <flux:button
                                                    variant="ghost"
                                                    icon="trash"
                                                    size="sm"
                                                    class="text-red-500 hover:text-red-600"
                                                    wire:click="eliminar({{ $usuario->id }})"
                                                    wire:confirm="¿Eliminar este usuario? Podrás restaurarlo después."
                                                    title="{{ __('Eliminar (soft delete)') }}"
                                                />
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-zinc-500">
                                <flux:icon.users class="size-10 mx-auto mb-2 opacity-30" />
                                {{ __('No se encontraron usuarios.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->usuarios->hasPages())
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                <div class="w-full sm:w-auto">
                    {{ $this->usuarios->links() }}
                </div>
                <div class="hidden sm:flex items-center gap-2 text-sm text-zinc-500">
                    <span>{{ __('Mostrar') }}</span>
                    <flux:select wire:model.live="perPage" class="w-20">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </flux:select>
                </div>
            </div>
        @else
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between text-xs text-zinc-400">
                <span>{{ $this->usuarios->total() }} {{ __('usuario(s) encontrado(s)') }}</span>
                @if($this->usuarios->total() > 0)
                <div class="hidden sm:flex items-center gap-2 text-sm text-zinc-500">
                    <span>{{ __('Mostrar') }}</span>
                    <flux:select wire:model.live="perPage" class="w-20">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </flux:select>
                </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Modal Cambiar Contraseña --}}
    <flux:modal name="cambiar-password" class="w-full max-w-md">
        <flux:heading>{{ __('Cambiar Contraseña') }}</flux:heading>
        <flux:subheading>{{ __('Ingresa la nueva contraseña para este usuario.') }}</flux:subheading>

        <form wire:submit.prevent="cambiarPassword" class="mt-4 space-y-4">
            <flux:field>
                <flux:label>{{ __('Nueva contraseña') }}</flux:label>
                <flux:input wire:model="nueva_password" type="password" placeholder="Mínimo 8 caracteres" />
                <flux:error name="nueva_password" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Confirmar contraseña') }}</flux:label>
                <flux:input wire:model="nueva_password_confirmation" type="password" placeholder="Repite la nueva contraseña" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" icon="lock-closed">
                    {{ __('Actualizar Contraseña') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
