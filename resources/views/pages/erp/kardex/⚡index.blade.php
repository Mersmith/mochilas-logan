<?php

use App\Models\Almacen;
use App\Models\Variacion;
use App\Models\Kardex;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Flux\Flux;

new #[Title('Kardex Valorizado')] class extends Component {
    use WithPagination;

    public ?int $almacen_id = null;
    public ?int $variacion_id = null;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $tipo_transaccion = 'todos';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public int $perPage = 20;

    public function updating($property)
    {
        if (in_array($property, ['search', 'tipo_transaccion', 'desde', 'hasta', 'perPage', 'almacen_id', 'variacion_id'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'tipo_transaccion', 'desde', 'hasta']);
        $this->perPage = 20;
        $this->resetPage();
    }

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $almacen = Almacen::where('activo', true)->first();
        if ($almacen) {
            $this->almacen_id = $almacen->id;
        }

        $variacion = Variacion::where('activo', true)->first();
        if ($variacion) {
            $this->variacion_id = $variacion->id;
        }
    }

    /**
     * Get Kardex entries for the selected warehouse and variation.
     */
    #[Computed]
    public function movimientos()
    {
        if (!$this->almacen_id || !$this->variacion_id) {
            return collect();
        }

        return $this->buildQuery()->paginate($this->perPage);
    }

    private function buildQuery()
    {
        return Kardex::with(['origenDocumento'])
            ->where('almacen_id', $this->almacen_id)
            ->where('variacion_id', $this->variacion_id)
            ->when($this->search, fn($q) => $q->where('concepto', 'like', '%' . $this->search . '%'))
            ->when($this->tipo_transaccion !== 'todos', fn($q) => $q->where('tipo_transaccion', $this->tipo_transaccion))
            ->when($this->desde, fn($q) => $q->whereDate('created_at', '>=', $this->desde))
            ->when($this->hasta, fn($q) => $q->whereDate('created_at', '<=', $this->hasta))
            ->orderBy('created_at', 'desc');
    }

    public function exportar()
    {
        if (!$this->almacen_id || !$this->variacion_id) {
            Flux::toast(variant: 'danger', text: __('Selecciona un almacén y una variación para exportar.'));
            return;
        }

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\KardexExport($this->buildQuery()), 'kardex.xlsx');
    }

    /**
     * Get summary cards data.
     */
    #[Computed]
    public function resumen(): array
    {
        if (!$this->almacen_id || !$this->variacion_id) {
            return [
                'stock_actual' => 0,
                'costo_promedio' => 0.00,
                'valor_total' => 0.00
            ];
        }

        $ultimoMovimiento = Kardex::where('almacen_id', $this->almacen_id)
            ->where('variacion_id', $this->variacion_id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$ultimoMovimiento) {
            return [
                'stock_actual' => 0,
                'costo_promedio' => 0.00,
                'valor_total' => 0.00
            ];
        }

        return [
            'stock_actual' => $ultimoMovimiento->stock_posterior,
            'costo_promedio' => $ultimoMovimiento->costo_unitario ?: 0.00,
            'valor_total' => $ultimoMovimiento->valor_total_almacen ?: 0.00
        ];
    }

    /**
     * Get active warehouses.
     */
    #[Computed]
    public function almacenes()
    {
        return Almacen::where('activo', true)->get();
    }

    /**
     * Get active variations.
     */
    #[Computed]
    public function variaciones()
    {
        return Variacion::with('producto', 'valores.atributo')
            ->where('activo', true)
            ->get()
            ->map(function ($v) {
                $desc = $v->valores->map(fn($val) => $val->atributo->nombre . ': ' . $val->valor)->implode(', ');
                return [
                    'id' => $v->id,
                    'nombre' => $v->producto->nombre . ' - ' . $v->sku . ' (' . $desc . ')',
                ];
            });
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Kardex Valorizado') }}</flux:heading>
        <flux:subheading>{{ __('Consulta la trazabilidad de stock, costos promedio y valor de inventario por almacén.') }}</flux:subheading>
    </div>

    <!-- Selectores de Consulta -->
    <div class="flex flex-col gap-6 md:flex-row bg-zinc-50 dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="w-full md:w-1/3">
            <flux:select wire:model.live="almacen_id" :label="__('Almacén')">
                @foreach($this->almacenes as $alm)
                    <flux:select.option value="{{ $alm->id }}">{{ $alm->nombre }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full md:w-2/3">
            <flux:select wire:model.live="variacion_id" :label="__('Variación / SKU')">
                @foreach($this->variaciones as $var)
                    <flux:select.option value="{{ $var['id'] }}">{{ $var['nombre'] }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- Tarjetas de Resumen Valorizado -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <!-- Stock Físico -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-6 rounded-xl shadow-sm">
            <flux:text size="sm" class="text-zinc-500 font-semibold uppercase">{{ __('Stock Físico Actual') }}</flux:text>
            <div class="flex items-baseline gap-2 mt-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $this->resumen['stock_actual'] }}</span>
                <span class="text-zinc-500 text-sm">{{ __('Unidades base') }}</span>
            </div>
        </div>

        <!-- Costo Unitario -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-6 rounded-xl shadow-sm">
            <flux:text size="sm" class="text-zinc-500 font-semibold uppercase">{{ __('Costo Promedio Ponderado') }}</flux:text>
            <div class="flex items-baseline gap-2 mt-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">S/ {{ number_format($this->resumen['costo_promedio'], 2) }}</span>
                <span class="text-zinc-500 text-sm">{{ __('por unidad') }}</span>
            </div>
        </div>

        <!-- Valor Total -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-6 rounded-xl shadow-sm">
            <flux:text size="sm" class="text-zinc-500 font-semibold uppercase">{{ __('Valor Total de Inventario') }}</flux:text>
            <div class="flex items-baseline gap-2 mt-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">S/ {{ number_format($this->resumen['valor_total'], 2) }}</span>
                <span class="text-zinc-500 text-sm">{{ __('en almacén') }}</span>
            </div>
        </div>
    </div>

    <!-- Tabla Histórica (Tailwind para compatibilidad con Flux Free) -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden shadow-sm flex flex-col">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30 flex flex-col md:flex-row gap-4 items-center justify-between">
            <flux:heading size="lg">{{ __('Historial de Transacciones') }}</flux:heading>
            
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar concepto...') }}" class="w-full sm:w-48" />
                <flux:select wire:model.live="tipo_transaccion" class="w-full sm:w-32">
                    <option value="todos">{{ __('Todos') }}</option>
                    <option value="Entrada">{{ __('Entradas') }}</option>
                    <option value="Salida">{{ __('Salidas') }}</option>
                </flux:select>
                <flux:input wire:model.live="desde" type="date" class="w-full sm:w-36" />
                <flux:input wire:model.live="hasta" type="date" class="w-full sm:w-36" />
            </div>
        </div>

        <!-- Cabecera de tabla: Acciones + PerPage -->
        <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30">
            <flux:button class="!bg-emerald-600 !text-white hover:!bg-emerald-700 border-none" size="sm" icon="arrow-down-tray" wire:click="exportar">{{ __('Exportar a Excel') }}</flux:button>

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
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Fecha') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Concepto / Motivo') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300 text-center">{{ __('Tipo') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300 text-center">{{ __('Cant. Base') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300 text-right">{{ __('Costo Unit.') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300 text-right">{{ __('Costo Mov.') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300 text-center">{{ __('Stock Final') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300 text-right">{{ __('Valor Final') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->movimientos as $mov)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $mov->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="p-4 font-medium text-zinc-900 dark:text-white">
                                {{ $mov->concepto }}
                            </td>
                            <td class="p-4 text-center">
                                @if($mov->tipo_transaccion === 'Entrada')
                                    <flux:badge color="emerald">{{ __('Entrada') }}</flux:badge>
                                @else
                                    <flux:badge color="rose">{{ __('Salida') }}</flux:badge>
                                @endif
                            </td>
                            <td class="p-4 text-center font-semibold {{ $mov->tipo_transaccion === 'Entrada' ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $mov->tipo_transaccion === 'Entrada' ? '+' : '-' }}{{ $mov->cantidad }}
                            </td>
                            <td class="p-4 text-right text-zinc-600 dark:text-zinc-400">
                                S/ {{ number_format($mov->costo_unitario, 2) }}
                            </td>
                            <td class="p-4 text-right text-zinc-600 dark:text-zinc-400">
                                S/ {{ number_format($mov->costo_total, 2) }}
                            </td>
                            <td class="p-4 text-center font-semibold text-zinc-900 dark:text-white">
                                {{ $mov->stock_posterior }}
                            </td>
                            <td class="p-4 text-right font-semibold text-zinc-900 dark:text-white">
                                S/ {{ number_format($mov->valor_total_almacen, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-12 text-zinc-400">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.face-smile class="size-8 text-zinc-300" />
                                    <span>{{ $search ? __('No se encontraron movimientos para ":query"', ['query' => $search]) : __('No hay movimientos registrados para este almacén y variación.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pie de tabla: Paginación + Info -->
        <div class="px-4 py-4 border-t border-zinc-200 dark:border-zinc-700">
            @if(method_exists($this->movimientos, 'hasPages') && $this->movimientos->hasPages())
                {{ $this->movimientos->links() }}
            @else
                <p class="text-xs text-zinc-400">
                    {{ __(':total registro(s)', ['total' => collect($this->movimientos->items())->count()]) }}
                </p>
            @endif
        </div>
    </div>
</div>
