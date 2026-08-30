<?php

use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Mis Compras')] #[Layout('layouts.settings')] class extends Component {
    use WithPagination;

    public ?int $selectedVentaId = null;
    public bool $showDetailModal = false;

    #[Computed]
    public function compras()
    {
        return Venta::where('user_id', Auth::id())
            ->with(['tipoDocumento', 'detalles.variacion.producto.marca', 'detalles.variacion.valores.atributo'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function verDetalle(int $id): void
    {
        $this->selectedVentaId = $id;
        $this->showDetailModal = true;
    }

    #[Computed]
    public function selectedVenta(): ?Venta
    {
        if (! $this->selectedVentaId) {
            return null;
        }

        return Venta::where('user_id', Auth::id())
            ->with(['tipoDocumento', 'detalles.variacion.producto.marca', 'detalles.variacion.valores.atributo'])
            ->find($this->selectedVentaId);
    }

    public function descargarComprobante(int $id): StreamedResponse
    {
        $venta = Venta::where('user_id', Auth::id())
            ->with(['user', 'tipoDocumento', 'detalles.variacion.producto', 'detalles.variacion.valores.atributo', 'detalles.unidadMedida'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.comprobante', compact('venta'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'comprobante-' . $venta->serie . '-' . $venta->correlativo . '.pdf');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Mis Compras') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Mis Compras')" :subheading="__('Revisa el estado de tus pedidos, detalles de productos y comprobantes de pago.')">
        
        @if($this->compras->count() > 0)
            <div class="space-y-4">
                @foreach($this->compras as $compra)
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 shadow-sm transition-all hover:border-zinc-300 dark:hover:border-zinc-700">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <span class="font-extrabold text-base text-zinc-900 dark:text-white">
                                        {{ $compra->tipoDocumento->nombre ?? 'Comprobante' }}
                                    </span>
                                    <span class="font-mono text-xs px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-400 font-bold">
                                        {{ $compra->serie }}-{{ str_pad($compra->correlativo, 6, '0', STR_PAD_LEFT) }}
                                    </span>
                                </div>
                                <p class="text-xs text-zinc-400 mt-1">
                                    {{ __('Fecha:') }} {{ $compra->created_at->format('d/m/Y h:i A') }}
                                </p>
                            </div>

                            <div class="flex items-center gap-3">
                                <!-- Estado de Pago -->
                                @if($compra->estado_pago === 'pagado')
                                    <flux:badge color="success" size="sm" icon="check-circle">{{ __('Pagado') }}</flux:badge>
                                @elseif($compra->estado_pago === 'pendiente')
                                    <flux:badge color="warning" size="sm" icon="clock">{{ __('Pendiente de Pago') }}</flux:badge>
                                @else
                                    <flux:badge color="danger" size="sm" icon="x-circle">{{ __('Cancelado') }}</flux:badge>
                                @endif

                                <!-- Estado de Despacho -->
                                @if($compra->estado_despacho === 'entregado')
                                    <flux:badge color="success" size="sm" icon="check">{{ __('Entregado') }}</flux:badge>
                                @elseif($compra->estado_despacho === 'despachado')
                                    <flux:badge color="indigo" size="sm" icon="truck">{{ __('En Camino') }}</flux:badge>
                                @elseif($compra->estado_despacho === 'preparado')
                                    <flux:badge color="sky" size="sm" icon="cube">{{ __('Listo para entrega') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm" icon="arrow-path">{{ __('En Preparación') }}</flux:badge>
                                @endif
                            </div>
                        </div>

                        <!-- Resumen de items comprados -->
                        <div class="py-4 space-y-3">
                            @foreach($compra->detalles as $detalle)
                                @php
                                    $producto = $detalle->variacion?->producto;
                                    $descAttr = $detalle->variacion?->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(' | ');
                                    $imagenUrl = $producto?->getFirstMediaUrl('imagen_principal', 'thumb') 
                                        ?: $producto?->getFirstMediaUrl('imagen_principal')
                                        ?: $producto?->getFirstMediaUrl('galeria', 'thumb');
                                @endphp
                                <div class="flex items-center gap-4">
                                    <div class="size-12 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center overflow-hidden shrink-0 border border-zinc-200/50 dark:border-zinc-800">
                                        @if($imagenUrl)
                                            <img src="{{ $imagenUrl }}" alt="{{ $producto->nombre ?? 'Mochila' }}" class="w-full h-full object-cover" />
                                        @else
                                            <flux:icon name="archive-box" class="size-6 text-zinc-400" />
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="text-sm font-bold text-zinc-900 dark:text-white truncate">
                                            {{ $producto->nombre ?? 'Mochila Logan' }}
                                        </h4>
                                        <p class="text-xxs text-zinc-500 truncate">{{ $descAttr }}</p>
                                        <p class="text-xxs text-zinc-400 font-semibold">{{ __('Cant:') }} {{ $detalle->cantidad }} x S/ {{ number_format($detalle->precio_unitario, 2) }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-sm font-extrabold text-zinc-900 dark:text-white">
                                            S/ {{ number_format($detalle->subtotal, 2) }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-950/40 -mx-5 -mb-5 p-5 rounded-b-2xl">
                            <div>
                                <span class="text-xs text-zinc-500">{{ __('Medio de pago:') }}</span>
                                <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 capitalize">
                                    {{ str_replace('_', ' ', $compra->metodo_pago) }}
                                </span>
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <span class="text-xs text-zinc-500 mr-2">{{ __('Total:') }}</span>
                                    <span class="text-lg font-extrabold text-zinc-900 dark:text-white">
                                        S/ {{ number_format($compra->total, 2) }}
                                    </span>
                                </div>

                                <flux:button variant="primary" size="sm" icon="eye" wire:click="verDetalle({{ $compra->id }})">
                                    {{ __('Ver Detalle') }}
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="pt-4">
                    {{ $this->compras->links() }}
                </div>
            </div>
        @else
            <!-- Estado Vacío -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-3xl text-center py-16 px-6">
                <flux:icon name="shopping-bag" class="size-16 mx-auto text-zinc-300 dark:text-zinc-700 mb-4" />
                <flux:heading size="lg" class="text-zinc-800 dark:text-zinc-200">{{ __('Aún no tienes compras registradas') }}</flux:heading>
                <flux:subheading class="mt-1 mb-6">{{ __('Cuando realices tu primer pedido en la tienda, aparecerá aquí con su estado y seguimiento en tiempo real.') }}</flux:subheading>
                <flux:button variant="primary" :href="route('catalogo')" wire:navigate class="rounded-xl font-bold">
                    {{ __('Explorar Catálogo') }}
                </flux:button>
            </div>
        @endif

        <!-- Modal de Detalle de la Compra -->
        <flux:modal wire:model="showDetailModal" class="max-w-2xl">
            @if($this->selectedVenta)
                @php $v = $this->selectedVenta; @endphp
                <div class="space-y-6">
                    <div class="flex items-center justify-between pb-3 border-b border-zinc-100 dark:border-zinc-800">
                        <div>
                            <flux:heading size="lg">{{ $v->tipoDocumento->nombre ?? 'Comprobante' }}</flux:heading>
                            <flux:subheading class="font-mono font-bold">{{ $v->serie }}-{{ str_pad($v->correlativo, 6, '0', STR_PAD_LEFT) }}</flux:subheading>
                        </div>
                        <flux:badge color="{{ $v->estado_pago === 'pagado' ? 'success' : ($v->estado_pago === 'pendiente' ? 'warning' : 'danger') }}">
                            {{ ucfirst($v->estado_pago) }}
                        </flux:badge>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-xs">
                        <div>
                            <span class="text-zinc-400 block">{{ __('Fecha de Compra:') }}</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $v->created_at->format('d/m/Y h:i A') }}</span>
                        </div>
                        <div>
                            <span class="text-zinc-400 block">{{ __('Medio de Pago:') }}</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200 capitalize">{{ str_replace('_', ' ', $v->metodo_pago) }}</span>
                        </div>
                        @if($v->comentarios)
                            <div class="col-span-2 bg-zinc-50 dark:bg-zinc-800/50 p-3 rounded-lg">
                                <span class="text-zinc-400 block mb-1">{{ __('Notas / Entrega:') }}</span>
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $v->comentarios }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Lista de Productos -->
                    <div class="space-y-3">
                        <flux:text size="sm" class="font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">{{ __('Productos Comprados') }}</flux:text>
                        <div class="border border-zinc-100 dark:border-zinc-800 rounded-xl divide-y divide-zinc-100 dark:divide-zinc-800 overflow-hidden">
                            @foreach($v->detalles as $det)
                                @php
                                    $prod = $det->variacion?->producto;
                                    $detallesTexto = $det->variacion?->valores->map(fn($val) => $val->atributo->nombre . ': ' . $val->valor)->implode(' | ');
                                @endphp
                                <div class="p-3.5 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="font-bold text-zinc-900 dark:text-white">{{ $prod->nombre ?? 'Mochila' }}</div>
                                        <div class="text-xxs text-zinc-500">{{ $detallesTexto }}</div>
                                        <div class="text-xxs text-zinc-400 mt-0.5">{{ __('Cant:') }} {{ $det->cantidad }} x S/ {{ number_format($det->precio_unitario, 2) }}</div>
                                    </div>
                                    <div class="font-extrabold text-zinc-900 dark:text-white">
                                        S/ {{ number_format($det->subtotal, 2) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Resumen de Costos -->
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-xl space-y-2 text-xs">
                        <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                            <span>{{ __('Subtotal') }}</span>
                            <span>S/ {{ number_format($v->subtotal, 2) }}</span>
                        </div>
                        @if($v->descuento > 0)
                            <div class="flex justify-between text-emerald-600 font-semibold">
                                <span>{{ __('Descuento Cupón') }}</span>
                                <span>- S/ {{ number_format($v->descuento, 2) }}</span>
                            </div>
                        @endif
                        @if($v->costo_envio > 0)
                            <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                                <span>{{ __('Costo de Envío') }}</span>
                                <span>S/ {{ number_format($v->costo_envio, 2) }}</span>
                            </div>
                        @endif
                        <hr class="border-zinc-200 dark:border-zinc-700" />
                        <div class="flex justify-between text-sm font-extrabold text-zinc-900 dark:text-white">
                            <span>{{ __('Total') }}</span>
                            <span>S/ {{ number_format($v->total, 2) }}</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center pt-2">
                        <flux:button variant="outline" icon="arrow-down-tray" wire:click="descargarComprobante({{ $v->id }})">
                            {{ __('Descargar Comprobante PDF') }}
                        </flux:button>
                        <flux:button variant="ghost" wire:click="$set('showDetailModal', false)">
                            {{ __('Cerrar') }}
                        </flux:button>
                    </div>
                </div>
            @endif
        </flux:modal>
    </x-pages::settings.layout>
</section>
