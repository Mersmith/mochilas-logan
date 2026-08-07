<?php

use App\Models\GuiaInventario;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalles de Guía de Inventario')] class extends Component {
    public GuiaInventario $guia;

    public function mount(GuiaInventario $guia)
    {
        // Esta vista es de solo lectura, por lo que cargamos todo
        $guia->load(['proveedor', 'almacenOrigen.sede', 'almacenDestino.sede', 'tipoDocumento', 'creador', 'detalles.variacion.producto', 'detalles.unidadMedida']);
        $this->guia = $guia;
    }
}; ?>

<div class="space-y-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">{{ __('Guía') }} {{ $guia->serie }}-{{ str_pad($guia->correlativo, 6, '0', STR_PAD_LEFT) }}</flux:heading>
                <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-sm font-medium border
                    {{ $guia->estado === 'Borrador' ? 'bg-zinc-100 text-zinc-700 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700' : '' }}
                    {{ $guia->estado === 'Procesado' ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800/50' : '' }}
                    {{ $guia->estado === 'En Tránsito' ? 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800/50' : '' }}
                    {{ $guia->estado === 'Anulado' ? 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800/50' : '' }}
                ">
                    {{ $guia->estado }}
                </span>
            </div>
            <flux:subheading>{{ __('Detalles y estado del movimiento de inventario.') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            @if($guia->estado === 'Borrador')
                <flux:button variant="primary" icon="pencil-square" href="{{ route('admin.guias.edit', $guia->id) }}" wire:navigate>
                    {{ __('Editar Borrador') }}
                </flux:button>
            @endif
            <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.guias.index') }}" wire:navigate>
                {{ __('Volver a la lista') }}
            </flux:button>
        </div>
    </div>

    @if($guia->estado === 'Borrador')
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 text-amber-800 dark:text-amber-400 p-4 rounded-xl flex items-center justify-between">
            <div class="text-sm">{{ __('Esta guía aún es un borrador. Haz clic en Editar Borrador para modificarla o procesarla.') }}</div>
        </div>
    @elseif($guia->estado === 'Anulado')
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50 text-red-800 dark:text-red-400 p-4 rounded-xl">
            <div class="text-sm font-bold">{{ __('Esta guía se encuentra ANULADA.') }}</div>
            <div class="text-xs">{{ __('Los movimientos generados en el Kardex y los saldos de inventario han sido revertidos.') }}</div>
        </div>
    @endif

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">{{ __('Tipo de Movimiento') }}</div>
                <div class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $guia->tipo_movimiento }}</div>
            </div>
            <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">{{ __('Fecha') }}</div>
                <div class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $guia->fecha_movimiento->format('d/m/Y') }}</div>
            </div>
            <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">{{ __('Documento') }}</div>
                <div class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $guia->tipoDocumento->nombre ?? 'N/A' }}</div>
            </div>
            <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">{{ __('Creado Por') }}</div>
                <div class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $guia->creador->name ?? 'Sistema' }}</div>
            </div>
        </div>

        <hr class="border-zinc-200 dark:border-zinc-700">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @if($guia->tipo_movimiento === 'Entrada')
                <div>
                    <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">{{ __('Proveedor') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $guia->proveedor->razon_social ?? 'N/A' }}</div>
                    @if($guia->proveedor?->ruc)
                        <div class="text-xs text-zinc-500">RUC: {{ $guia->proveedor->ruc }}</div>
                    @endif
                </div>
                <div>
                    <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">{{ __('Almacén de Destino') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $guia->almacenDestino->nombre ?? 'N/A' }}</div>
                    <div class="text-xs text-zinc-500">{{ $guia->almacenDestino->sede->nombre ?? '' }}</div>
                </div>
            @elseif($guia->tipo_movimiento === 'Salida')
                <div>
                    <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">{{ __('Almacén de Origen') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $guia->almacenOrigen->nombre ?? 'N/A' }}</div>
                    <div class="text-xs text-zinc-500">{{ $guia->almacenOrigen->sede->nombre ?? '' }}</div>
                </div>
            @elseif($guia->tipo_movimiento === 'Transferencia')
                <div>
                    <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">{{ __('Almacén de Origen') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $guia->almacenOrigen->nombre ?? 'N/A' }}</div>
                    <div class="text-xs text-zinc-500">{{ $guia->almacenOrigen->sede->nombre ?? '' }}</div>
                </div>
                <div>
                    <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">{{ __('Almacén de Destino') }}</div>
                    <div class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $guia->almacenDestino->nombre ?? 'N/A' }}</div>
                    <div class="text-xs text-zinc-500">{{ $guia->almacenDestino->sede->nombre ?? '' }}</div>
                </div>
            @endif
        </div>
        
        @if($guia->motivo)
            <hr class="border-zinc-200 dark:border-zinc-700">
            <div>
                <div class="text-xs text-zinc-500 uppercase tracking-wider font-semibold">{{ __('Motivo / Observación') }}</div>
                <div class="mt-1 text-sm text-zinc-700 dark:text-zinc-300 bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg">{{ $guia->motivo }}</div>
            </div>
        @endif
    </div>

    {{-- Productos List (Read-only) --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40">
            <flux:heading size="lg">{{ __('Detalles de los Productos') }}</flux:heading>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold">
                        <th class="p-4">{{ __('Producto / Variación') }}</th>
                        <th class="p-4 text-center">{{ __('Cant. Ingresada') }}</th>
                        <th class="p-4 text-center">{{ __('UM Base (Unidades)') }}</th>
                        @if($guia->tipo_movimiento === 'Entrada')
                            <th class="p-4 text-right">{{ __('C. Unitario') }}</th>
                            <th class="p-4 text-right">{{ __('Subtotal') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($guia->detalles as $detalle)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="p-4">
                                <div class="font-medium text-zinc-900 dark:text-white">
                                    {{ optional(optional($detalle->variacion)->producto)->nombre }}
                                </div>
                                <div class="text-xs text-zinc-500">
                                    SKU: {{ optional($detalle->variacion)->sku }}
                                </div>
                            </td>
                            <td class="p-4 text-center font-medium">
                                {{ $detalle->cantidad }} {{ optional($detalle->unidadMedida)->nombre }}
                            </td>
                            <td class="p-4 text-center text-zinc-600 dark:text-zinc-400 text-xs">
                                {{ $detalle->cantidad_base }} 
                                @if($detalle->factor_conversion > 1)
                                    <span class="text-zinc-400 italic">(Factor: x{{ $detalle->factor_conversion }})</span>
                                @endif
                            </td>
                            @if($guia->tipo_movimiento === 'Entrada')
                                <td class="p-4 text-right text-zinc-600 dark:text-zinc-400">
                                    ${{ number_format($detalle->costo_unitario, 2) }}
                                </td>
                                <td class="p-4 text-right font-medium text-emerald-600 dark:text-emerald-400">
                                    ${{ number_format($detalle->costo_total, 2) }}
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $guia->tipo_movimiento === 'Entrada' ? 5 : 3 }}" class="text-center py-12 text-zinc-500">
                                {{ __('No hay productos en esta guía.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($guia->tipo_movimiento === 'Entrada' && $guia->detalles->count() > 0)
                    <tfoot>
                        <tr class="border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40">
                            <td colspan="4" class="p-4 text-right font-semibold text-zinc-900 dark:text-white">
                                {{ __('Total General:') }}
                            </td>
                            <td class="p-4 text-right font-bold text-emerald-600 dark:text-emerald-400 text-lg">
                                ${{ number_format($guia->detalles->sum('costo_total'), 2) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
