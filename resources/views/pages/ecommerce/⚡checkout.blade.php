<?php

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Variacion;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\Almacen;
use App\Models\TipoDocumento;
use App\Models\Sede;
use App\Models\Cupon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Checkout - Finalizar Compra'), Layout('layouts.publico')] class extends Component {
    // Delivery
    public string $tipoEntrega = 'recojo';   // 'recojo' or 'envio'
    public ?int $sedeRecojo = null;
    public string $direccionEnvio = '';

    // Payment
    public string $metodoPago = 'transferencia'; // 'transferencia', 'tarjeta', 'yape_plin'

    // Coupon
    public string $couponCode = '';
    public ?int $appliedCouponId = null;
    public float $couponDiscountAmount = 0.00;

    // Notes
    public string $comentarios = '';

    /**
     * Mount the component and initialize.
     */
    public function mount(): void
    {
        $cart = session()->get('public_cart', []);
        if (empty($cart)) {
            $this->redirect(route('carrito'), navigate: true);
            return;
        }

        // Auto-select first sede
        $firstSede = Sede::where('activo', true)->first();
        if ($firstSede) {
            $this->sedeRecojo = $firstSede->id;
        }

        if (auth()->check() && auth()->user()->cliente && auth()->user()->cliente->tipo_cliente === 'mayorista') {
            $this->metodoPago = 'coordinar';
        }
    }

    /**
     * Build enriched line items from session cart.
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
            $variacion = Variacion::with(['producto.marca', 'producto.descuentos', 'valores.atributo', 'precios.listaPrecio', 'inventarios'])
                ->find($item['variacion_id']);

            if (!$variacion) {
                continue;
            }

            $stock = (int) $variacion->inventarios->sum('stock_base');
            $basePrice = (float) ($variacion->precios->firstWhere('listaPrecio.nombre', $nombreListaPrecio)?->precio ?? 0.00);

            $activeDiscount = $variacion->producto->descuentos
                ->where('activo', true)
                ->filter(fn($d) => (!$d->fecha_inicio || $d->fecha_inicio <= now()) && (!$d->fecha_fin || $d->fecha_fin >= now()))
                ->first();

            $pct = $activeDiscount ? (int) $activeDiscount->porcentaje_descuento : 0;
            $finalPrice = $pct > 0 ? round($basePrice * (1 - $pct / 100), 2) : $basePrice;

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
                'subtotal' => round($finalPrice * min($item['cantidad'], $stock), 2),
            ];
        }

        return $items;
    }

    /**
     * Order totals.
     */
    #[Computed]
    public function resumen(): array
    {
        $items = $this->items;
        $totalItems = array_sum(array_column($items, 'cantidad'));
        $subtotalFinal = array_sum(array_column($items, 'subtotal'));
        $costoEnvio = ($this->tipoEntrega === 'envio') ? 9.90 : 0.00;
        $total = max(0, round($subtotalFinal - $this->couponDiscountAmount + $costoEnvio, 2));

        return [
            'total_items' => $totalItems,
            'subtotal' => round($subtotalFinal, 2),
            'costo_envio' => $costoEnvio,
            'descuento_cupon' => $this->couponDiscountAmount,
            'total' => $total,
        ];
    }

    /**
     * Available sedes for pickup.
     */
    #[Computed]
    public function sedes()
    {
        return Sede::where('activo', true)->get();
    }

    /**
     * Apply coupon code.
     */
    public function aplicarCupon(): void
    {
        if (empty(trim($this->couponCode))) {
            Flux::toast(variant: 'danger', text: __('Ingresa un código de cupón.'));
            return;
        }

        $cupon = Cupon::where('codigo', trim($this->couponCode))->where('activo', true)->first();

        if (!$cupon) {
            Flux::toast(variant: 'danger', text: __('El cupón ingresado no es válido o está inactivo.'));
            return;
        }

        $now = now();
        if (($cupon->fecha_inicio && $now->lt($cupon->fecha_inicio)) || ($cupon->fecha_expiracion && $now->gt($cupon->fecha_expiracion))) {
            Flux::toast(variant: 'danger', text: __('El cupón ha expirado o aún no está vigente.'));
            return;
        }

        if ($cupon->usos_restantes <= 0) {
            Flux::toast(variant: 'danger', text: __('El cupón ya ha alcanzado su límite de usos.'));
            return;
        }

        $subtotal = $this->resumen['subtotal'];
        if ($subtotal < $cupon->monto_minimo_compra) {
            Flux::toast(variant: 'danger', text: __('El monto mínimo de compra para este cupón es S/ ' . number_format($cupon->monto_minimo_compra, 2)));
            return;
        }

        $this->appliedCouponId = $cupon->id;
        if ($cupon->tipo_descuento === 'porcentaje') {
            $this->couponDiscountAmount = round($subtotal * ($cupon->valor_descuento / 100), 2);
        } else {
            $this->couponDiscountAmount = round(min($cupon->valor_descuento, $subtotal), 2);
        }

        unset($this->resumen);
        Flux::toast(variant: 'success', text: __('¡Cupón aplicado! Descuento: S/ ' . number_format($this->couponDiscountAmount, 2)));
    }

    /**
     * Remove applied coupon.
     */
    public function cancelarCupon(): void
    {
        $this->appliedCouponId = null;
        $this->couponCode = '';
        $this->couponDiscountAmount = 0.00;
        unset($this->resumen);
    }

    /**
     * Process the order and create the sale record.
     */
    public function confirmarCompra(): void
    {
        if (!Auth::check()) {
            Flux::toast(variant: 'danger', text: __('Debes iniciar sesión para completar la compra.'));
            $this->redirect(route('login'), navigate: true);
            return;
        }

        $items = $this->items;
        if (empty($items)) {
            Flux::toast(variant: 'danger', text: __('Tu carrito está vacío.'));
            return;
        }

        // Validate stock for all items
        foreach ($items as $item) {
            if ($item['cantidad'] > $item['stock'] || $item['stock'] <= 0) {
                Flux::toast(variant: 'danger', text: __('El producto "' . $item['nombre'] . '" no tiene stock suficiente.'));
                return;
            }
        }

        // Validate delivery
        if ($this->tipoEntrega === 'recojo' && !$this->sedeRecojo) {
            Flux::toast(variant: 'danger', text: __('Selecciona un punto de entrega.'));
            return;
        }

        if ($this->tipoEntrega === 'envio' && empty(trim($this->direccionEnvio))) {
            Flux::toast(variant: 'danger', text: __('Ingresa una dirección de envío.'));
            return;
        }

        DB::transaction(function () use ($items) {
            $tot = $this->resumen;

            // Get default document type (Boleta de Venta)
            $tipoDoc = TipoDocumento::where('codigo_sunat', '03')->first();

            // Get serie and correlativo
            $almacen = Almacen::where('activo', true)->first();
            $serieRecord = DB::table('series')
                ->where('tipo_documento_id', $tipoDoc->id)
                ->first();

            $serie = $serieRecord ? $serieRecord->serie : 'B001';
            $correlativo = $serieRecord ? $serieRecord->correlativo : 1;

            // Build delivery notes
            $notasEntrega = $this->tipoEntrega === 'recojo'
                ? 'Recojo en tienda: ' . Sede::find($this->sedeRecojo)?->nombre
                : 'Envío a: ' . $this->direccionEnvio;

            $comentarioFinal = trim($notasEntrega . ($this->comentarios ? ' | ' . $this->comentarios : ''));

            // Create sale header
            $venta = Venta::create([
                'user_id' => Auth::id(),
                'tipo_documento_id' => $tipoDoc->id,
                'serie' => $serie,
                'correlativo' => $correlativo,
                'subtotal' => $tot['subtotal'],
                'descuento' => $tot['descuento_cupon'],
                'costo_envio' => $tot['costo_envio'],
                'total' => $tot['total'],
                'estado_pago' => 'pendiente',
                'estado_despacho' => 'pendiente',
                'tipo_pago' => 'online',
                'metodo_pago' => $this->metodoPago,
                'cupon_id' => $this->appliedCouponId,
                'comentarios' => $comentarioFinal,
            ]);

            // Decrement coupon uses
            if ($this->appliedCouponId) {
                Cupon::find($this->appliedCouponId)?->decrement('usos_restantes');
            }

            // Create detail lines, decrement stock & register Kardex
            foreach ($items as $item) {
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'variacion_id' => $item['variacion_id'],
                    'unidad_medida_id' => 1,
                    'cantidad' => $item['cantidad'],
                    'factor_conversion' => 1,
                    'cantidad_base' => $item['cantidad'],
                    'precio_unitario' => $item['precio_final'],
                    'subtotal' => $item['subtotal'],
                ]);

                // Decrement stock from first warehouse with availability
                $inv = Inventario::where('variacion_id', $item['variacion_id'])
                    ->where('stock_base', '>', 0)
                    ->first();

                if ($inv) {
                    $stockAnterior = $inv->stock_base;
                    $inv->decrement('stock_base', $item['cantidad']);

                    Kardex::create([
                        'almacen_id' => $inv->almacen_id,
                        'variacion_id' => $item['variacion_id'],
                        'tipo_transaccion' => 'Salida',
                        'concepto' => 'Venta Web ' . $serie . '-' . str_pad($correlativo, 6, '0', STR_PAD_LEFT),
                        'cantidad' => $item['cantidad'],
                        'stock_anterior' => $stockAnterior,
                        'stock_posterior' => $stockAnterior - $item['cantidad'],
                        'costo_unitario' => $item['precio_final'],
                        'costo_total' => $item['subtotal'],
                        'valor_total_almacen' => ($stockAnterior - $item['cantidad']) * $item['precio_final'],
                        'origen_documento_id' => $venta->id,
                        'origen_documento_type' => Venta::class,
                        'creado_por_usuario_id' => Auth::id(),
                    ]);
                }
            }

            // Increment correlativo
            if ($serieRecord) {
                DB::table('series')
                    ->where('id', $serieRecord->id)
                    ->increment('correlativo');
            }

            // Clear session cart
            session()->forget('public_cart');
        });

        Flux::toast(variant: 'success', text: __('¡Compra realizada con éxito! Tu pedido ha sido registrado.'));
        $this->redirect(route('catalogo'), navigate: true);
    }
}; ?>

