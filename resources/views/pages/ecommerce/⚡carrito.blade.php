<?php

use App\Models\Variacion;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Mi Bolsa de Compras'), Layout('layouts.publico')] class extends Component {

    /**
     * Get cart items from session with full variation data.
     */
    #[Computed]
    public function items(): array
    {
        $cart = session()->get('public_cart', []);
        $items = [];

        $nombreListaPrecio = 'Precio Menor';
        if (auth()->check() && auth()->user()->cliente && auth()->user()->cliente->tipo_cliente === 'mayorista') {
            $nombreListaPrecio = auth()->user()->cliente->listaPrecio->nombre ?? 'Precio Mayor';
        }

        foreach ($cart as $key => $item) {
            $variacion = Variacion::with(['producto.marca', 'producto.categoria', 'producto.descuentos', 'valores.atributo', 'precios.listaPrecio', 'inventarios'])
                ->find($item['variacion_id']);

            if (!$variacion) {
                continue;
            }

            $stock = (int) $variacion->inventarios->sum('stock_base');
            $basePrice = (float) ($variacion->precios->firstWhere('listaPrecio.nombre', $nombreListaPrecio)?->precio ?? 0.00);
            
            // Check active discount
            $activeDiscount = $variacion->producto->descuentos
                ->where('activo', true)
                ->filter(fn($d) => (!$d->fecha_inicio || $d->fecha_inicio <= now()) && (!$d->fecha_fin || $d->fecha_fin >= now()))
                ->first();

            if ($activeDiscount) {
                $pct = (int) $activeDiscount->porcentaje_descuento;
                $finalPrice = round($basePrice * (1 - $pct / 100), 2);
            } else {
                $pct = 0;
                $finalPrice = $basePrice;
            }

            $descAttr = $variacion->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(' | ');

            $items[$key] = [
                'key' => $key,
                'variacion_id' => $variacion->id,
                'nombre' => $variacion->producto->nombre,
                'marca' => $variacion->producto->marca->nombre,
                'sku' => $variacion->sku,
                'detalles' => $descAttr,
                'cantidad' => min($item['cantidad'], $stock),
                'stock' => $stock,
                'precio_base' => $basePrice,
                'precio_final' => $finalPrice,
                'porcentaje_descuento' => $pct,
                'subtotal' => round($finalPrice * min($item['cantidad'], $stock), 2),
                'producto_id' => $variacion->producto->id,
                'slug' => \Illuminate\Support\Str::slug($variacion->producto->nombre),
            ];
        }

        return $items;
    }

    /**
     * Computed order summary.
     */
    #[Computed]
    public function resumen(): array
    {
        $items = $this->items;
        $totalItems = array_sum(array_column($items, 'cantidad'));
        $subtotalBase = 0;
        $subtotalFinal = 0;

        foreach ($items as $item) {
            $subtotalBase += $item['precio_base'] * $item['cantidad'];
            $subtotalFinal += $item['subtotal'];
        }

        $descuentoTotal = round($subtotalBase - $subtotalFinal, 2);

        return [
            'total_items' => $totalItems,
            'subtotal_base' => round($subtotalBase, 2),
            'descuento_total' => $descuentoTotal,
            'total' => round($subtotalFinal, 2),
        ];
    }

    /**
     * Update item quantity.
     */
    public function updateQuantity(string $key, int $delta): void
    {
        $cart = session()->get('public_cart', []);

        if (!isset($cart[$key])) {
            return;
        }

        $variacion = Variacion::with('inventarios')->find($cart[$key]['variacion_id']);
        $stock = $variacion ? (int) $variacion->inventarios->sum('stock_base') : 0;

        $newQty = $cart[$key]['cantidad'] + $delta;
        $newQty = max(1, min($stock, $newQty));
        $cart[$key]['cantidad'] = $newQty;

        session()->put('public_cart', $cart);
        unset($this->items, $this->resumen);
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(string $key): void
    {
        $cart = session()->get('public_cart', []);
        unset($cart[$key]);
        session()->put('public_cart', $cart);
        unset($this->items, $this->resumen);

        Flux::toast(variant: 'success', text: __('Producto eliminado de la bolsa.'));
    }

    /**
     * Clear all items from cart.
     */
    public function vaciarCarrito(): void
    {
        session()->forget('public_cart');
        unset($this->items, $this->resumen);

        Flux::toast(variant: 'success', text: __('Se ha vaciado tu bolsa de compras.'));
    }
}; ?>

