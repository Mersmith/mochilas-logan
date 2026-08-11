<?php

use App\Models\Sede;
use App\Exports\SedesExport;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Flux\Flux;

new #[Title('Gestión de Sedes')] class extends Component {
    use WithPagination;

    // ── Filtros ──────────────────────────────────────────
    #[Url(as: 'q')]
    public string $search = '';

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

    /**
     * Resetea la paginación cuando cambia algún filtro
     */
    public function updating($property)
    {
        if (in_array($property, ['search', 'filtroEstado', 'filtroPapelera', 'desde', 'hasta', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'filtroEstado', 'filtroPapelera', 'desde', 'hasta']);
        $this->perPage = 10;
        $this->resetPage();
    }

    /**
     * Devuelve el query builder base con los filtros aplicados.
     */
    protected function getBaseQuery()
    {
        $query = Sede::withCount(['almacenes', 'series'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nombre', 'like', '%' . $this->search . '%')
                        ->orWhere('direccion', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('nombre', 'asc');

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

    #[Computed]
    public function sedes()
    {
        return $this->getBaseQuery()->paginate($this->perPage);
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
        if (!auth()->user()->can('sedes.editar')) {
            abort(403, 'No tienes permiso para eliminar sedes.');
        }

        $sede = Sede::withTrashed()->findOrFail($id);

        // Prevent deletion if it has relations like almacenes or series
        if ($sede->almacenes()->count() > 0 || $sede->series()->count() > 0) {
            Flux::toast(variant: 'danger', text: __('No se puede eliminar la sede porque tiene almacenes o series asignados.'));
            return;
        }

        if ($sede->trashed()) {
            $sede->forceDelete();
            Flux::toast(variant: 'success', text: __('Sede eliminada permanentemente.'));
        } else {
            $sede->delete();
            Flux::toast(variant: 'success', text: __('Sede enviada a la papelera (soft delete).'));
        }
    }

    public function restaurar(int $id): void
    {
        if (!auth()->user()->can('sedes.editar')) {
            abort(403, 'No tienes permiso para restaurar sedes.');
        }

        $sede = Sede::withTrashed()->findOrFail($id);
        $sede->restore();

        Flux::toast(variant: 'success', text: __('Sede restaurada correctamente.'));
    }

    // ── Exportaciones ──────────────────────────────────────────

    public function exportarTodos()
    {
        $query = Sede::withCount(['almacenes', 'series'])->orderBy('nombre', 'asc');
        return Excel::download(new SedesExport($query), 'todas_las_sedes.xlsx');
    }

    public function exportarFiltrados()
    {
        $query = $this->getBaseQuery();
        return Excel::download(new SedesExport($query), 'sedes_filtradas.xlsx');
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Sedes') }}</flux:heading>
            <flux:subheading>{{ __('Administra las sedes físicas de la empresa.') }}</flux:subheading>
        </div>
        @can('sedes.editar')
            <flux:button variant="primary" icon="plus" href="{{ route('admin.sedes.create') }}" wire:navigate>
                {{ __('Nueva Sede') }}
            </flux:button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div
        class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="{{ __('Buscar por nombre o dirección...') }}" />
            </div>
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
                    <flux:menu.item wire:click="exportarTodos" icon="document-text">{{ __('Todas las sedes') }}</flux:menu.item>
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
                        <th class="p-3">{{ __('Nombre') }}</th>
                        <th class="p-3">{{ __('Dirección') }}</th>
                        <th class="p-3 text-center">{{ __('Almacenes') }}</th>
                        <th class="p-3 text-center">{{ __('Estado') }}</th>
                        <th class="p-3 text-center">{{ __('Creado') }}</th>
                        <th class="p-3 text-center">{{ __('Registro') }}</th>
                        @can('sedes.editar')
                            <th class="p-3"></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->sedes as $sede)
                        <tr
                            class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors {{ $sede->trashed() ? 'opacity-50' : '' }}">
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
                                {{ $sede->created_at->format('d/m/Y') }}
                            </td>
                            <td class="p-3 text-center">
                                @if($sede->trashed())
                                    <div class="flex justify-center" title="{{ __('Eliminada') }}">
                                        <flux:icon.trash class="size-5 text-red-500" />
                                    </div>
                                @else
                                    <div class="flex justify-center" title="{{ __('Admitida') }}">
                                        <flux:icon.check-badge class="size-5 text-blue-500" />
                                    </div>
                                @endif
                            </td>
                            @can('sedes.editar')
                                <td class="p-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($sede->trashed())
                                            <flux:button variant="ghost" icon="arrow-path" size="sm"
                                                wire:click.prevent="restaurar({{ $sede->id }})"
                                                wire:confirm="¿Está seguro de restaurar esta sede?" />
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarEliminacion({{ $sede->id }}, true)" />
                                        @else
                                            <flux:button variant="ghost" icon="pencil-square" size="sm"
                                                href="{{ route('admin.sedes.edit', $sede->id) }}" wire:navigate />
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarEliminacion({{ $sede->id }})" />
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('sedes.editar') ? 7 : 6 }}"
                                class="text-center py-12 text-zinc-400">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.face-smile class="size-8 text-zinc-300" />
                                    <span>{{ $search ? __('No se encontraron resultados para ":query"', ['query' => $search]) : __('No hay sedes registradas.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pie de tabla: Paginación + Info -->
        <div class="px-4 py-4 border-t border-zinc-200 dark:border-zinc-700">
            @if($this->sedes->hasPages())
                {{ $this->sedes->links() }}
            @else
                <p class="text-xs text-zinc-400">
                    {{ __(':total registro(s)', ['total' => $this->sedes->total()]) }}
                </p>
            @endif
        </div>
    </div>

    <!-- Modals de confirmación -->
    <x-modal-eliminar name="modal-eliminar-soft" />
    <x-modal-eliminar name="modal-eliminar-force" title="¿Eliminar permanentemente?"
        description="Esta acción es irreversible y eliminará el registro de la base de datos de forma permanente."
        :isPermanent="true" action="ejecutarEliminacionPermanente" />
</div>