<div class="max-w-6xl mx-auto">
    <!-- Back to cart -->
    <div class="mb-6">
        <a href="{{ route('carrito') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-500 hover:text-black dark:hover:text-white transition-colors">
            <flux:icon name="arrow-left" class="size-4" />
            {{ __('Volver a la bolsa') }}
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Columna Izquierda: Formulario de Checkout -->
        <div class="lg:col-span-2 space-y-6">
            <flux:heading size="xl" class="font-extrabold tracking-tight text-zinc-900 dark:text-white">
                {{ __('Revisa y paga tu compra') }}
            </flux:heading>

            <!-- Sección 1: Punto de Entrega -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 space-y-5">
                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">{{ __('Punto de entrega') }}</flux:heading>

                <div class="flex gap-4">
                    <button wire:click.prevent="$set('tipoEntrega', 'recojo')" class="flex-1 border rounded-xl p-4 text-left transition-all {{ $tipoEntrega === 'recojo' ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                        <div class="flex items-center gap-3">
                            <flux:icon name="map-pin" class="size-5 {{ $tipoEntrega === 'recojo' ? 'text-black dark:text-white' : 'text-zinc-400' }}" />
                            <div>
                                <div class="text-sm font-bold {{ $tipoEntrega === 'recojo' ? 'text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400' }}">{{ __('Retiro en Tienda') }}</div>
                                <div class="text-xxs text-emerald-600 font-semibold">{{ __('Gratis') }}</div>
                            </div>
                        </div>
                    </button>

                    <button wire:click.prevent="$set('tipoEntrega', 'envio')" class="flex-1 border rounded-xl p-4 text-left transition-all {{ $tipoEntrega === 'envio' ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                        <div class="flex items-center gap-3">
                            <flux:icon name="truck" class="size-5 {{ $tipoEntrega === 'envio' ? 'text-black dark:text-white' : 'text-zinc-400' }}" />
                            <div>
                                <div class="text-sm font-bold {{ $tipoEntrega === 'envio' ? 'text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400' }}">{{ __('Envío a Domicilio') }}</div>
                                <div class="text-xxs text-zinc-500 font-semibold">S/ 9.90</div>
                            </div>
                        </div>
                    </button>
                </div>

                @if($tipoEntrega === 'recojo')
                    <div class="space-y-3 pt-2">
                        <flux:text size="sm" class="font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">{{ __('Selecciona tu punto de recojo') }}</flux:text>
                        @foreach($this->sedes as $sede)
                            <label class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition-all {{ $sedeRecojo === $sede->id ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                                <input type="radio" wire:model.live="sedeRecojo" value="{{ $sede->id }}" class="mt-0.5 accent-black dark:accent-white" />
                                <div>
                                    <div class="text-sm font-bold text-zinc-900 dark:text-white">{{ $sede->nombre }}</div>
                                    <div class="text-xxs text-zinc-500">{{ $sede->direccion }}</div>
                                    <div class="text-xxs text-emerald-600 font-semibold mt-1">{{ __('Gratis') }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @else
                    <div class="pt-2">
                        <flux:textarea wire:model.defer="direccionEnvio" :label="__('Dirección de envío completa')" placeholder="Av. Ejemplo 123, Distrito, Ciudad" rows="2" />
                    </div>
                @endif
            </div>

            <!-- Sección 2: Medio de Pago -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 space-y-5">
                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">{{ __('Medio de pago') }}</flux:heading>

                @if(auth()->check() && auth()->user()->cliente && auth()->user()->cliente->tipo_cliente === 'mayorista')
                    <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 rounded-xl flex items-start gap-3">
                        <flux:icon name="exclamation-circle" class="size-5 text-amber-600 mt-0.5" />
                        <div>
                            <div class="text-sm font-bold text-amber-800 dark:text-amber-500">{{ __('Pedido B2B Mayorista') }}</div>
                            <div class="text-xs text-amber-700 dark:text-amber-400 mt-1">{{ __('Tu pedido se registrará como "Pendiente de Pago". Nos comunicaremos contigo para coordinar el abono o transferencia por el total con descuento mayorista.') }}</div>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <button wire:click.prevent="$set('metodoPago', 'transferencia')" class="border rounded-xl p-4 text-center transition-all {{ $metodoPago === 'transferencia' ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                            <flux:icon name="building-library" class="size-6 mx-auto mb-2 {{ $metodoPago === 'transferencia' ? 'text-black dark:text-white' : 'text-zinc-400' }}" />
                            <div class="text-xs font-bold {{ $metodoPago === 'transferencia' ? 'text-zinc-900 dark:text-white' : 'text-zinc-500' }}">{{ __('Transferencia') }}</div>
                        </button>

                        <button wire:click.prevent="$set('metodoPago', 'tarjeta')" class="border rounded-xl p-4 text-center transition-all {{ $metodoPago === 'tarjeta' ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                            <flux:icon name="credit-card" class="size-6 mx-auto mb-2 {{ $metodoPago === 'tarjeta' ? 'text-black dark:text-white' : 'text-zinc-400' }}" />
                            <div class="text-xs font-bold {{ $metodoPago === 'tarjeta' ? 'text-zinc-900 dark:text-white' : 'text-zinc-500' }}">{{ __('Tarjeta') }}</div>
                        </button>

                        <button wire:click.prevent="$set('metodoPago', 'yape_plin')" class="border rounded-xl p-4 text-center transition-all {{ $metodoPago === 'yape_plin' ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                            <flux:icon name="device-phone-mobile" class="size-6 mx-auto mb-2 {{ $metodoPago === 'yape_plin' ? 'text-black dark:text-white' : 'text-zinc-400' }}" />
                            <div class="text-xs font-bold {{ $metodoPago === 'yape_plin' ? 'text-zinc-900 dark:text-white' : 'text-zinc-500' }}">{{ __('Yape / Plin') }}</div>
                        </button>
                    </div>
                @endif
            </div>

            <!-- Sección 3: Productos en tu pedido (resumen compacto) -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 space-y-4">
                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">{{ __('Productos en tu pedido') }}</flux:heading>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($this->items as $item)
                        <div class="py-3 flex items-center gap-4 first:pt-0 last:pb-0">
                            <div class="w-14 h-14 flex-shrink-0 bg-zinc-100 dark:bg-zinc-950 rounded-lg flex items-center justify-center border border-zinc-200/50 dark:border-zinc-800">
                                <flux:icon name="archive-box" class="size-6 text-zinc-300 dark:text-zinc-700" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold text-zinc-900 dark:text-white truncate">{{ $item['nombre'] }}</div>
                                <div class="text-xxs text-zinc-500">{{ $item['detalles'] }}</div>
                                <div class="text-xxs text-zinc-400 mt-0.5">{{ __('Cant:') }} {{ $item['cantidad'] }}</div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-sm font-extrabold text-zinc-900 dark:text-white">S/ {{ number_format($item['subtotal'], 2) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Notas / Comentarios -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6">
                <flux:textarea wire:model.defer="comentarios" :label="__('¿Algún comentario adicional? (opcional)')" placeholder="Ej: Entregar en portería, llamar antes, etc." rows="2" />
            </div>
        </div>

        <!-- Columna Derecha: Resumen de la Compra (Sticky) -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 space-y-5 shadow-sm sticky top-24">
                <flux:heading size="lg" class="font-extrabold text-zinc-900 dark:text-white">{{ __('Resumen de la compra') }}</flux:heading>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Productos') }} ({{ $this->resumen['total_items'] }})</span>
                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">S/ {{ number_format($this->resumen['subtotal'], 2) }}</span>
                    </div>

                    @if($this->resumen['descuento_cupon'] > 0)
                        <div class="flex justify-between">
                            <span class="text-zinc-500">{{ __('Cupón') }}</span>
                            <span class="font-semibold text-emerald-600">- S/ {{ number_format($this->resumen['descuento_cupon'], 2) }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Entregas') }}</span>
                        <span class="font-semibold {{ $this->resumen['costo_envio'] > 0 ? 'text-zinc-800 dark:text-zinc-200' : 'text-emerald-600' }}">
                            {{ $this->resumen['costo_envio'] > 0 ? 'S/ ' . number_format($this->resumen['costo_envio'], 2) : __('Gratis') }}
                        </span>
                    </div>
                </div>

                <hr class="border-zinc-200 dark:border-zinc-800" />

                <div class="flex justify-between items-baseline">
                    <span class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Total') }}</span>
                    <span class="text-2xl font-extrabold text-zinc-900 dark:text-white">S/ {{ number_format($this->resumen['total'], 2) }}</span>
                </div>

                <!-- Coupon -->
                <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ __('¿Tienes un cupón?') }}</span>
                        @if($appliedCouponId)
                            <flux:button variant="ghost" size="sm" wire:click.prevent="cancelarCupon" class="text-xxs text-rose-600 hover:text-rose-700">{{ __('Quitar') }}</flux:button>
                        @endif
                    </div>
                    @if(!$appliedCouponId)
                        <div class="flex gap-2">
                            <flux:input wire:model.defer="couponCode" placeholder="Código" size="sm" class="flex-1" />
                            <flux:button variant="outline" size="sm" wire:click.prevent="aplicarCupon" class="font-semibold text-xs">{{ __('Aplicar') }}</flux:button>
                        </div>
                    @else
                        <div class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 rounded-lg px-3 py-2">
                            <flux:icon name="check-circle" class="size-4 text-emerald-600" />
                            <span class="text-xxs font-bold text-emerald-700 dark:text-emerald-400">{{ $couponCode }} {{ __('aplicado') }} (- S/ {{ number_format($couponDiscountAmount, 2) }})</span>
                        </div>
                    @endif
                </div>

                <!-- Pay Button -->
                <flux:button variant="primary" wire:click.prevent="confirmarCompra" class="w-full h-12 rounded-xl font-bold text-base" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="confirmarCompra">
                        {{ (auth()->check() && auth()->user()->cliente && auth()->user()->cliente->tipo_cliente === 'mayorista') ? __('Enviar Orden de Compra') : __('Pagar ahora') }}
                    </span>
                    <span wire:loading wire:target="confirmarCompra">{{ __('Procesando...') }}</span>
                </flux:button>

                <!-- Terms -->
                <p class="text-xxs text-zinc-400 text-center leading-relaxed">
                    {{ __('Al confirmar tu compra, aceptas los Términos y Condiciones de Venta y la Política de Privacidad de Mochilas Logan.') }}
                </p>
            </div>
        </div>
    </div>
</div>