<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <flux:heading size="xl" class="font-extrabold tracking-tight text-zinc-900 dark:text-white">
                {{ __('Carro') }}
                <span class="text-base font-medium text-zinc-400">({{ $this->resumen['total_items'] }} {{ __('productos') }})</span>
            </flux:heading>
        </div>
        <a href="{{ route('catalogo') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-500 hover:text-black dark:hover:text-white transition-colors">
            <flux:icon name="arrow-left" class="size-4" />
            {{ __('Seguir comprando') }}
        </a>
    </div>

    @if(count($this->items) > 0)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Columna Izquierda: Productos en el Carrito -->
            <div class="lg:col-span-2 space-y-4">
                @foreach($this->items as $item)
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 flex gap-5 items-start relative group transition-all hover:shadow-sm">
                        <!-- Imagen placeholder -->
                        <div class="w-24 h-24 flex-shrink-0 bg-zinc-100 dark:bg-zinc-950 rounded-xl flex items-center justify-center border border-zinc-200/50 dark:border-zinc-800">
                            <a href="{{ route('producto.detalle', ['producto' => $item['producto_id'], 'slug' => $item['slug']]) }}" wire:navigate>
                                <flux:icon name="archive-box" class="size-10 text-zinc-300 dark:text-zinc-700" />
                            </a>
                        </div>

                        <!-- Información del producto -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <a href="{{ route('producto.detalle', ['producto' => $item['producto_id'], 'slug' => $item['slug']]) }}" wire:navigate class="hover:underline">
                                        <flux:heading size="md" class="font-bold text-zinc-900 dark:text-white truncate">{{ $item['nombre'] }}</flux:heading>
                                    </a>
                                    <div class="text-xxs font-semibold text-zinc-400 uppercase tracking-wider mt-0.5">{{ $item['marca'] }}</div>
                                    <div class="text-xxs text-zinc-500 mt-1">{{ $item['detalles'] }}</div>
                                    <div class="text-xxs text-zinc-400 mt-0.5">SKU: {{ $item['sku'] }}</div>
                                </div>

                                <!-- Precios -->
                                <div class="text-right flex-shrink-0">
                                    <div class="text-lg font-extrabold text-zinc-900 dark:text-white">S/ {{ number_format($item['precio_final'], 2) }}</div>
                                    @if($item['porcentaje_descuento'] > 0)
                                        <div class="text-xxs text-zinc-400 line-through">S/ {{ number_format($item['precio_base'], 2) }}</div>
                                        <span class="inline-block mt-0.5 bg-rose-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full">-{{ $item['porcentaje_descuento'] }}%</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Controles: Cantidad y Eliminar -->
                            <div class="flex items-center justify-between mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800/50">
                                <!-- Selector de Cantidad -->
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden bg-zinc-50 dark:bg-zinc-950 h-9">
                                        <button wire:click.prevent="updateQuantity('{{ $item['key'] }}', -1)" class="px-2.5 text-zinc-500 hover:text-black dark:hover:text-white font-bold text-sm h-full transition-colors {{ $item['cantidad'] <= 1 ? 'opacity-40 cursor-not-allowed' : '' }}">−</button>
                                        <span class="w-8 text-center font-bold text-sm text-zinc-900 dark:text-white">{{ $item['cantidad'] }}</span>
                                        <button wire:click.prevent="updateQuantity('{{ $item['key'] }}', 1)" class="px-2.5 text-zinc-500 hover:text-black dark:hover:text-white font-bold text-sm h-full transition-colors {{ $item['cantidad'] >= $item['stock'] ? 'opacity-40 cursor-not-allowed' : '' }}">+</button>
                                    </div>
                                    <span class="text-xxs text-zinc-400">{{ __('Máx') }} {{ $item['stock'] }} {{ __('uds.') }}</span>
                                </div>

                                <!-- Subtotal y eliminar -->
                                <div class="flex items-center gap-4">
                                    <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200">S/ {{ number_format($item['subtotal'], 2) }}</span>
                                    <flux:button variant="ghost" size="sm" icon="x-mark" wire:click.prevent="removeItem('{{ $item['key'] }}')" class="text-zinc-400 hover:text-rose-600" title="{{ __('Eliminar') }}" />
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <!-- Vaciar carrito -->
                <div class="flex justify-end pt-2">
                    <flux:button variant="ghost" size="sm" wire:click.prevent="vaciarCarrito" class="text-xs text-zinc-400 hover:text-rose-600">
                        {{ __('Vaciar carrito') }}
                    </flux:button>
                </div>
            </div>

            <!-- Columna Derecha: Resumen de la Orden -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 space-y-5 shadow-sm sticky top-24">
                    <flux:heading size="lg" class="font-extrabold text-zinc-900 dark:text-white">{{ __('Resumen de la orden') }}</flux:heading>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-zinc-500">{{ __('Productos') }} ({{ $this->resumen['total_items'] }})</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200">S/ {{ number_format($this->resumen['subtotal_base'], 2) }}</span>
                        </div>

                        @if($this->resumen['descuento_total'] > 0)
                            <div class="flex justify-between">
                                <span class="text-zinc-500">{{ __('Descuentos') }}</span>
                                <span class="font-semibold text-emerald-600">- S/ {{ number_format($this->resumen['descuento_total'], 2) }}</span>
                            </div>
                        @endif
                    </div>

                    <hr class="border-zinc-200 dark:border-zinc-800" />

                    <div class="flex justify-between items-baseline">
                        <span class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Total') }}</span>
                        <span class="text-2xl font-extrabold text-zinc-900 dark:text-white">S/ {{ number_format($this->resumen['total'], 2) }}</span>
                    </div>

                    <flux:button variant="primary" :href="route('checkout')" wire:navigate class="w-full h-12 rounded-xl font-bold text-base">
                        {{ __('Continuar compra') }}
                    </flux:button>

                    <p class="text-xxs text-zinc-400 text-center leading-relaxed">
                        {{ __('Al continuar, aceptas nuestros Términos y Condiciones de Venta y la Política de Privacidad de Mochilas Logan.') }}
                    </p>
                </div>
            </div>
        </div>
    @else
        <!-- Carrito Vacío -->
        <div class="bg-zinc-50 dark:bg-zinc-900 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-3xl text-center py-20 px-6">
            <flux:icon name="shopping-bag" class="size-16 mx-auto text-zinc-300 dark:text-zinc-700 mb-5" />
            <flux:heading size="lg" class="text-zinc-700 dark:text-zinc-300">{{ __('Tu bolsa de compras está vacía') }}</flux:heading>
            <flux:subheading class="mt-1 mb-6">{{ __('Explora nuestro catálogo de mochilas y encuentra la ideal para ti.') }}</flux:subheading>
            <flux:button variant="primary" :href="route('catalogo')" wire:navigate class="rounded-xl font-semibold">
                {{ __('Ir al catálogo') }}
            </flux:button>
        </div>
    @endif
</div>
