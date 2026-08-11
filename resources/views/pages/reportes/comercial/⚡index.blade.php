<?php

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Categoria;
use App\Models\Marca;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

new #[Title('Reportes Comerciales')] class extends Component {
    #[Url]
    public string $tab = 'marcas_categorias';

    #[Url]
    public string $filtroTiempo = 'mes_actual';

    public function setTab($tabName)
    {
        $this->tab = $tabName;
    }

    private function aplicarFiltroTiempo($query)
    {
        if ($this->filtroTiempo === 'mes_actual') {
            $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
        }
        return $query;
    }

    #[Computed]
    public function rendimientoCategoriasMarcas()
    {
        if ($this->tab !== 'marcas_categorias') return [];

        $query = VentaDetalle::select(
                'productos.categoria_id',
                'productos.marca_id',
                DB::raw('SUM(venta_detalles.cantidad) as total_cantidad'),
                DB::raw('SUM(venta_detalles.subtotal) as total_monto')
            )
            ->join('variacions', 'venta_detalles.variacion_id', '=', 'variacions.id')
            ->join('productos', 'variacions.producto_id', '=', 'productos.id')
            ->whereHas('venta', function ($q) {
                $q->where('estado_pago', 'pagado');
                $this->aplicarFiltroTiempo($q);
            })
            ->groupBy('productos.categoria_id', 'productos.marca_id')
            ->get();

        $categorias = Categoria::all()->keyBy('id');
        $marcas = Marca::all()->keyBy('id');

        $rankingCategorias = [];
        $rankingMarcas = [];

        foreach ($query as $row) {
            // Categorias
            $catId = $row->categoria_id;
            if (!isset($rankingCategorias[$catId])) {
                $rankingCategorias[$catId] = [
                    'nombre' => $catId ? ($categorias[$catId]->nombre ?? 'Sin Categoría') : 'Sin Categoría',
                    'cantidad' => 0,
                    'monto' => 0
                ];
            }
            $rankingCategorias[$catId]['cantidad'] += $row->total_cantidad;
            $rankingCategorias[$catId]['monto'] += $row->total_monto;

            // Marcas
            $marId = $row->marca_id;
            if (!isset($rankingMarcas[$marId])) {
                $rankingMarcas[$marId] = [
                    'nombre' => $marId ? ($marcas[$marId]->nombre ?? 'Sin Marca') : 'Sin Marca',
                    'cantidad' => 0,
                    'monto' => 0
                ];
            }
            $rankingMarcas[$marId]['cantidad'] += $row->total_cantidad;
            $rankingMarcas[$marId]['monto'] += $row->total_monto;
        }

        usort($rankingCategorias, fn($a, $b) => $b['monto'] <=> $a['monto']);
        usort($rankingMarcas, fn($a, $b) => $b['monto'] <=> $a['monto']);

        return [
            'categorias' => $rankingCategorias,
            'marcas' => $rankingMarcas,
        ];
    }

    #[Computed]
    public function rendimientoPromociones()
    {
        if ($this->tab !== 'promociones') return [];

        $query = Venta::with('cupon')
            ->where('estado_pago', 'pagado')
            ->where(function($q) {
                $q->whereNotNull('cupon_id')->orWhere('descuento', '>', 0);
            });
            
        $query = $this->aplicarFiltroTiempo($query);
        $ventas = $query->get();

        $ranking = [];
        foreach ($ventas as $v) {
            $key = $v->cupon_id ? $v->cupon->codigo : 'Descuento Manual';
            
            if (!isset($ranking[$key])) {
                $ranking[$key] = [
                    'nombre' => $key,
                    'usos' => 0,
                    'monto_ventas' => 0,
                    'monto_descontado' => 0
                ];
            }
            
            $ranking[$key]['usos'] += 1;
            $ranking[$key]['monto_ventas'] += $v->total;
            $ranking[$key]['monto_descontado'] += $v->descuento;
        }

        usort($ranking, fn($a, $b) => $b['usos'] <=> $a['usos']);
        return $ranking;
    }

    #[Computed]
    public function mapaGeografico()
    {
        if ($this->tab !== 'geografico') return collect();

        $query = Venta::select('envio_departamento', DB::raw('COUNT(*) as total_pedidos'), DB::raw('SUM(total) as monto_total'))
            ->where('estado_pago', 'pagado')
            ->whereNotNull('envio_departamento');
            
        $query = $this->aplicarFiltroTiempo($query);
        
        return $query->groupBy('envio_departamento')
            ->orderBy('monto_total', 'desc')
            ->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Reportes Comerciales') }}</flux:heading>
            <flux:subheading>{{ __('Análisis de ventas por categoría, marca, promociones y distribución geográfica.') }}</flux:subheading>
        </div>
        
        <div class="flex gap-2">
            <flux:select wire:model.live="filtroTiempo" class="w-48">
                <option value="mes_actual">{{ __('Mes Actual') }}</option>
                <option value="historico">{{ __('Histórico (Todo)') }}</option>
            </flux:select>
        </div>
    </div>

    <!-- Navegación de Tabs -->
    <div class="flex gap-2 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700 pb-2">
        <button wire:click="setTab('marcas_categorias')" class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors {{ $tab === 'marcas_categorias' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}">
            <flux:icon name="chart-pie" class="size-4 inline-block mr-1" />
            {{ __('Marcas y Categorías') }}
        </button>
        <button wire:click="setTab('promociones')" class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors {{ $tab === 'promociones' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}">
            <flux:icon name="ticket" class="size-4 inline-block mr-1" />
            {{ __('Rendimiento Cupones') }}
        </button>
        <button wire:click="setTab('geografico')" class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors {{ $tab === 'geografico' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}">
            <flux:icon name="map" class="size-4 inline-block mr-1" />
            {{ __('Mapa Geográfico') }}
        </button>
    </div>

    <!-- Contenido de Tabs -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden">
        
        {{-- TAB: MARCAS Y CATEGORIAS --}}
        @if($tab === 'marcas_categorias')
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30">
                <flux:heading size="lg">{{ __('Rendimiento por Categoría y Marca') }}</flux:heading>
                <p class="text-sm text-zinc-500">{{ __('Descubre qué líneas de productos están generando mayores ingresos.') }}</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-zinc-200 dark:divide-zinc-700">
                <!-- Top Marcas -->
                <div>
                    <div class="p-4 bg-zinc-50/50 dark:bg-zinc-800/10 font-bold text-zinc-700 dark:text-zinc-300">
                        {{ __('Top Marcas') }}
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium">
                                    <th class="p-4">{{ __('Marca') }}</th>
                                    <th class="p-4 text-center">{{ __('Unidades') }}</th>
                                    <th class="p-4 text-right">{{ __('Ingresos (S/)') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @forelse($this->rendimientoCategoriasMarcas['marcas'] as $index => $m)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                        <td class="p-4">
                                            <span class="font-medium text-zinc-900 dark:text-white">{{ $m['nombre'] }}</span>
                                        </td>
                                        <td class="p-4 text-center text-zinc-600 dark:text-zinc-400">{{ $m['cantidad'] }}</td>
                                        <td class="p-4 text-right font-bold text-emerald-600 dark:text-emerald-400">S/ {{ number_format($m['monto'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="p-8 text-center text-zinc-500">
                                            {{ __('No hay datos para mostrar en este periodo.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Categorías -->
                <div>
                    <div class="p-4 bg-zinc-50/50 dark:bg-zinc-800/10 font-bold text-zinc-700 dark:text-zinc-300">
                        {{ __('Top Categorías') }}
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium">
                                    <th class="p-4">{{ __('Categoría') }}</th>
                                    <th class="p-4 text-center">{{ __('Unidades') }}</th>
                                    <th class="p-4 text-right">{{ __('Ingresos (S/)') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @forelse($this->rendimientoCategoriasMarcas['categorias'] as $index => $c)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                        <td class="p-4">
                                            <span class="font-medium text-zinc-900 dark:text-white">{{ $c['nombre'] }}</span>
                                        </td>
                                        <td class="p-4 text-center text-zinc-600 dark:text-zinc-400">{{ $c['cantidad'] }}</td>
                                        <td class="p-4 text-right font-bold text-emerald-600 dark:text-emerald-400">S/ {{ number_format($c['monto'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="p-8 text-center text-zinc-500">
                                            {{ __('No hay datos para mostrar en este periodo.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- TAB: PROMOCIONES --}}
        @if($tab === 'promociones')
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30">
                <flux:heading size="lg">{{ __('Rendimiento de Cupones y Promociones') }}</flux:heading>
                <p class="text-sm text-zinc-500">{{ __('Mide el retorno de tus campañas evaluando cuánto descuento diste vs cuánta venta generaste.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium">
                            <th class="p-4">{{ __('Código Promocional') }}</th>
                            <th class="p-4 text-center">{{ __('Veces Usado') }}</th>
                            <th class="p-4 text-right">{{ __('Monto Descontado (S/)') }}</th>
                            <th class="p-4 text-right">{{ __('Ventas Generadas (S/)') }}</th>
                            <th class="p-4 text-right">{{ __('ROI Aprox.') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->rendimientoPromociones as $promo)
                            @php
                                $roi = $promo['monto_descontado'] > 0 ? ($promo['monto_ventas'] / $promo['monto_descontado']) : 0;
                            @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="p-4 font-bold text-zinc-900 dark:text-white">
                                    <flux:badge color="zinc">{{ $promo['nombre'] }}</flux:badge>
                                </td>
                                <td class="p-4 text-center text-zinc-600 dark:text-zinc-400 font-medium">{{ $promo['usos'] }}</td>
                                <td class="p-4 text-right text-rose-600 dark:text-rose-400 font-medium">- S/ {{ number_format($promo['monto_descontado'], 2) }}</td>
                                <td class="p-4 text-right font-bold text-emerald-600 dark:text-emerald-400">S/ {{ number_format($promo['monto_ventas'], 2) }}</td>
                                <td class="p-4 text-right">
                                    @if($roi > 0)
                                        <span class="text-xs text-zinc-500">{{ __('Retorno x') }}{{ number_format($roi, 1) }}</span>
                                    @else
                                        <span class="text-xs text-zinc-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-zinc-500">
                                    {{ __('No se aplicaron cupones ni descuentos en el periodo seleccionado.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- TAB: GEOGRAFICO --}}
        @if($tab === 'geografico')
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30">
                <flux:heading size="lg">{{ __('Distribución Geográfica (Departamentos)') }}</flux:heading>
                <p class="text-sm text-zinc-500">{{ __('Descubre en qué regiones del país tienes mayor demanda y ajusta tus estrategias de envío y publicidad.') }}</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium">
                            <th class="p-4 w-12 text-center">#</th>
                            <th class="p-4">{{ __('Departamento') }}</th>
                            <th class="p-4 text-center">{{ __('Pedidos') }}</th>
                            <th class="p-4 text-right">{{ __('Ingresos (S/)') }}</th>
                            <th class="p-4 w-1/3">{{ __('Participación') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @php
                            $totalMontoGlobal = collect($this->mapaGeografico)->sum('monto_total');
                        @endphp
                        @forelse($this->mapaGeografico as $index => $geo)
                            @php
                                $porcentaje = $totalMontoGlobal > 0 ? ($geo['monto_total'] / $totalMontoGlobal) * 100 : 0;
                            @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                <td class="p-4 text-center font-bold text-zinc-400">{{ $index + 1 }}</td>
                                <td class="p-4 font-medium text-zinc-900 dark:text-white">{{ $geo['envio_departamento'] }}</td>
                                <td class="p-4 text-center text-zinc-600 dark:text-zinc-400">{{ $geo['total_pedidos'] }}</td>
                                <td class="p-4 text-right font-bold text-emerald-600 dark:text-emerald-400">S/ {{ number_format($geo['monto_total'], 2) }}</td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                            <div class="bg-blue-500 h-full rounded-full" style="width: {{ $porcentaje }}%"></div>
                                        </div>
                                        <span class="text-xs text-zinc-500 w-10 text-right">{{ number_format($porcentaje, 1) }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-zinc-500">
                                    {{ __('No hay datos de envíos para mostrar en este periodo.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</div>
