<?php

use App\Models\Variacion;
use App\Models\EmpresaConfiguracion;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Mi Bolsa de Compras'), Layout('layouts.publico')] class extends Component {

    /**
     * Get validated cart items in real time.
     */
    #[Computed]
    public function items(): array
    {
        return \App\Services\CartService::getValidatedItems();
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
    public function updateQuantity(int $variacionId, int $delta): void
    {
        $items = $this->items;
        $key = 'var_' . $variacionId;

        if (!isset($items[$key])) {
            return;
        }

        $currentQty = $items[$key]['cantidad'];
        $newQty = $currentQty + $delta;

        \App\Services\CartService::updateQuantity($variacionId, $newQty);
        unset($this->items, $this->resumen);
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(int $variacionId): void
    {
        \App\Services\CartService::remove($variacionId);
        unset($this->items, $this->resumen);

        Flux::toast(variant: 'success', text: __('Producto eliminado de la bolsa.'));
    }

    /**
     * Clear all items.
     */
    public function clearCart(): void
    {
        \App\Services\CartService::clear();
        unset($this->items, $this->resumen);

        Flux::toast(variant: 'success', text: __('Se ha vaciado tu bolsa de compras.'));
    }

    /**
     * URL y mensaje preformateado para pedir por WhatsApp.
     */
    #[Computed]
    public function whatsappUrl(): ?string
    {
        $empresa = EmpresaConfiguracion::getEmpresa();
        $numero = preg_replace('/[^0-9]/', '', $empresa->whatsapp_pedidos ?? '');

        if (!$numero || count($this->items) === 0) {
            return null;
        }

        $lineas = [];
        $lineas[] = '¡Hola! Deseo realizar el siguiente pedido desde la tienda web:';
        $lineas[] = '';
        $lineas[] = '📦 *PRODUCTOS:*';

        $num = 1;
        foreach ($this->items as $item) {
            $nombre = $item['nombre'];
            $detalles = !empty($item['detalles']) ? " ({$item['detalles']})" : '';
            $sku = $item['sku'];
            $cantidad = $item['cantidad'];
            $precio = number_format($item['precio_final'], 2);
            $subtotal = number_format($item['subtotal'], 2);

            $lineas[] = "{$num}. *{$nombre}*{$detalles}";
            $lineas[] = "   • SKU: {$sku}";
            $lineas[] = "   • Cantidad: {$cantidad} x S/ {$precio} = S/ {$subtotal}";
            $num++;
        }

        $lineas[] = '';
        $lineas[] = "🛒 Total de productos: {$this->resumen['total_items']} uds.";
        if ($this->resumen['descuento_total'] > 0) {
            $lineas[] = "🏷️ Descuento total: -S/ " . number_format($this->resumen['descuento_total'], 2);
        }
        $lineas[] = "💵 *TOTAL A PAGAR: S/ " . number_format($this->resumen['total'], 2) . "*";
        $lineas[] = '';
        $lineas[] = 'Por favor, indíquenme los métodos de pago (Yape, Plin o Transferencia) y datos para coordinar el envío. ¡Muchas gracias!';

        $texto = implode("\n", $lineas);

        return "https://wa.me/{$numero}?text=" . rawurlencode($texto);
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
                        <!-- Imagen del producto -->
                        <div class="w-24 h-24 flex-shrink-0 bg-zinc-100 dark:bg-zinc-950 rounded-xl flex items-center justify-center border border-zinc-200/50 dark:border-zinc-800 overflow-hidden">
                            <a href="{{ route('producto.detalle', ['producto' => $item['producto_id'], 'slug' => $item['slug']]) }}" wire:navigate class="w-full h-full flex items-center justify-center">
                                @if(!empty($item['imagen_url']))
                                    <img src="{{ $item['imagen_url'] }}" alt="{{ $item['nombre'] }}" class="w-full h-full object-cover" />
                                @else
                                    <flux:icon name="archive-box" class="size-10 text-zinc-300 dark:text-zinc-700" />
                                @endif
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
                                        <button wire:click.prevent="updateQuantity({{ $item['variacion_id'] }}, -1)" class="px-2.5 text-zinc-500 hover:text-black dark:hover:text-white font-bold text-sm h-full transition-colors {{ $item['cantidad'] <= 1 ? 'opacity-40 cursor-not-allowed' : '' }}">−</button>
                                        <span class="w-8 text-center font-bold text-sm text-zinc-900 dark:text-white">{{ $item['cantidad'] }}</span>
                                        <button wire:click.prevent="updateQuantity({{ $item['variacion_id'] }}, 1)" class="px-2.5 text-zinc-500 hover:text-black dark:hover:text-white font-bold text-sm h-full transition-colors {{ $item['cantidad'] >= $item['stock'] ? 'opacity-40 cursor-not-allowed' : '' }}">+</button>
                                    </div>
                                    <span class="text-xxs text-zinc-400">{{ __('Máx') }} {{ $item['stock'] }} {{ __('uds.') }}</span>
                                </div>

                                <!-- Subtotal y eliminar -->
                                <div class="flex items-center gap-4">
                                    <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200">S/ {{ number_format($item['subtotal'], 2) }}</span>
                                    <flux:button variant="ghost" size="sm" icon="x-mark" wire:click.prevent="removeItem({{ $item['variacion_id'] }})" class="text-zinc-400 hover:text-rose-600" title="{{ __('Eliminar') }}" />
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <!-- Vaciar carrito -->
                <div class="flex justify-end pt-2">
                    <flux:button variant="ghost" size="sm" wire:click.prevent="clearCart" class="text-xs text-zinc-400 hover:text-rose-600">
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

                    <div class="space-y-3">
                        <flux:button variant="primary" :href="route('checkout')" wire:navigate class="w-full h-12 rounded-xl font-bold text-base">
                            {{ __('Continuar compra') }}
                        </flux:button>

                        @if($this->whatsappUrl)
                            <a href="{{ $this->whatsappUrl }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="w-full h-12 rounded-xl font-bold text-sm sm:text-base flex items-center justify-center gap-2.5 transition-all bg-[#25D366] hover:bg-[#20ba59] text-white shadow-sm hover:shadow active:scale-[0.99]">
                                <svg class="size-5 fill-current shrink-0" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                <span>{{ __('Pedir por WhatsApp') }}</span>
                            </a>
                        @endif
                    </div>

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
