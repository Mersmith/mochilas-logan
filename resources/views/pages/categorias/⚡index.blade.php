<?php

use App\Models\Categoria;
use App\Exports\CategoriasExport;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Flux\Flux;

new #[Title('Categorías')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filtroEstado = 'todos';

    #[Url]
    public string $categoria_padre_id = '';

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
        if (in_array($property, ['search', 'filtroEstado', 'categoria_padre_id', 'filtroPapelera', 'desde', 'hasta', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'filtroEstado', 'categoria_padre_id', 'filtroPapelera', 'desde', 'hasta']);
        $this->perPage = 10;
        $this->resetPage();
    }

    protected function getBaseQuery()
    {
        $query = Categoria::query()
            ->with(['categoriaPadre'])
            ->when($this->search, fn($q) => $q->where('nombre', 'like', '%' . $this->search . '%')
                                               ->orWhere('codigo', 'like', '%' . $this->search . '%'))
            ->orderBy('orden', 'asc')
            ->orderBy('nombre', 'asc');

        if ($this->filtroEstado === 'activos') {
            $query->where('activo', true);
        } elseif ($this->filtroEstado === 'desactivados') {
            $query->where('activo', false);
        }

        if ($this->categoria_padre_id === 'root') {
            $query->whereNull('categoria_padre_id');
        } elseif ($this->categoria_padre_id) {
            $query->where('categoria_padre_id', $this->categoria_padre_id);
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
    public function categorias()
    {
        return $this->getBaseQuery()->paginate($this->perPage);
    }

    #[Computed]
    public function categoriasPadre()
    {
        return Categoria::whereNull('categoria_padre_id')->where('activo', true)->orderBy('nombre')->get();
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
        if (!auth()->user()->can('categorias.editar')) {
            abort(403);
        }

        $categoria = Categoria::withTrashed()->findOrFail($id);

        if ($categoria->trashed()) {
            $categoria->forceDelete();
            Flux::toast(variant: 'success', text: __('Eliminado permanentemente.'));
        } else {
            $categoria->delete();
            Flux::toast(variant: 'success', text: __('Enviado a la papelera.'));
        }
    }

    public function restaurar(int $id): void
    {
        if (!auth()->user()->can('categorias.editar')) {
            abort(403);
        }

        $categoria = Categoria::withTrashed()->findOrFail($id);
        $categoria->restore();

        Flux::toast(variant: 'success', text: __('Restaurado correctamente.'));
    }

    public function exportarTodos()
    {
        $query = Categoria::query()->with(['categoriaPadre'])->orderBy('orden', 'asc');
        return Excel::download(new CategoriasExport($query), 'todas_las_categorias.xlsx');
    }

    public function exportarFiltrados()
    {
        $query = $this->getBaseQuery();
        return Excel::download(new CategoriasExport($query), 'categorias_filtradas.xlsx');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Categorías') }}</flux:heading>
            <flux:subheading>{{ __('Administra las categorías y subcategorías de los productos.') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:dropdown>
                <flux:button class="!bg-emerald-600 !text-white hover:!bg-emerald-700 border-none" icon="arrow-down-tray">{{ __('Exportar') }}</flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportarTodos" icon="document-text">{{ __('Todos') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportarFiltrados" icon="funnel">{{ __('Filtrados') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            @can('categorias.editar')
                <flux:button variant="primary" icon="plus" href="{{ route('admin.categorias.create') }}" wire:navigate>
                    {{ __('Nueva Categoría') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-[200px]">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar por nombre o código...') }}" />
            </div>
            
            <flux:select wire:model.live="categoria_padre_id" class="sm:w-44">
                <option value="">{{ __('Cualquier jerarquía') }}</option>
                <option value="root">{{ __('Solo Categorías Principales') }}</option>
                @foreach($this->categoriasPadre as $cp)
                    <option value="{{ $cp->id }}">{{ __('Subcategorías de') }} {{ $cp->nombre }}</option>
                @endforeach
            </flux:select>
            
            <flux:select wire:model.live="filtroEstado" class="sm:w-40">
                <option value="todos">{{ __('Todos los estados') }}</option>
                <option value="activos">{{ __('Activos') }}</option>
                <option value="desactivados">{{ __('Desactivados') }}</option>
            </flux:select>
            
            <flux:select wire:model.live="filtroPapelera" class="sm:w-40">
                <option value="admitidos">{{ __('Admitidos') }}</option>
                <option value="eliminados">{{ __('Eliminados') }}</option>
                <option value="todos">{{ __('Todos') }}</option>
            </flux:select>
        </div>

        <div class="flex flex-col sm:flex-row items-end gap-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live="desde" type="date" label="{{ __('Desde') }}" class="w-full sm:w-40" />
                <flux:input wire:model.live="hasta" type="date" label="{{ __('Hasta') }}" class="w-full sm:w-40" />
            </div>
            <div class="flex-1 sm:text-right">
                <flux:button class="!bg-blue-600 !text-white hover:!bg-blue-700 border-none" wire:click="resetFiltros" icon="arrow-path">
                    {{ __('Limpiar Filtros') }}
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden flex flex-col">
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-3">{{ __('Nombre') }}</th>
                        <th class="p-3">{{ __('Padre') }}</th>
                        <th class="p-3">{{ __('Código') }}</th>
                        <th class="p-3 text-center">{{ __('Orden') }}</th>
                        <th class="p-3 text-center">{{ __('Estado') }}</th>
                        @can('categorias.editar')
                            <th class="p-3"></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->categorias as $categoria)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors {{ $categoria->trashed() ? 'opacity-50' : '' }}">
                            <td class="p-3 font-medium text-zinc-900 dark:text-white">
                                {{ $categoria->nombre }}
                            </td>
                            <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                {{ $categoria->categoriaPadre->nombre ?? '-' }}
                            </td>
                            <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                {{ $categoria->codigo ?: '-' }}
                            </td>
                            <td class="p-3 text-center text-zinc-600 dark:text-zinc-400 font-mono">
                                {{ $categoria->orden }}
                            </td>
                            <td class="p-3 text-center">
                                @if($categoria->activo)
                                    <div class="flex justify-center" title="{{ __('Activo') }}">
                                        <flux:icon.check-circle class="size-5 text-emerald-500" />
                                    </div>
                                @else
                                    <div class="flex justify-center" title="{{ __('Desactivado') }}">
                                        <flux:icon.pause-circle class="size-5 text-amber-500" />
                                    </div>
                                @endif
                            </td>
                            @can('categorias.editar')
                                <td class="p-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($categoria->trashed())
                                            <flux:button variant="ghost" icon="arrow-path" size="sm"
                                                wire:click.prevent="restaurar({{ $categoria->id }})"
                                                wire:confirm="¿Está seguro de restaurar este registro?" />
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarEliminacion({{ $categoria->id }}, true)" />
                                        @else
                                            <flux:button variant="ghost" icon="pencil-square" size="sm"
                                                href="{{ route('admin.categorias.edit', $categoria->id) }}" wire:navigate />
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarEliminacion({{ $categoria->id }})" />
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('categorias.editar') ? 6 : 5 }}"
                                class="text-center py-8 text-zinc-500">
                                {{ __('No hay registros que coincidan con tu búsqueda.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->categorias->hasPages())
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                <div class="w-full sm:w-auto">
                    {{ $this->categorias->links() }}
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
                <span>{{ $this->categorias->total() }} {{ __('registro(s) encontrado(s)') }}</span>
            </div>
        @endif
    </div>

    <x-modal-eliminar name="modal-eliminar-soft" />
    <x-modal-eliminar name="modal-eliminar-force" title="¿Eliminar permanentemente?"
        description="Esta acción es irreversible y eliminará el registro de la base de datos de forma permanente."
        :isPermanent="true" action="ejecutarEliminacionPermanente" />
</div>
