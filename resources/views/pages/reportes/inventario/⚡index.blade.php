<?php

use App\Models\Inventario;
use App\Models\VentaDetalle;
use App\Models\Kardex;
use App\Models\Almacen;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

new #[Title('Reportes de Inventario')] class extends Component {
    #[Url]
    public string $tab = 'critico';

    public function setTab($tabName)
    {
        $this->tab = $tabName;
    }

    #[Computed]
    public function stockCritico()
    {
        if ($this->tab !== 'critico') return collect();

        return Inventario::with(['variacion.producto', 'variacion.valores.atributo', 'almacen'])
            ->whereColumn('stock_base', '<=', 'stock_minimo')
            ->orderBy('stock_base', 'asc')
            ->get();
    }

    #[Computed]
    public function deadStock()
    {
        if ($this->tab !== 'inmovilizado') return collect();

        $fechaLimite = Carbon::now()->subMonths(3);
        
        $variacionesVendidas = VentaDetalle::whereHas('venta', function($q) use ($fechaLimite) {
            $q->where('estado_pago', 'pagado')
              ->where('created_at', '>=', $fechaLimite);
        })->pluck('variacion_id');

        return Inventario::with(['variacion.producto', 'variacion.valores.atributo', 'almacen'])
            ->whereNotIn('variacion_id', $variacionesVendidas)
            ->where('stock_base', '>', 0)
            ->orderBy('stock_base', 'desc')
            ->get();
    }

    #[Computed]
    public function valorizacion()
    {
        if ($this->tab !== 'valorizacion') return collect();

        $almacenes = Almacen::all();
        $resultados = [];
        $totalGlobal = 0;

        foreach ($almacenes as $almacen) {
            $inventarios = Inventario::with(['variacion.producto'])
                ->where('almacen_id', $almacen->id)
                ->where('stock_base', '>', 0)
                ->get();
            
            $valorAlmacen = 0;
            $detalle = [];

            foreach ($inventarios as $inv) {
                // Último costo del kardex
                $ultimoKardex = Kardex::where('variacion_id', $inv->variacion_id)
                    ->where('almacen_id', $almacen->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                $costoUnitario = $ultimoKardex ? $ultimoKardex->costo_unitario : 0;
                $valorVariacion = $costoUnitario * $inv->stock_base;
                $valorAlmacen += $valorVariacion;

                $detalle[] = [
                    'producto' => $inv->variacion->producto->nombre,
                    'sku' => $inv->variacion->sku,
                    'stock' => $inv->stock_base,
                    'costo' => $costoUnitario,
                    'total' => $valorVariacion
                ];
            }

            // Ordenar detalle por total descendente
            usort($detalle, fn($a, $b) => $b['total'] <=> $a['total']);

            $resultados[] = [
                'almacen' => $almacen->nombre,
                'total' => $valorAlmacen,
                'detalle' => $detalle
            ];
            
            $totalGlobal += $valorAlmacen;
        }

        return [
            'almacenes' => $resultados,
            'total_global' => $totalGlobal
        ];
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Reportes de Inventario y Logística') }}</flux:heading>
        <flux:subheading>{{ __('Análisis de stock crítico, inmovilizados y valorización de almacenes.') }}</flux:subheading>
    </div>

    <!-- Navegación de Tabs -->
    <div class="flex gap-2 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700 pb-2">
        <button wire:click="setTab('critico')" class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors {{ $tab === 'critico' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}">
            <flux:icon name="exclamation-triangle" class="size-4 inline-block mr-1" />
            {{ __('Stock Crítico') }}
        </button>
        <button wire:click="setTab('inmovilizado')" class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors {{ $tab === 'inmovilizado' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}">
            <flux:icon name="archive-box-x-mark" class="size-4 inline-block mr-1" />
            {{ __('Mercadería Inmovilizada') }}
        </button>
        <button wire:click="setTab('valorizacion')" class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors {{ $tab === 'valorizacion' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}">
            <flux:icon name="currency-dollar" class="size-4 inline-block mr-1" />
            {{ __('Valorización de Almacenes') }}
        </button>
    </div>

    <!-- Contenido de Tabs -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden">
        
        {{-- TAB: STOCK CRÍTICO --}}
        @if($tab === 'critico')
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30">
                <flux:heading size="lg">{{ __('Punto de Reorden (Stock Crítico)') }}</flux:heading>
                <p class="text-sm text-zinc-500">{{ __('Lista de productos que necesitan reabastecimiento urgente según sus mínimos establecidos.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium">
                            <th class="p-4">{{ __('Almacén') }}</th>
                            <th class="p-4">{{ __('Producto') }}</th>
                            <th class="p-4 text-center">{{ __('Stock Actual') }}</th>
                            <th class="p-4 text-center">{{ __('Mínimo') }}</th>
                            <th class="p-4">{{ __('Estado') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->stockCritico as $inv)
                            @php
                                $desc = $inv->variacion->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
                            @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="p-4 text-zinc-600 dark:text-zinc-400">{{ $inv->almacen->nombre }}</td>
                                <td class="p-4">
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $inv->variacion->producto->nombre }}</div>
                                    <div class="text-xs text-zinc-500">{{ $desc }} (SKU: {{ $inv->variacion->sku }})</div>
                                </td>
                                <td class="p-4 text-center font-bold {{ $inv->stock_base <= 0 ? 'text-rose-600' : 'text-amber-600' }}">
                                    {{ $inv->stock_base }}
                                </td>
                                <td class="p-4 text-center text-zinc-600 dark:text-zinc-400">{{ $inv->stock_minimo }}</td>
                                <td class="p-4">
                                    @if($inv->stock_base <= 0)
                                        <flux:badge color="rose" size="sm">{{ __('Agotado') }}</flux:badge>
                                    @else
                                        <flux:badge color="warning" size="sm">{{ __('Crítico') }}</flux:badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-zinc-500">
                                    {{ __('Todos los almacenes tienen stock suficiente por encima de sus límites mínimos.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- TAB: DEAD STOCK --}}
        @if($tab === 'inmovilizado')
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30">
                <flux:heading size="lg">{{ __('Mercadería Inmovilizada (Dead Stock)') }}</flux:heading>
                <p class="text-sm text-zinc-500">{{ __('Productos con stock disponible que no han tenido salidas por ventas en los últimos 3 meses.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium">
                            <th class="p-4">{{ __('Almacén') }}</th>
                            <th class="p-4">{{ __('Producto') }}</th>
                            <th class="p-4 text-center">{{ __('Stock Inmovilizado') }}</th>
                            <th class="p-4">{{ __('Sugerencia') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->deadStock as $inv)
                            @php
                                $desc = $inv->variacion->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
                            @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="p-4 text-zinc-600 dark:text-zinc-400">{{ $inv->almacen->nombre }}</td>
                                <td class="p-4">
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $inv->variacion->producto->nombre }}</div>
                                    <div class="text-xs text-zinc-500">{{ $desc }} (SKU: {{ $inv->variacion->sku }})</div>
                                </td>
                                <td class="p-4 text-center font-bold text-zinc-900 dark:text-white">{{ $inv->stock_base }}</td>
                                <td class="p-4">
                                    <flux:badge color="zinc" size="sm">{{ __('Aplicar Promoción') }}</flux:badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-zinc-500">
                                    {{ __('¡Excelente! Todo tu inventario tiene rotación activa en los últimos 3 meses.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- TAB: VALORIZACIÓN --}}
        @if($tab === 'valorizacion')
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">{{ __('Valorización Total de Almacenes') }}</flux:heading>
                        <p class="text-sm text-zinc-500">{{ __('Resumen del capital invertido en mercadería por almacén, basado en el último costo unitario registrado.') }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-emerald-600 dark:text-emerald-400 font-bold uppercase">{{ __('Gran Total Invertido') }}</div>
                        <div class="text-3xl font-black text-emerald-700 dark:text-emerald-500">S/ {{ number_format($this->valorizacion['total_global'], 2) }}</div>
                    </div>
                </div>
            </div>
            
            <div class="p-6 space-y-8">
                @foreach($this->valorizacion['almacenes'] as $data)
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
                        <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center">
                            <flux:heading size="md" class="font-bold text-zinc-900 dark:text-white">{{ $data['almacen'] }}</flux:heading>
                            <span class="font-bold text-zinc-900 dark:text-white">S/ {{ number_format($data['total'], 2) }}</span>
                        </div>
                        <div class="p-4">
                            @if(count($data['detalle']) > 0)
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="text-zinc-500 border-b border-zinc-200 dark:border-zinc-700">
                                            <th class="py-2 px-1">{{ __('SKU') }}</th>
                                            <th class="py-2 px-1">{{ __('Producto') }}</th>
                                            <th class="py-2 px-1 text-center">{{ __('Stock Físico') }}</th>
                                            <th class="py-2 px-1 text-right">{{ __('Costo Unitario (S/)') }}</th>
                                            <th class="py-2 px-1 text-right">{{ __('Total Valorizado (S/)') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                        @foreach(array_slice($data['detalle'], 0, 10) as $det)
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                                <td class="py-2 px-1 text-zinc-500">{{ $det['sku'] }}</td>
                                                <td class="py-2 px-1 font-medium text-zinc-900 dark:text-white">{{ $det['producto'] }}</td>
                                                <td class="py-2 px-1 text-center">{{ $det['stock'] }}</td>
                                                <td class="py-2 px-1 text-right">{{ number_format($det['costo'], 2) }}</td>
                                                <td class="py-2 px-1 text-right font-bold">S/ {{ number_format($det['total'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @if(count($data['detalle']) > 10)
                                    <div class="mt-3 text-center text-xs text-zinc-500">
                                        {{ __('Mostrando el Top 10 productos con mayor valorización en este almacén (de :total en total).', ['total' => count($data['detalle'])]) }}
                                    </div>
                                @endif
                            @else
                                <div class="text-center text-zinc-500 text-sm py-4">{{ __('Este almacén no tiene inventario físico.') }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</div>
