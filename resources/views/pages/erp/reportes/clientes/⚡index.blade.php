<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

new #[Title('Reportes de Clientes (CRM)')] class extends Component {
    #[Url]
    public string $tab = 'top_buyers';

    public function setTab($tabName)
    {
        $this->tab = $tabName;
    }

    #[Computed]
    public function topBuyers()
    {
        if ($this->tab !== 'top_buyers') return collect();

        return User::select(
                'users.id', 'users.name', 'users.email', 
                DB::raw('COUNT(ventas.id) as total_pedidos'), 
                DB::raw('SUM(ventas.total) as ltv_total'),
                DB::raw('MAX(ventas.created_at) as ultima_compra')
            )
            ->join('ventas', 'users.id', '=', 'ventas.user_id')
            ->where('ventas.estado_pago', 'pagado')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderBy('ltv_total', 'desc')
            ->take(100)
            ->get();
    }

    #[Computed]
    public function inactivos()
    {
        if ($this->tab !== 'inactivos') return collect();

        $fechaLimite = Carbon::now()->subMonths(6);

        return User::select(
                'users.id', 'users.name', 'users.email',
                DB::raw('MAX(ventas.created_at) as ultima_compra'),
                DB::raw('COUNT(ventas.id) as total_pedidos'),
                DB::raw('SUM(ventas.total) as ltv_total')
            )
            ->join('ventas', 'users.id', '=', 'ventas.user_id')
            ->where('ventas.estado_pago', 'pagado')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->having('ultima_compra', '<', $fechaLimite)
            ->orderBy('ultima_compra', 'asc')
            ->get();
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Reportes de Clientes (CRM)') }}</flux:heading>
        <flux:subheading>{{ __('Análisis de lealtad, valor de vida (LTV) y clientes en riesgo de abandono.') }}</flux:subheading>
    </div>

    <!-- Navegación de Tabs -->
    <div class="flex gap-2 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700 pb-2">
        <button wire:click="setTab('top_buyers')" class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors {{ $tab === 'top_buyers' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}">
            <flux:icon name="star" class="size-4 inline-block mr-1" />
            {{ __('Top Buyers (Mejores Clientes)') }}
        </button>
        <button wire:click="setTab('inactivos')" class="px-4 py-2 text-sm font-medium rounded-lg whitespace-nowrap transition-colors {{ $tab === 'inactivos' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800' }}">
            <flux:icon name="user-minus" class="size-4 inline-block mr-1" />
            {{ __('Clientes Inactivos (Churn)') }}
        </button>
    </div>

    <!-- Contenido de Tabs -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden">
        
        {{-- TAB: TOP BUYERS --}}
        @if($tab === 'top_buyers')
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30 flex justify-between items-center">
                <div>
                    <flux:heading size="lg">{{ __('Ranking de Mejores Clientes (VIPs)') }}</flux:heading>
                    <p class="text-sm text-zinc-500">{{ __('Tus 100 clientes más valiosos ordenados por el monto histórico que han gastado (LTV).') }}</p>
                </div>
                <flux:badge color="amber" size="sm" class="hidden sm:inline-flex">Top 100 Histórico</flux:badge>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium">
                            <th class="p-4 w-12 text-center">#</th>
                            <th class="p-4">{{ __('Cliente') }}</th>
                            <th class="p-4 text-center">{{ __('Pedidos Totales') }}</th>
                            <th class="p-4 text-center">{{ __('Última Compra') }}</th>
                            <th class="p-4 text-right">{{ __('Monto Gastado (S/)') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->topBuyers as $index => $cliente)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="p-4 text-center font-bold text-zinc-400">{{ $index + 1 }}</td>
                                <td class="p-4">
                                    <div class="font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                                        {{ $cliente->name }}
                                        @if($index < 5)
                                            <flux:icon name="sparkles" class="size-4 text-amber-500" />
                                        @endif
                                    </div>
                                    <div class="text-xs text-zinc-500">{{ $cliente->email }}</div>
                                </td>
                                <td class="p-4 text-center font-medium text-zinc-700 dark:text-zinc-300">{{ $cliente->total_pedidos }}</td>
                                <td class="p-4 text-center text-zinc-600 dark:text-zinc-400">
                                    {{ \Carbon\Carbon::parse($cliente->ultima_compra)->diffForHumans() }}
                                </td>
                                <td class="p-4 text-right font-black text-emerald-600 dark:text-emerald-400">
                                    S/ {{ number_format($cliente->ltv_total, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-zinc-500">
                                    {{ __('Aún no hay clientes registrados con compras pagadas.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- TAB: CLIENTES INACTIVOS --}}
        @if($tab === 'inactivos')
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30">
                <flux:heading size="lg">{{ __('Clientes en Riesgo de Abandono (Inactivos +6 meses)') }}</flux:heading>
                <p class="text-sm text-zinc-500">{{ __('Clientes que te han comprado antes pero llevan más de 180 días sin hacer un pedido. Ideal para campañas de retargeting.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium">
                            <th class="p-4">{{ __('Cliente') }}</th>
                            <th class="p-4 text-center">{{ __('Días Inactivo') }}</th>
                            <th class="p-4 text-center">{{ __('Valor Perdido') }}</th>
                            <th class="p-4">{{ __('Acción Sugerida') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->inactivos as $cliente)
                            @php
                                $diasInactivo = \Carbon\Carbon::parse($cliente->ultima_compra)->diffInDays(now());
                            @endphp
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="p-4">
                                    <div class="font-bold text-zinc-900 dark:text-white">{{ $cliente->name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $cliente->email }}</div>
                                    <div class="text-[10px] text-zinc-400 mt-1">{{ __('Últ. compra: ') }} {{ \Carbon\Carbon::parse($cliente->ultima_compra)->format('d/m/Y') }}</div>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="font-bold text-rose-600 dark:text-rose-400">{{ $diasInactivo }} días</span>
                                </td>
                                <td class="p-4 text-center text-zinc-600 dark:text-zinc-400">
                                    S/ {{ number_format($cliente->ltv_total, 2) }} <span class="text-xs block text-zinc-400">({{ $cliente->total_pedidos }} pedidos)</span>
                                </td>
                                <td class="p-4">
                                    <flux:button size="sm" icon="envelope" variant="outline">{{ __('Enviar Email') }}</flux:button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-zinc-500">
                                    {{ __('¡Excelente! No tienes clientes inactivos por más de 6 meses.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</div>
