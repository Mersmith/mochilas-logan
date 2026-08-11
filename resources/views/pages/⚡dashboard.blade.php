<?php

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Inventario;
use App\Models\Kardex;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
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
        $hoy = Carbon::today();
        $inicioMesActual = Carbon::now()->startOfMonth();
        $inicioMesAnterior = Carbon::now()->subMonth()->startOfMonth();
        $finMesAnterior = Carbon::now()->subMonth()->endOfMonth();

        // Ventas de Hoy
        $ventasDia = (float)Venta::where('estado_pago', 'pagado')
            ->whereDate('created_at', $hoy)
            ->sum('total');

        // Ventas Mes Actual
        $ventasMesActual = (float)Venta::where('estado_pago', 'pagado')
            ->whereBetween('created_at', [$inicioMesActual, Carbon::now()->endOfMonth()])
            ->sum('total');

        // Ventas Mes Anterior
        $ventasMesAnterior = (float)Venta::where('estado_pago', 'pagado')
            ->whereBetween('created_at', [$inicioMesAnterior, $finMesAnterior])
            ->sum('total');

        // Crecimiento Porcentual
        $crecimiento = 0;
        if ($ventasMesAnterior > 0) {
            $crecimiento = (($ventasMesActual - $ventasMesAnterior) / $ventasMesAnterior) * 100;
        } elseif ($ventasMesActual > 0) {
            $crecimiento = 100;
        }

        // Ticket Promedio (Mes Actual)
        $cantidadVentasMes = Venta::where('estado_pago', 'pagado')
            ->whereBetween('created_at', [$inicioMesActual, Carbon::now()->endOfMonth()])
            ->count();
        $ticketPromedio = $cantidadVentasMes > 0 ? ($ventasMesActual / $cantidadVentasMes) : 0;

        // Stock y Utilidad (Manteniendo lo anterior)
        $cogs = (float)Kardex::where('tipo_transaccion', 'Salida')
            ->where('concepto', 'like', 'Venta %')
            ->sum('costo_total');
        $ingresosNetos = (float)Venta::where('estado_pago', 'pagado')->sum('subtotal');
        $utilidadNeta = $ingresosNetos - $cogs;
        $stockTotal = (int)Inventario::sum('stock_base');

        return [
            'ventas_dia' => $ventasDia,
            'ventas_mes_actual' => $ventasMesActual,
            'ventas_mes_anterior' => $ventasMesAnterior,
            'crecimiento' => round($crecimiento, 2),
            'ticket_promedio' => $ticketPromedio,
            'utilidad_neta' => $utilidadNeta,
            'stock_total' => $stockTotal,
        ];
    }

    /**
     * Get operational alerts.
     */
    #[Computed]
    public function alertasOperativas(): array
    {
        $pendientesPago = Venta::where('estado_pago', 'pendiente')->count();
        $pendientesDespacho = Venta::where('estado_pago', 'pagado')
            ->whereIn('estado_despacho', ['pendiente', 'preparado'])
            ->count();

        return [
            'pendientes_pago' => $pendientesPago,
            'pendientes_despacho' => $pendientesDespacho,
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
     * Get Top 5 products of the current month.
     */
    #[Computed]
    public function top5Productos()
    {
        $inicioMesActual = Carbon::now()->startOfMonth();
        
        return VentaDetalle::select('variacion_id', DB::raw('SUM(cantidad) as total_vendido'))
            ->whereHas('venta', function ($q) use ($inicioMesActual) {
                $q->where('estado_pago', 'pagado')
                  ->whereBetween('created_at', [$inicioMesActual, Carbon::now()->endOfMonth()]);
            })
            ->with(['variacion.producto'])
            ->groupBy('variacion_id')
            ->orderBy('total_vendido', 'desc')
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
        <!-- Ventas del Día -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-6 rounded-xl shadow-sm">
            <div class="flex items-center justify-between">
                <flux:text size="sm" class="text-zinc-500 font-semibold uppercase">{{ __('Ventas de Hoy') }}</flux:text>
                <flux:icon name="banknotes" class="text-zinc-400 size-5" />
            </div>
            <div class="mt-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">S/ {{ number_format($this->kpis['ventas_dia'], 2) }}</span>
            </div>
            <p class="text-xxs text-zinc-400 mt-1">{{ __('Recaudación total de ventas procesadas hoy.') }}</p>
        </div>

        <!-- Ventas del Mes vs Anterior -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-6 rounded-xl shadow-sm">
            <div class="flex items-center justify-between">
                <flux:text size="sm" class="text-zinc-500 font-semibold uppercase">{{ __('Ventas del Mes') }}</flux:text>
                <flux:icon name="calendar-days" class="text-zinc-400 size-5" />
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">S/ {{ number_format($this->kpis['ventas_mes_actual'], 2) }}</span>
            </div>
            <div class="mt-1 flex items-center gap-1 text-xxs">
                @if($this->kpis['crecimiento'] >= 0)
                    <flux:icon name="arrow-trending-up" class="size-3 text-emerald-500" />
                    <span class="text-emerald-500 font-medium">+{{ $this->kpis['crecimiento'] }}%</span>
                @else
                    <flux:icon name="arrow-trending-down" class="size-3 text-rose-500" />
                    <span class="text-rose-500 font-medium">{{ $this->kpis['crecimiento'] }}%</span>
                @endif
                <span class="text-zinc-400">{{ __('vs mes anterior') }}</span>
            </div>
        </div>

        <!-- Ticket Promedio -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 p-6 rounded-xl shadow-sm">
            <div class="flex items-center justify-between">
                <flux:text size="sm" class="text-zinc-500 font-semibold uppercase">{{ __('Ticket Promedio (Mes)') }}</flux:text>
                <flux:icon name="shopping-bag" class="text-zinc-400 size-5" />
            </div>
            <div class="mt-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">S/ {{ number_format($this->kpis['ticket_promedio'], 2) }}</span>
            </div>
            <p class="text-xxs text-zinc-400 mt-1">{{ __('Gasto promedio por cada venta.') }}</p>
        </div>

        <!-- Alertas Operativas -->
        <div class="flex flex-col gap-3">
            <!-- Pendientes de Despacho -->
            <div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 p-4 rounded-xl shadow-sm flex items-center justify-between flex-1">
                <div>
                    <flux:text size="sm" class="text-amber-700 dark:text-amber-500 font-bold uppercase">{{ __('A Despachar') }}</flux:text>
                    <p class="text-xxs text-amber-600 dark:text-amber-400 mt-1">{{ __('Pedidos pagados esperando envío.') }}</p>
                </div>
                <div class="text-3xl font-black text-amber-600 dark:text-amber-500">
                    {{ $this->alertasOperativas['pendientes_despacho'] }}
                </div>
            </div>
            
            <!-- Pendientes de Pago -->
            <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 p-4 rounded-xl shadow-sm flex items-center justify-between flex-1">
                <div>
                    <flux:text size="sm" class="text-rose-700 dark:text-rose-500 font-bold uppercase">{{ __('Por Pagar') }}</flux:text>
                    <p class="text-xxs text-rose-600 dark:text-rose-400 mt-1">{{ __('Pedidos en espera de pago.') }}</p>
                </div>
                <div class="text-3xl font-black text-rose-600 dark:text-rose-500">
                    {{ $this->alertasOperativas['pendientes_pago'] }}
                </div>
            </div>
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
            <!-- Tarjeta Top 5 Productos -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm p-6 space-y-4">
                <flux:heading size="lg">{{ __('Top 5 Productos del Mes') }}</flux:heading>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->top5Productos as $index => $item)
                        @php
                            $desc = $item->variacion->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
                        @endphp
                        <div class="py-3 flex items-center justify-between gap-4 first:pt-0 last:pb-0">
                            <div class="flex items-center gap-3">
                                <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 flex items-center justify-center text-xs font-bold">
                                    {{ $index + 1 }}
                                </div>
                                <div>
                                    <div class="font-semibold text-zinc-900 dark:text-white text-xs">
                                        {{ $item->variacion->producto->nombre }}
                                    </div>
                                    <div class="text-xxs text-zinc-500">{{ $desc }} (SKU: {{ $item->variacion->sku }})</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-zinc-900 dark:text-white text-xs">{{ $item->total_vendido }}</div>
                                <div class="text-xxs text-zinc-400">{{ __('unidades') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-zinc-400 text-xs">
                            {{ __('Aún no hay ventas este mes.') }}
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
