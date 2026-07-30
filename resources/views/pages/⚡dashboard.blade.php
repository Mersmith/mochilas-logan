<?php

use App\Models\Venta;
use App\Models\Inventario;
use App\Models\Kardex;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;

new #[Title('Dashboard')] class extends Component {
    
    /**
     * Get dynamic KPI metrics.
     */
    #[Computed]
    public function kpis(): array
    {
        $ingresosBrutos = (float)Venta::where('estado_pago', 'pagado')->sum('total');
        
        $cogs = (float)Kardex::where('tipo_transaccion', 'Salida')
            ->where('concepto', 'like', 'Venta %')
            ->sum('costo_total');
            
        $ingresosNetos = (float)Venta::where('estado_pago', 'pagado')->sum('subtotal');
        $utilidadNeta = $ingresosNetos - $cogs;
        
        $stockTotal = (int)Inventario::sum('stock_base');

        return [
            'ingresos_brutos' => $ingresosBrutos,
            'cogs' => $cogs,
            'utilidad_neta' => $utilidadNeta,
            'stock_total' => $stockTotal,
        ];
    }

    /**
     * Get stock alerts (stock_base <= stock_minimo).
     */
    #[Computed]
    public function alertasStock()
    {
        return Inventario::with(['variacion.producto', 'variacion.valores.atributo', 'almacen'])
            ->whereColumn('stock_base', '<=', 'stock_minimo')
            ->orderBy('stock_base', 'asc')
            ->get();
    }

    /**
     * Get payment methods sales distribution.
     */
    #[Computed]
    public function ventasMetodoPago()
    {
        return Venta::where('estado_pago', 'pagado')
            ->select('metodo_pago', DB::raw('SUM(total) as total_monto'), DB::raw('COUNT(*) as total_ventas'))
            ->groupBy('metodo_pago')
            ->get()
            ->map(function ($item) {
                $nombres = [
                    'efectivo' => __('Efectivo'),
                    'tarjeta' => __('Tarjeta'),
                    'transferencia' => __('Transferencia'),
                    'yape_plin' => __('Yape / Plin'),
                ];
                $item->nombre_metodo = $nombres[strtolower($item->metodo_pago)] ?? ucfirst($item->metodo_pago);
                return $item;
            });
    }

    /**
     * Get recent completed sales.
     */
    #[Computed]
    public function ventasRecientes()
    {
        return Venta::with(['user', 'tipoDocumento'])
            ->where('estado_pago', 'pagado')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div>
        <flux:heading size="xl">{{ __('Dashboard Comercial') }}</flux:heading>
        <flux:subheading>{{ __('Resumen financiero de ventas, ganancias reales y alertas de inventario.') }}</flux:subheading>
    </div>

    <!-- Tarjetas de KPIs Financieros -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Ingresos Brutos (Ventas Totales) -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-6 rounded-xl shadow-sm">
            <div class="flex items-center justify-between">
                <flux:text size="sm" class="text-zinc-500 font-semibold uppercase">{{ __('Ingresos Totales (IGV Inc.)') }}</flux:text>
                <flux:icon name="banknotes" class="text-zinc-400 size-5" />
            </div>
            <div class="mt-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">S/ {{ number_format($this->kpis['ingresos_brutos'], 2) }}</span>
            </div>
            <p class="text-xxs text-zinc-400 mt-1">{{ __('Facturación comercial en ventas procesadas.') }}</p>
        </div>

        <!-- Costo de Venta (COGS) -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-6 rounded-xl shadow-sm">
            <div class="flex items-center justify-between">
                <flux:text size="sm" class="text-zinc-500 font-semibold uppercase">{{ __('Costo de Mercadería (COGS)') }}</flux:text>
                <flux:icon name="shopping-bag" class="text-zinc-400 size-5" />
            </div>
            <div class="mt-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">S/ {{ number_format($this->kpis['cogs'], 2) }}</span>
            </div>
            <p class="text-xxs text-zinc-400 mt-1">{{ __('Costo promedio ponderado de Kardex de salida.') }}</p>
        </div>

        <!-- Utilidad Neta (Ganancia Real) -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-6 rounded-xl shadow-sm">
            <div class="flex items-center justify-between">
                <flux:text size="sm" class="text-zinc-500 font-semibold uppercase">{{ __('Utilidad Neta (Ganancia Real)') }}</flux:text>
                <flux:icon name="currency-dollar" class="text-emerald-500 size-5" />
            </div>
            <div class="mt-2">
                <span class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">S/ {{ number_format($this->kpis['utilidad_neta'], 2) }}</span>
            </div>
            <p class="text-xxs text-zinc-400 mt-1">{{ __('Ingresos Netos (Sin IGV) menos Costo de Mercadería.') }}</p>
        </div>

        <!-- Stock Físico Total -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-6 rounded-xl shadow-sm">
            <div class="flex items-center justify-between">
                <flux:text size="sm" class="text-zinc-500 font-semibold uppercase">{{ __('Stock Físico Global') }}</flux:text>
                <flux:icon name="archive-box" class="text-zinc-400 size-5" />
            </div>
            <div class="mt-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $this->kpis['stock_total'] }}</span>
            </div>
            <p class="text-xxs text-zinc-400 mt-1">{{ __('Unidades base disponibles en todos los almacenes.') }}</p>
        </div>
    </div>

    <!-- Alertas de Stock Bajo y Ventas Recientes (Dos Columnas) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Columna Izquierda/Centro: Alertas de Stock Bajo (Ancho 2/3) -->
        <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden flex flex-col h-full">
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30 flex items-center justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Alertas de Stock Bajo / Reposición') }}</flux:heading>
                    <flux:subheading>{{ __('Productos con stock igual o inferior a su mínimo establecido.') }}</flux:subheading>
                </div>
                <flux:icon name="exclamation-triangle" class="text-amber-500 size-6 animate-pulse" />
            </div>

            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 font-medium">
                            <th class="p-4">{{ __('Almacén') }}</th>
                            <th class="p-4">{{ __('Mochila / Variación') }}</th>
                            <th class="p-4 text-center">{{ __('Stock Actual') }}</th>
                            <th class="p-4 text-center">{{ __('Stock Mínimo') }}</th>
                            <th class="p-4 text-center">{{ __('Estado Alerta') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->alertasStock as $inv)
                            @php
                                $desc = $inv->variacion->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
                            @endphp
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                    {{ $inv->almacen->nombre }}
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $inv->variacion->producto->nombre }}</div>
                                    <div class="text-xxs text-zinc-500">{{ $desc }} (SKU: {{ $inv->variacion->sku }})</div>
                                </td>
                                <td class="p-4 text-center font-bold {{ $inv->stock_base <= 0 ? 'text-rose-600' : 'text-amber-600' }}">
                                    {{ $inv->stock_base }}
                                </td>
                                <td class="p-4 text-center text-zinc-600 dark:text-zinc-400 font-semibold">
                                    {{ $inv->stock_minimo }}
                                </td>
                                <td class="p-4 text-center">
                                    @if($inv->stock_base <= 0)
                                        <flux:badge color="rose" size="sm">{{ __('Agotado') }}</flux:badge>
                                    @else
                                        <flux:badge color="warning" size="sm">{{ __('Crítico') }}</flux:badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-12 text-zinc-400 text-xs">
                                    {{ __('¡Excelente! Todos los almacenes tienen stock suficiente por encima de sus límites mínimos.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Columna Derecha: Resumen de Ventas Recientes y Distribución de Pagos -->
        <div class="space-y-6">
            <!-- Tarjeta Ventas Recientes -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm p-6 space-y-4">
                <flux:heading size="lg">{{ __('Últimas Ventas') }}</flux:heading>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->ventasRecientes as $v)
                        <div class="py-3 flex items-center justify-between gap-4 first:pt-0 last:pb-0">
                            <div>
                                <div class="font-semibold text-zinc-900 dark:text-white text-xs">
                                    {{ $v->tipoDocumento->nombre }} ({{ $v->serie }}-{{ str_pad($v->correlativo, 5, '0', STR_PAD_LEFT) }})
                                </div>
                                <div class="text-xxs text-zinc-500">{{ $v->user->name }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-zinc-900 dark:text-white text-xs">S/ {{ number_format($v->total, 2) }}</div>
                                <div class="text-xxs text-zinc-400">{{ $v->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-zinc-400 text-xs">
                            {{ __('Aún no se registran ventas.') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Tarjeta Métodos de Pago -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm p-6 space-y-4">
                <flux:heading size="lg">{{ __('Ventas por Medio de Pago') }}</flux:heading>
                <div class="space-y-3">
                    @forelse($this->ventasMetodoPago as $mp)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-zinc-600 dark:text-zinc-400 font-medium">{{ $mp->nombre_metodo }} ({{ $mp->total_ventas }} {{ __('pedidos') }})</span>
                            <span class="font-bold text-zinc-900 dark:text-white">S/ {{ number_format($mp->total_monto, 2) }}</span>
                        </div>
                    @empty
                        <div class="text-center py-6 text-zinc-400 text-xs">
                            {{ __('No hay datos de recaudación.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
