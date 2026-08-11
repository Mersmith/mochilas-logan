<?php

use App\Models\TipoDocumento;
use App\Exports\TipoDocumentosExport;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Flux\Flux;

new #[Title('Tipos de Documento')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filtroEstado = 'todos';

    #[Url]
    public string $filtroPapelera = 'admitidos';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public int $perPage = 10;

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

    protected function getBaseQuery()
    {
        $query = TipoDocumento::query()
            ->withCount(['series'])
            ->when($this->search, fn($q) => $q->where('nombre', 'like', '%' . $this->search . '%'))
            ->orderBy('nombre', 'asc');

        if ($this->filtroEstado === 'activos') {
            $query->where('activo', true);
        } elseif ($this->filtroEstado === 'desactivados') {
            $query->where('activo', false);
        }

        if ($this->filtroPapelera === 'eliminados') {
            $query->onlyTrashed();
        } elseif ($this->filtroPapelera === 'todos') {
            $query->withTrashed();
        }

        $query->when($this->desde, fn($q) => $q->whereDate('created_at', '>=', $this->desde))
            ->when($this->hasta, fn($q) => $q->whereDate('created_at', '<=', $this->hasta));

        return $query;
    }

    #[Computed]
    public function tiposDocumento()
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
        if (!auth()->user()->can('tipos-documento.editar')) {
            abort(403);
        }

        $tipoDocumento = TipoDocumento::withTrashed()->findOrFail($id);

        if ($tipoDocumento->series()->count() > 0) {
            Flux::toast(variant: 'danger', text: __('No se puede eliminar porque tiene series asignadas.'));
            return;
        }

        if ($tipoDocumento->trashed()) {
            $tipoDocumento->forceDelete();
            Flux::toast(variant: 'success', text: __('Eliminado permanentemente.'));
        } else {
            $tipoDocumento->delete();
            Flux::toast(variant: 'success', text: __('Enviado a la papelera.'));
        }
    }

    public function restaurar(int $id): void
    {
        if (!auth()->user()->can('tipos-documento.editar')) {
            abort(403);
        }

        $tipoDocumento = TipoDocumento::withTrashed()->findOrFail($id);
        $tipoDocumento->restore();

        Flux::toast(variant: 'success', text: __('Restaurado correctamente.'));
    }

    public function exportarTodos()
    {
        $query = TipoDocumento::query()->orderBy('nombre', 'asc');
        return Excel::download(new TipoDocumentosExport($query), 'todos_los_tipos_documento.xlsx');
    }

    public function exportarFiltrados()
    {
        $query = $this->getBaseQuery();
        return Excel::download(new TipoDocumentosExport($query), 'tipos_documento_filtrados.xlsx');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Tipos de Documento') }}</flux:heading>
            <flux:subheading>{{ __('Administra los tipos de documentos como Boletas, Facturas, etc.') }}</flux:subheading>
        </div>
        @can('tipos-documento.editar')
                <flux:button variant="primary" icon="plus" href="{{ route('admin.tipos-documento.create') }}" wire:navigate>
                    {{ __('Nuevo Tipo de Documento') }}
                </flux:button>
            @endcan
        
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar por nombre...') }}" />
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
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden flex flex-col">
                <!-- Cabecera de tabla: Acciones + PerPage -->
        <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30">
            <flux:dropdown>
                <flux:button class="!bg-emerald-600 !text-white hover:!bg-emerald-700 border-none" size="sm" icon="arrow-down-tray">{{ __('Exportar') }}</flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportarFiltrados" icon="funnel">{{ __('Resultados filtrados') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportarTodos" icon="document-text">{{ __('Todos los registros') }}</flux:menu.item>
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

        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-3">{{ __('Nombre') }}</th>
                        <th class="p-3 text-center">{{ __('Código SUNAT') }}</th>
                        <th class="p-3 text-center">{{ __('Series') }}</th>
                        <th class="p-3 text-center">{{ __('Estado') }}</th>
                        <th class="p-3 text-center">{{ __('Creado') }}</th>
                        <th class="p-3 text-center">{{ __('Registro') }}</th>
                        @can('tipos-documento.editar')
                            <th class="p-3"></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->tiposDocumento as $tipoDocumento)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors {{ $tipoDocumento->trashed() ? 'opacity-50' : '' }}">
                            <td class="p-3 font-medium text-zinc-900 dark:text-white">
                                {{ $tipoDocumento->nombre }}
                            </td>
                            <td class="p-3 text-center text-zinc-600 dark:text-zinc-400">
                                {{ $tipoDocumento->codigo_sunat ?: '-' }}
                            </td>
                            <td class="p-3 text-center text-zinc-600 dark:text-zinc-400">
                                {{ $tipoDocumento->series_count ?? 0 }}
                            </td>
                            <td class="p-3 text-center">
                                @if($tipoDocumento->activo)
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
                                {{ $tipoDocumento->created_at->format('d/m/Y') }}
                            </td>
                            <td class="p-3 text-center">
                                @if($tipoDocumento->trashed())
                                    <div class="flex justify-center" title="{{ __('Eliminado') }}">
                                        <flux:icon.trash class="size-5 text-red-500" />
                                    </div>
                                @else
                                    <div class="flex justify-center" title="{{ __('Admitido') }}">
                                        <flux:icon.check-badge class="size-5 text-blue-500" />
                                    </div>
                                @endif
                            </td>
                            @can('tipos-documento.editar')
                                <td class="p-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($tipoDocumento->trashed())
                                            <flux:button variant="ghost" icon="arrow-path" size="sm"
                                                wire:click.prevent="restaurar({{ $tipoDocumento->id }})"
                                                wire:confirm="¿Está seguro de restaurar este registro?" />
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarEliminacion({{ $tipoDocumento->id }}, true)" />
                                        @else
                                            <flux:button variant="ghost" icon="pencil-square" size="sm"
                                                href="{{ route('admin.tipos-documento.edit', $tipoDocumento->id) }}" wire:navigate />
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarEliminacion({{ $tipoDocumento->id }})" />
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('tipos-documento.editar') ? 7 : 6 }}"
                                class="text-center py-12 text-zinc-400">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.face-smile class="size-8 text-zinc-300" />
                                    <span>{{ $search ? __('No se encontraron resultados para ":query"', ['query' => $search]) : __('No hay registros.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pie de tabla: Paginación + Info -->
        <div class="px-4 py-4 border-t border-zinc-200 dark:border-zinc-700">
            @if($this->tiposDocumento->hasPages())
                {{ $this->tiposDocumento->links() }}
            @else
                <p class="text-xs text-zinc-400">
                    {{ __(':total registro(s)', ['total' => $this->tiposDocumento->total()]) }}
                </p>
            @endif
        </div>
    </div>

    <x-modal-eliminar name="modal-eliminar-soft" />
    <x-modal-eliminar name="modal-eliminar-force" title="¿Eliminar permanentemente?"
        description="Esta acción es irreversible y eliminará el registro de la base de datos de forma permanente."
        :isPermanent="true" action="ejecutarEliminacionPermanente" />
</div>
