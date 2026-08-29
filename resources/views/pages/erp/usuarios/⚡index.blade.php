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
            ->withoutRole('cliente') // <-- Filtro clave para Personal Interno
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
        return Role::where('name', '!=', 'cliente')->orderBy('name')->get();
    }

    public ?int $idEliminar = null;

    public function confirmarEliminacion(int $id, bool $esPermanente = false): void
    {
        $this->idEliminar = $id;
        $this->modal($esPermanente ? 'modal-eliminar-force' : 'modal-eliminar-soft')->show();
    }

    public function ejecutarEliminacion(): void
    {
        $this->eliminar($this->idEliminar);
        $this->modal('modal-eliminar-soft')->close();
    }

    public function ejecutarEliminacionPermanente(): void
    {
        $this->eliminar($this->idEliminar);
        $this->modal('modal-eliminar-force')->close();
    }

    public function eliminar(int $id): void
    {
        if (! auth()->user()->can('usuarios.editar')) {
            abort(403, 'No tienes permiso para eliminar usuarios.');
        }

        $usuario = User::withTrashed()->findOrFail($id);

        if ($usuario->is_super_admin) {
            abort(403, 'Este usuario no puede ser modificado.');
        }

        if ($usuario->id === auth()->id()) {
            Flux::toast(variant: 'warning', text: __('No puedes eliminar tu propia cuenta.'));
            return;
        }

        if ($usuario->trashed()) {
            $usuario->forceDelete();
            Flux::toast(variant: 'success', text: __('Usuario eliminado permanentemente.'));
        } else {
            $usuario->delete();
            Flux::toast(variant: 'success', text: __('Usuario enviado a la papelera (soft delete).'));
        }
    }

    public function restaurar(int $id): void
    {
        if (! auth()->user()->can('usuarios.editar')) {
            abort(403, 'No tienes permiso para restaurar usuarios.');
        }

        $usuario = User::withTrashed()->findOrFail($id);
        $usuario->restore();

        Flux::toast(variant: 'success', text: __('Usuario restaurado correctamente.'));
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
        if (! auth()->user()->can('usuarios.editar')) {
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

        Flux::toast(variant: 'success', text: __('Contraseña actualizada correctamente.'));
        Flux::modal('cambiar-password')->close();
        $this->cambiarPass_usuario_id = null;
        $this->nueva_password = '';
        $this->nueva_password_confirmation = '';
    }

    // ── Exportaciones ──────────────────────────────────────────

    public function exportarTodos()
    {
        $query = User::with('roles')
            ->where('is_super_admin', false)
            ->withoutRole('cliente')
            ->orderBy('name');
        return Excel::download(new UsuariosExport($query), 'todos_los_usuarios.xlsx');
    }

    public function exportarFiltrados()
    {
        $query = $this->getBaseQuery();
        return Excel::download(new UsuariosExport($query), 'usuarios_filtrados.xlsx');
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Personal Interno') }}</flux:heading>
            <flux:subheading>{{ __('Administra los accesos de los trabajadores del sistema.') }}</flux:subheading>
        </div>
        @can('usuarios.editar')
            <flux:button variant="primary" icon="plus" href="{{ route('admin.usuarios.create') }}" wire:navigate>
                {{ __('Nuevo Usuario') }}
            </flux:button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div
        class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="{{ __('Buscar por nombre o email...') }}" />
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

        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
            <flux:input wire:model.live="desde" type="date" label="{{ __('Desde') }}" class="w-full sm:w-40" />
            <flux:input wire:model.live="hasta" type="date" label="{{ __('Hasta') }}" class="w-full sm:w-40" />
        </div>
    </div>

    <!-- Tabla -->
    <div
        class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden flex flex-col">

        <!-- Cabecera de tabla: Acciones + PerPage -->
        <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30">
            <flux:dropdown>
                <flux:button class="!bg-emerald-600 !text-white hover:!bg-emerald-700 border-none" size="sm" icon="arrow-down-tray">{{ __('Exportar') }}</flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportarFiltrados" icon="funnel">{{ __('Resultados filtrados') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportarTodos" icon="document-text">{{ __('Todos los usuarios') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            <flux:button size="sm" class="!bg-red-600 !text-white hover:!bg-red-700 border-none" wire:click="resetFiltros" icon="arrow-path">
                {{ __('Limpiar') }}
            </flux:button>

            <div class="flex items-center gap-2 text-sm text-zinc-500 ml-auto">
                <span class="hidden sm:inline">{{ __('Mostrar') }}</span>
                <flux:select wire:model.live="perPage" class="w-20">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </flux:select>
            </div>
        </div>

        <!-- Contenido de tabla -->
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr
                        class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-3">{{ __('Usuario') }}</th>
                        <th class="p-3">{{ __('Email') }}</th>
                        <th class="p-3 text-center">{{ __('Rol') }}</th>
                        <th class="p-3 text-center">{{ __('Estado') }}</th>
                        <th class="p-3 text-center">{{ __('Creado') }}</th>
                        <th class="p-3 text-center">{{ __('Registro') }}</th>
                        @can('usuarios.editar')
                            <th class="p-3"></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->usuarios as $usuario)
                        <tr
                            class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors {{ $usuario->trashed() ? 'opacity-50' : '' }}">
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
                                @if($usuario->activo)
                                    <div class="flex justify-center" title="{{ __('Activo') }}">
                                        <flux:icon.check-circle class="size-5 text-emerald-500" />
                                    </div>
                                @else
                                    <div class="flex justify-center" title="{{ __('Desactivado') }}">
                                        <flux:icon.pause-circle class="size-5 text-amber-500" />
                                    </div>
                                @endif
                            </td>
                            <td class="p-3 text-center text-zinc-600 dark:text-zinc-400">
                                {{ $usuario->created_at->format('d/m/Y') }}
                            </td>
                            <td class="p-3 text-center">
                                @if($usuario->trashed())
                                    <div class="flex justify-center" title="{{ __('Eliminado') }}">
                                        <flux:icon.trash class="size-5 text-red-500" />
                                    </div>
                                @else
                                    <div class="flex justify-center" title="{{ __('Admitido') }}">
                                        <flux:icon.check-badge class="size-5 text-blue-500" />
                                    </div>
                                @endif
                            </td>
                            @can('usuarios.editar')
                                <td class="p-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($usuario->trashed())
                                            <flux:button variant="ghost" icon="arrow-path" size="sm"
                                                wire:click.prevent="restaurar({{ $usuario->id }})"
                                                wire:confirm="¿Está seguro de restaurar este usuario?" />
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarEliminacion({{ $usuario->id }}, true)" />
                                        @else
                                            <flux:button variant="ghost" icon="key" size="sm"
                                                wire:click="abrirCambiarPassword({{ $usuario->id }})"
                                                title="{{ __('Cambiar contraseña') }}" />
                                            <flux:button variant="ghost" icon="pencil-square" size="sm"
                                                href="{{ route('admin.usuarios.edit', $usuario->id) }}" wire:navigate />
                                            @if($usuario->id !== auth()->id())
                                                <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                    wire:click.prevent="confirmarEliminacion({{ $usuario->id }})" />
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('usuarios.editar') ? 7 : 6 }}"
                                class="text-center py-12 text-zinc-400">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.face-smile class="size-8 text-zinc-300" />
                                    <span>{{ $search ? __('No se encontraron resultados para ":query"', ['query' => $search]) : __('No hay usuarios registrados.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pie de tabla: Paginación + Info -->
        <div class="px-4 py-4 border-t border-zinc-200 dark:border-zinc-700">
            @if($this->usuarios->hasPages())
                {{ $this->usuarios->links() }}
            @else
                <p class="text-xs text-zinc-400">
                    {{ __(':total registro(s)', ['total' => $this->usuarios->total()]) }}
                </p>
            @endif
        </div>
    </div>

    <!-- Modals de confirmación -->
    <x-modal-eliminar name="modal-eliminar-soft" />
    <x-modal-eliminar name="modal-eliminar-force" title="¿Eliminar permanentemente?"
        description="Esta acción es irreversible y eliminará el registro de la base de datos de forma permanente."
        :isPermanent="true" action="ejecutarEliminacionPermanente" />

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
