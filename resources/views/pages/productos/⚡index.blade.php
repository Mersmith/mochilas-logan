<?php

use App\Models\Producto;
use App\Models\TipoProducto;
use App\Models\Marca;
use App\Models\Categoria;
use App\Exports\ProductosExport;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Flux\Flux;

new #[Title('Catálogo de Productos')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filtroEstado = 'todos';

    #[Url]
    public string $filtroPapelera = 'admitidos';

    #[Url]
    public string $filtroTipo = '';

    #[Url]
    public string $filtroMarca = '';

    #[Url]
    public string $filtroCategoria = '';

    #[Url]
    public string $filtroEmpaque = '';

    #[Url]
    public string $filtroDescuento = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public int $perPage = 10;

    public function updating($property)
    {
        if (in_array($property, ['search', 'filtroEstado', 'filtroPapelera', 'desde', 'hasta', 'perPage', 'filtroTipo', 'filtroMarca', 'filtroCategoria', 'filtroEmpaque', 'filtroDescuento'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'filtroEstado', 'filtroPapelera', 'desde', 'hasta', 'filtroTipo', 'filtroMarca', 'filtroCategoria', 'filtroEmpaque', 'filtroDescuento']);
        $this->perPage = 10;
        $this->resetPage();
    }

    protected function getBaseQuery()
    {
        $query = Producto::query()
            ->with(['tipoProducto', 'marca', 'categoria', 'variacions.inventarios'])
            ->when($this->search, fn($q) => $q->where('nombre', 'like', '%' . $this->search . '%')
                                               ->orWhere('slug', 'like', '%' . $this->search . '%'))
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

        if ($this->filtroTipo) {
            $query->where('tipo_producto_id', $this->filtroTipo);
        }
        if ($this->filtroMarca) {
            $query->where('marca_id', $this->filtroMarca);
        }
        if ($this->filtroCategoria) {
            $query->where('categoria_id', $this->filtroCategoria);
        }
        if ($this->filtroEmpaque === 'si') {
            $query->has('empaques');
        } elseif ($this->filtroEmpaque === 'no') {
            $query->doesntHave('empaques');
        }
        if ($this->filtroDescuento === 'si') {
            $query->has('descuentos');
        } elseif ($this->filtroDescuento === 'no') {
            $query->doesntHave('descuentos');
        }

        return $query;
    }

    #[Computed]
    public function productos()
    {
        return $this->getBaseQuery()->paginate($this->perPage);
    }

    #[Computed]
    public function tiposProducto()
    {
        return TipoProducto::orderBy('nombre')->get();
    }

    #[Computed]
    public function marcas()
    {
        return Marca::orderBy('nombre')->get();
    }

    #[Computed]
    public function categorias()
    {
        return Categoria::orderBy('nombre')->get();
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
        if (!auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $producto = Producto::withTrashed()->findOrFail($id);

        if ($producto->trashed()) {
            $producto->forceDelete();
            Flux::toast(variant: 'success', text: __('Eliminado permanentemente.'));
        } else {
            $producto->delete();
            Flux::toast(variant: 'success', text: __('Enviado a la papelera.'));
        }
    }

    public function restaurar(int $id): void
    {
        if (!auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $producto = Producto::withTrashed()->findOrFail($id);
        $producto->restore();

        Flux::toast(variant: 'success', text: __('Restaurado correctamente.'));
    }

    public function exportarTodos()
    {
        $query = Producto::query()->with(['tipoProducto', 'marca', 'categoria'])->orderBy('nombre', 'asc');
        return Excel::download(new ProductosExport($query), 'todos_los_productos.xlsx');
    }

    public function exportarFiltrados()
    {
        $query = $this->getBaseQuery();
        return Excel::download(new ProductosExport($query), 'productos_filtrados.xlsx');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Catálogo de Productos') }}</flux:heading>
            <flux:subheading>{{ __('Administra los productos, sus variaciones y precios.') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:dropdown>
                <flux:button class="!bg-emerald-600 !text-white hover:!bg-emerald-700 border-none" icon="arrow-down-tray">{{ __('Exportar') }}</flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportarTodos" icon="document-text">{{ __('Todos') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportarFiltrados" icon="funnel">{{ __('Filtrados') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            @can('productos.crear')
                <flux:button variant="primary" icon="plus" href="{{ route('admin.productos.create') }}" wire:navigate>
                    {{ __('Nuevo Producto') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar por nombre o slug...') }}" />
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

        <div class="flex flex-col sm:flex-row gap-3">
            <flux:select wire:model.live="filtroTipo" class="flex-1">
                <option value="">{{ __('Todos los Tipos') }}</option>
                @foreach($this->tiposProducto as $tipo)
                    <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="filtroMarca" class="flex-1">
                <option value="">{{ __('Todas las Marcas') }}</option>
                @foreach($this->marcas as $marca)
                    <option value="{{ $marca->id }}">{{ $marca->nombre }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="filtroCategoria" class="flex-1">
                <option value="">{{ __('Todas las Categorías') }}</option>
                @foreach($this->categorias as $categoria)
                    <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex flex-col sm:flex-row items-end gap-3 justify-between">
            <div class="flex flex-col md:flex-row gap-3 flex-1 w-full">
                <flux:select wire:model.live="filtroEmpaque" class="w-full md:w-44">
                    <option value="">{{ __('Empaques: Todos') }}</option>
                    <option value="si">{{ __('Con empaque') }}</option>
                    <option value="no">{{ __('Sin empaque') }}</option>
                </flux:select>
                <flux:select wire:model.live="filtroDescuento" class="w-full md:w-44">
                    <option value="">{{ __('Descuento: Todos') }}</option>
                    <option value="si">{{ __('Con descuento') }}</option>
                    <option value="no">{{ __('Sin descuento') }}</option>
                </flux:select>
                <flux:input wire:model.live="desde" type="date" class="w-full md:w-40" />
                <flux:input wire:model.live="hasta" type="date" class="w-full md:w-40" />
            </div>
            <div class="mt-4 sm:mt-0 w-full sm:w-auto text-right">
                <flux:button class="!bg-blue-600 !text-white hover:!bg-blue-700 border-none w-full sm:w-auto" wire:click="resetFiltros" icon="arrow-path">
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
                        <th class="p-3">{{ __('Imagen') }}</th>
                        <th class="p-3">{{ __('Nombre') }}</th>
                        <th class="p-3">{{ __('Categoría/Marca') }}</th>
                        <th class="p-3 text-center">{{ __('Variaciones') }}</th>
                        <th class="p-3 text-center">{{ __('Stock') }}</th>
                        <th class="p-3 text-center">{{ __('Estado') }}</th>
                        @can('productos.editar')
                            <th class="p-3"></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->productos as $producto)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors {{ $producto->trashed() ? 'opacity-50' : '' }}">
                            <td class="p-3">
                                @if($producto->getFirstMediaUrl('imagen_principal', 'thumb'))
                                    <img src="{{ $producto->getFirstMediaUrl('imagen_principal', 'thumb') }}" alt="{{ $producto->nombre }}" class="w-12 h-12 object-cover rounded-md border border-zinc-200 dark:border-zinc-700">
                                @else
                                    <div class="w-12 h-12 bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center rounded-md border border-zinc-200 dark:border-zinc-700">
                                        <flux:icon.photo class="size-6 text-zinc-400" />
                                    </div>
                                @endif
                            </td>
                            <td class="p-3 font-medium text-zinc-900 dark:text-white">
                                {{ $producto->nombre }}
                                <div class="text-xs font-normal text-zinc-500">{{ $producto->slug }}</div>
                            </td>
                            <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                <div>{{ $producto->categoria->nombre ?? '-' }}</div>
                                <div class="text-xs text-zinc-500">{{ $producto->marca->nombre ?? '-' }}</div>
                            </td>
                            <td class="p-3 text-center">
                                <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 py-1 px-2 rounded-full text-xs font-bold">
                                    {{ $producto->variacions->count() }}
                                </span>
                            </td>
                            <td class="p-3 text-center font-bold text-zinc-700 dark:text-zinc-300">
                                {{ $producto->variacions->flatMap->inventarios->sum('stock_base') }}
                            </td>
                            <td class="p-3 text-center">
                                @if($producto->activo)
                                    <div class="flex justify-center" title="{{ __('Activo') }}">
                                        <flux:icon.check-circle class="size-5 text-emerald-500" />
                                    </div>
                                @else
                                    <div class="flex justify-center" title="{{ __('Desactivado') }}">
                                        <flux:icon.pause-circle class="size-5 text-amber-500" />
                                    </div>
                                @endif
                            </td>
                            @can('productos.editar')
                                <td class="p-3">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($producto->trashed())
                                            <flux:button variant="ghost" icon="arrow-path" size="sm"
                                                wire:click.prevent="restaurar({{ $producto->id }})"
                                                wire:confirm="¿Está seguro de restaurar este registro?" />
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarEliminacion({{ $producto->id }}, true)" />
                                        @else
                                            <flux:button variant="ghost" icon="pencil-square" size="sm"
                                                href="{{ route('admin.productos.edit', $producto->id) }}" wire:navigate />
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarEliminacion({{ $producto->id }})" />
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('productos.editar') ? 7 : 6 }}"
                                class="text-center py-8 text-zinc-500">
                                {{ __('No hay registros que coincidan con tu búsqueda.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->productos->hasPages())
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                <div class="w-full sm:w-auto">
                    {{ $this->productos->links() }}
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
                <span>{{ $this->productos->total() }} {{ __('registro(s) encontrado(s)') }}</span>
            </div>
        @endif
    </div>

    <x-modal-eliminar name="modal-eliminar-soft" />
    <x-modal-eliminar name="modal-eliminar-force" title="¿Eliminar permanentemente?"
        description="Esta acción es irreversible y eliminará el registro de la base de datos de forma permanente."
        :isPermanent="true" action="ejecutarEliminacionPermanente" />
</div>
