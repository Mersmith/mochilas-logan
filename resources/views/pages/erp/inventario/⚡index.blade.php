<?php

use App\Models\Inventario;
use App\Models\Almacen;
use App\Models\AtributoValor;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Flux\Flux;
use App\Exports\InventariosExport;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Control de Inventario')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $almacen_id = '';

    #[Url]
    public string $atributo_valor_id = '';

    #[Url]
    public string $marca_id = '';

    #[Url]
    public string $categoria_id = '';

    #[Url]
    public string $tipo_producto_id = '';

    #[Url]
    public string $stock_min = '';

    #[Url]
    public string $stock_max = '';

    #[Url]
    public int $perPage = 20;

    public function updating($property)
    {
        if (in_array($property, ['search', 'almacen_id', 'atributo_valor_id', 'marca_id', 'categoria_id', 'tipo_producto_id', 'stock_min', 'stock_max', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'almacen_id', 'atributo_valor_id', 'marca_id', 'categoria_id', 'tipo_producto_id', 'stock_min', 'stock_max']);
        $this->perPage = 20;
        $this->resetPage();
    }

    private function buildQuery()
    {
        $query = Inventario::with(['variacion.producto', 'variacion.valores.atributo', 'almacen'])
            ->when($this->search, function ($q) {
                $q->whereHas('variacion', function ($vq) {
                    $vq->where('sku', 'like', '%' . $this->search . '%')
                       ->orWhereHas('producto', function ($pq) {
                           $pq->where('nombre', 'like', '%' . $this->search . '%');
                       });
                });
            });

        if ($this->almacen_id) {
            $query->where('almacen_id', $this->almacen_id);
        }

        if ($this->atributo_valor_id) {
            $query->whereHas('variacion.valores', function ($q) {
                $q->where('atributo_valores.id', $this->atributo_valor_id);
            });
        }

        if ($this->marca_id || $this->categoria_id || $this->tipo_producto_id) {
            $query->whereHas('variacion.producto', function ($pq) {
                if ($this->marca_id) {
                    $pq->where('marca_id', $this->marca_id);
                }
                if ($this->categoria_id) {
                    $pq->where('categoria_id', $this->categoria_id);
                }
                if ($this->tipo_producto_id) {
                    $pq->where('tipo_producto_id', $this->tipo_producto_id);
                }
            });
        }

        if ($this->stock_min !== '') {
            $query->where('stock_base', '>=', $this->stock_min);
        }

        if ($this->stock_max !== '') {
            $query->where('stock_base', '<=', $this->stock_max);
        }

        return $query;
    }

    #[Computed]
    public function inventarios()
    {
        return $this->buildQuery()->paginate($this->perPage);
    }

    public function exportarTodos()
    {
        $query = Inventario::with(['variacion.producto', 'variacion.valores.atributo', 'almacen'])->orderBy('almacen_id');
        return Excel::download(new InventariosExport($query), 'todos_los_inventarios.xlsx');
    }

    public function exportarFiltrados()
    {
        $query = $this->buildQuery();
        return Excel::download(new InventariosExport($query), 'inventarios_filtrados.xlsx');
    }

    #[Computed]
    public function almacenes()
    {
        return Almacen::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function marcas()
    {
        return \App\Models\Marca::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function categorias()
    {
        return \App\Models\Categoria::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function tiposProducto()
    {
        return \App\Models\TipoProducto::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function atributoValores()
    {
        return AtributoValor::with('atributo')->get()->map(function($val) {
            return [
                'id' => $val->id,
                'nombre' => $val->atributo->nombre . ': ' . $val->valor
            ];
        })->sortBy('nombre')->values();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Control de Inventario') }}</flux:heading>
            <flux:subheading>{{ __('Consulta el stock real de cada variación o SKU por almacén.') }}</flux:subheading>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-[200px]">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar por SKU o producto...') }}" />
            </div>
            <flux:select wire:model.live="almacen_id" class="sm:w-44">
                <option value="">{{ __('Todos los almacenes') }}</option>
                @foreach($this->almacenes as $a)
                    <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="atributo_valor_id" class="sm:w-44">
                <option value="">{{ __('Todos los atributos') }}</option>
                @foreach($this->atributoValores as $av)
                    <option value="{{ $av['id'] }}">{{ $av['nombre'] }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="tipo_producto_id" class="sm:w-40">
                <option value="">{{ __('Tipos de Prod.') }}</option>
                @foreach($this->tiposProducto as $tp)
                    <option value="{{ $tp->id }}">{{ $tp->nombre }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="categoria_id" class="sm:w-40">
                <option value="">{{ __('Categorías') }}</option>
                @foreach($this->categorias as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="marca_id" class="sm:w-40">
                <option value="">{{ __('Marcas') }}</option>
                @foreach($this->marcas as $mar)
                    <option value="{{ $mar->id }}">{{ $mar->nombre }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex flex-col sm:flex-row items-end gap-3 justify-between">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live="stock_min" type="number" label="{{ __('Stock Mín.') }}" class="w-full sm:w-32" />
                <flux:input wire:model.live="stock_max" type="number" label="{{ __('Stock Máx.') }}" class="w-full sm:w-32" />
            </div>
            <div class="mt-4 sm:mt-0">
            </div>
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
                        <th class="p-3">{{ __('Imagen') }}</th>
                        <th class="p-3">{{ __('Producto / SKU') }}</th>
                        <th class="p-3">{{ __('Atributos') }}</th>
                        <th class="p-3">{{ __('Almacén') }}</th>
                        <th class="p-3 text-center">{{ __('Stock Base') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->inventarios as $inv)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="p-3">
                                @if($inv->variacion->producto->getFirstMediaUrl('imagen_principal', 'thumb'))
                                    <img src="{{ $inv->variacion->producto->getFirstMediaUrl('imagen_principal', 'thumb') }}" alt="{{ $inv->variacion->producto->nombre }}" class="w-12 h-12 object-cover rounded-md border border-zinc-200 dark:border-zinc-700">
                                @else
                                    <div class="w-12 h-12 bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center rounded-md border border-zinc-200 dark:border-zinc-700">
                                        <flux:icon.photo class="size-6 text-zinc-400" />
                                    </div>
                                @endif
                            </td>
                            <td class="p-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $inv->variacion->producto->nombre }}</div>
                                <div class="text-xs text-zinc-500 font-mono">{{ $inv->variacion->sku }}</div>
                            </td>
                            <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($inv->variacion->valores as $val)
                                        <span class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 px-2 py-0.5 rounded text-xs">
                                            {{ $val->atributo->nombre }}: {{ $val->valor }}
                                        </span>
                                    @empty
                                        <span class="text-zinc-400 text-xs">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="p-3 text-zinc-600 dark:text-zinc-300">
                                {{ $inv->almacen->nombre }}
                            </td>
                            <td class="p-3 text-center">
                                <span class="font-bold {{ $inv->stock_base <= $inv->stock_minimo ? 'text-red-600 dark:text-red-400' : 'text-zinc-700 dark:text-zinc-200' }}">
                                    {{ $inv->stock_base }}
                                </span>
                                @if($inv->stock_base <= $inv->stock_minimo)
                                    <div class="text-[10px] text-red-500">{{ __('Stock Bajo') }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-12 text-zinc-400">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.face-smile class="size-8 text-zinc-300" />
                                    <span>{{ $search ? __('No se encontraron resultados para ":query"', ['query' => $search]) : __('No hay inventario registrado.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <!-- Pie de tabla: Paginación + Info -->
        <div class="px-4 py-4 border-t border-zinc-200 dark:border-zinc-700">
            @if($this->inventarios->hasPages())
                {{ $this->inventarios->links() }}
            @else
                <p class="text-xs text-zinc-400">
                    {{ __(':total registro(s)', ['total' => $this->inventarios->total()]) }}
                </p>
            @endif
        </div>
    </div>
</div>
