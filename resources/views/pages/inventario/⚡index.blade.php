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

new #[Title('Control de Inventario')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $almacen_id = '';

    #[Url]
    public string $atributo_valor_id = '';

    #[Url]
    public string $stock_min = '';

    #[Url]
    public string $stock_max = '';

    #[Url]
    public int $perPage = 20;

    public function updating($property)
    {
        if (in_array($property, ['search', 'almacen_id', 'atributo_valor_id', 'stock_min', 'stock_max', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'almacen_id', 'atributo_valor_id', 'stock_min', 'stock_max']);
        $this->perPage = 20;
        $this->resetPage();
    }

    #[Computed]
    public function inventarios()
    {
        $query = Inventario::with(['variacion.producto.media', 'variacion.valores.atributo', 'almacen'])
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

        if ($this->stock_min !== '') {
            $query->where('stock_base', '>=', $this->stock_min);
        }

        if ($this->stock_max !== '') {
            $query->where('stock_base', '<=', $this->stock_max);
        }

        return $query->paginate($this->perPage);
    }

    #[Computed]
    public function almacenes()
    {
        return Almacen::where('activo', true)->orderBy('nombre')->get();
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

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar por producto o SKU...') }}" />
            </div>
            <flux:select wire:model.live="almacen_id" class="sm:w-44">
                <option value="">{{ __('Todos los Almacenes') }}</option>
                @foreach($this->almacenes as $alm)
                    <option value="{{ $alm->id }}">{{ $alm->nombre }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="atributo_valor_id" class="flex-1">
                <option value="">{{ __('Cualquier Atributo') }}</option>
                @foreach($this->atributoValores as $val)
                    <option value="{{ $val['id'] }}">{{ $val['nombre'] }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex flex-col sm:flex-row items-end gap-3 justify-between">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live="stock_min" type="number" label="{{ __('Stock Mín.') }}" class="w-full sm:w-32" />
                <flux:input wire:model.live="stock_max" type="number" label="{{ __('Stock Máx.') }}" class="w-full sm:w-32" />
            </div>
            <div class="mt-4 sm:mt-0">
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
                            <td colspan="5" class="text-center py-8 text-zinc-500">
                                {{ __('No hay registros de inventario que coincidan con tu búsqueda.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->inventarios->hasPages())
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800">
                {{ $this->inventarios->links() }}
            </div>
        @endif
    </div>
</div>
