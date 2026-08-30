<?php

use App\Models\Cupon;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\Sede;
use App\Models\Serie;
use App\Models\TipoDocumento;
use App\Models\Variacion;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Checkout - Finalizar Compra'), Layout('layouts.publico')]
    class extends Component {
    // Delivery
    public string $tipoEntrega = 'recojo';   // 'recojo' or 'envio'
    public ?int $sedeRecojo = null;
    public string $direccionEnvio = '';

    // Payment
    public string $metodoPago = 'transferencia'; // 'transferencia', 'tarjeta', 'yape_plin', 'whatsapp'

    // Coupon
    public string $couponCode = '';
    public ?int $appliedCouponId = null;
    public float $couponDiscountAmount = 0.00;

    // Notes
    public string $comentarios = '';

    // Número de WhatsApp oficial de atención
    public string $telefonoWhatsappTienda = '51916647675';

    /**
     * Mount the component and initialize.
     */
    public function mount(): void
    {
        $empresa = \App\Models\EmpresaConfiguracion::getEmpresa();
        $this->telefonoWhatsappTienda = $empresa->whatsapp_pedidos ?? '51916647675';

        $items = \App\Services\CartService::getValidatedItems();
        if (empty($items)) {
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
     * Generate structured WhatsApp order URL.
     */
    #[Computed]
    public function urlPedidoWhatsapp(): string
    {
        $user = Auth::user();
        $cliente = $user?->cliente;
        $items = $this->items;
        $tot = $this->resumen;
        $empresa = \App\Models\EmpresaConfiguracion::getEmpresa();
        $whatsappTienda = preg_replace('/[^0-9]/', '', $empresa->whatsapp_pedidos ?? '51916647675');

        $msg = "🎒 *¡HOLA " . strtoupper($empresa->nombre_comercial ?? 'MOCHILAS LOGAN') . "! Deseo realizar un nuevo pedido:*\n\n";

        if ($user) {
            $msg .= "👤 *Cliente:* " . $user->name . "\n";
            if ($user->email)
                $msg .= "📧 *Email:* " . $user->email . "\n";
            if ($cliente?->telefono)
                $msg .= "📱 *Teléfono:* " . $cliente->telefono . "\n";
            if ($cliente?->dni)
                $msg .= "🪪 *DNI:* " . $cliente->dni . "\n";
            if ($cliente?->ruc)
                $msg .= "🏢 *RUC:* " . $cliente->ruc . " - " . $cliente->razon_social . "\n";
        }

        $msg .= "\n📦 *PRODUCTOS DEL PEDIDO:*\n";
        $i = 1;
        foreach ($items as $item) {
            $msg .= $i . ". *" . $item['nombre'] . "*\n";
            $msg .= "   • " . $item['detalles'] . " | Cant: " . $item['cantidad'] . "\n";
            $msg .= "   • Subtotal: S/ " . number_format($item['subtotal'], 2) . "\n";
            $i++;
        }

        $msg .= "\n🚚 *MODALIDAD DE ENTREGA:* ";
        if ($this->tipoEntrega === 'recojo') {
            $sede = Sede::find($this->sedeRecojo);
            $msg .= "Retiro en tienda (" . ($sede?->nombre ?? 'Sede Principal') . " - " . ($sede?->direccion ?? '') . ")\n";
        } else {
            $msg .= "Envío a Domicilio (+S/ " . number_format($tot['costo_envio'], 2) . ")\n";
            $msg .= "📍 *Dirección:* " . ($this->direccionEnvio ?: 'Por coordinar') . "\n";
        }

        $msg .= "💳 *MEDIO DE PAGO:* ";
        $mediosMap = [
            'transferencia' => 'Transferencia Bancaria',
            'tarjeta' => 'Tarjeta Débito/Crédito',
            'yape_plin' => 'Yape / Plin',
            'whatsapp' => 'Coordinar Pago por WhatsApp',
            'coordinar' => 'Mayorista (Abono por coordinar)',
        ];
        $msg .= ($mediosMap[$this->metodoPago] ?? ucfirst($this->metodoPago)) . "\n";

        if ($this->appliedCouponId && $this->couponDiscountAmount > 0) {
            $msg .= "🏷️ *Cupón aplicado:* -" . number_format($this->couponDiscountAmount, 2) . "\n";
        }

        if (!empty($this->comentarios)) {
            $msg .= "📝 *Notas:* " . $this->comentarios . "\n";
        }

        $msg .= "\n💰 *TOTAL A PAGAR:* S/ " . number_format($tot['total'], 2) . "\n";
        $msg .= "\n_Quedo a la espera de la confirmación de mi pedido. ¡Gracias!_";

        return 'https://api.whatsapp.com/send?phone=' . $whatsappTienda . '&text=' . urlencode($msg);
    }

    /**
     * Build enriched line items from CartService.
     */
    #[Computed]
    public function items(): array
    {
        return \App\Services\CartService::getValidatedItems();
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
        
        $empresa = \App\Models\EmpresaConfiguracion::getEmpresa();
        $costoEnvioConfig = (float) ($empresa->costo_envio_fijo ?? 9.90);
        $costoEnvio = ($this->tipoEntrega === 'envio') ? $costoEnvioConfig : 0.00;
        
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
            $empresa = \App\Models\EmpresaConfiguracion::getEmpresa();

            // Get default document type (Boleta de Venta o Configuración Web)
            $tipoDoc = $empresa->tipoDocumentoWeb 
                ?? TipoDocumento::where('codigo_sunat', '03')->first() 
                ?? TipoDocumento::firstOrCreate(
                    ['codigo_sunat' => '03'],
                    ['nombre' => 'Boleta de Venta', 'descripcion' => 'Boleta de Venta Electrónica', 'activo' => true]
                );

            // Get or create Serie for Web
            $defaultSedeId = $empresa->almacenWeb?->sede_id ?? Sede::first()?->id ?? 1;
            
            $serieModel = Serie::where('tipo_documento_id', $tipoDoc->id)
                ->whereIn('tipo_origen', ['web', 'todos'])
                ->lockForUpdate()
                ->first();

            if (! $serieModel) {
                $serieModel = Serie::where('tipo_documento_id', $tipoDoc->id)
                    ->lockForUpdate()
                    ->first();
            }

            if (! $serieModel) {
                $serieModel = Serie::create([
                    'sede_id' => $defaultSedeId,
                    'tipo_documento_id' => $tipoDoc->id,
                    'serie' => 'EB01',
                    'correlativo' => 1,
                    'tipo_origen' => 'web',
                    'activo' => true,
                ]);
            }

            // Ensure correlativo is higher than any existing sale with this tipo_documento and serie
            $maxCorrelativoUsado = (int) Venta::where('tipo_documento_id', $tipoDoc->id)
                ->where('serie', $serieModel->serie)
                ->max('correlativo');

            $correlativoActual = max((int) $serieModel->correlativo, $maxCorrelativoUsado + 1);

            $serie = $serieModel->serie;
            $correlativo = $correlativoActual;

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

            // Increment Serie Model correlativo
            $serieModel->update([
                'correlativo' => $correlativo + 1,
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

            // Clear cart
            \App\Services\CartService::clear();
            session()->forget('public_cart');
        });

        Flux::toast(variant: 'success', text: __('¡Compra realizada con éxito! Tu pedido ha sido registrado.'));
        $this->redirect(route('catalogo'), navigate: true);
    }
}; ?>

<div class="max-w-6xl mx-auto">
    <!-- Back to cart -->
    <div class="mb-6">
        <a href="{{ route('carrito') }}" wire:navigate
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-zinc-500 hover:text-black dark:hover:text-white transition-colors">
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
            <div
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 space-y-5">
                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">{{ __('Punto de entrega') }}
                </flux:heading>

                <div class="flex gap-4">
                    <button wire:click.prevent="$set('tipoEntrega', 'recojo')"
                        class="flex-1 border rounded-xl p-4 text-left transition-all {{ $tipoEntrega === 'recojo' ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                        <div class="flex items-center gap-3">
                            <flux:icon name="map-pin"
                                class="size-5 {{ $tipoEntrega === 'recojo' ? 'text-black dark:text-white' : 'text-zinc-400' }}" />
                            <div>
                                <div
                                    class="text-sm font-bold {{ $tipoEntrega === 'recojo' ? 'text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400' }}">
                                    {{ __('Retiro en Tienda') }}</div>
                                <div class="text-xxs text-emerald-600 font-semibold">{{ __('Gratis') }}</div>
                            </div>
                        </div>
                    </button>

                    <button wire:click.prevent="$set('tipoEntrega', 'envio')"
                        class="flex-1 border rounded-xl p-4 text-left transition-all {{ $tipoEntrega === 'envio' ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                        <div class="flex items-center gap-3">
                            <flux:icon name="truck"
                                class="size-5 {{ $tipoEntrega === 'envio' ? 'text-black dark:text-white' : 'text-zinc-400' }}" />
                            <div>
                                <div
                                    class="text-sm font-bold {{ $tipoEntrega === 'envio' ? 'text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-400' }}">
                                    {{ __('Envío a Domicilio') }}</div>
                                <div class="text-xxs text-zinc-500 font-semibold">S/ {{ number_format(\App\Models\EmpresaConfiguracion::getEmpresa()->costo_envio_fijo ?? 9.90, 2) }}</div>
                            </div>
                        </div>
                    </button>
                </div>

                @if($tipoEntrega === 'recojo')
                    <div class="space-y-3 pt-2">
                        <flux:text size="sm" class="font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">
                            {{ __('Selecciona tu punto de recojo') }}</flux:text>
                        @foreach($this->sedes as $sede)
                            <label
                                class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition-all {{ $sedeRecojo === $sede->id ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                                <input type="radio" wire:model.live="sedeRecojo" value="{{ $sede->id }}"
                                    class="mt-0.5 accent-black dark:accent-white" />
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
                        <flux:textarea wire:model.defer="direccionEnvio" :label="__('Dirección de envío completa')"
                            placeholder="Av. Ejemplo 123, Distrito, Ciudad" rows="2" />
                    </div>
                @endif
            </div>

            <!-- Sección 2: Medio de Pago -->
            <div
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 space-y-5">
                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">{{ __('Medio de pago') }}
                </flux:heading>

                @if(auth()->check() && auth()->user()->cliente && auth()->user()->cliente->tipo_cliente === 'mayorista')
                    <div
                        class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 rounded-xl flex items-start gap-3">
                        <flux:icon name="exclamation-circle" class="size-5 text-amber-600 mt-0.5" />
                        <div>
                            <div class="text-sm font-bold text-amber-800 dark:text-amber-500">
                                {{ __('Pedido B2B Mayorista') }}</div>
                            <div class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                                {{ __('Tu pedido se registrará como "Pendiente de Pago". Nos comunicaremos contigo para coordinar el abono o transferencia por el total con descuento mayorista.') }}
                            </div>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <button wire:click.prevent="$set('metodoPago', 'transferencia')"
                            class="border rounded-xl p-4 text-center transition-all {{ $metodoPago === 'transferencia' ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                            <flux:icon name="building-library"
                                class="size-6 mx-auto mb-2 {{ $metodoPago === 'transferencia' ? 'text-black dark:text-white' : 'text-zinc-400' }}" />
                            <div
                                class="text-xs font-bold {{ $metodoPago === 'transferencia' ? 'text-zinc-900 dark:text-white' : 'text-zinc-500' }}">
                                {{ __('Transferencia') }}</div>
                        </button>

                        <button wire:click.prevent="$set('metodoPago', 'tarjeta')"
                            class="border rounded-xl p-4 text-center transition-all {{ $metodoPago === 'tarjeta' ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                            <flux:icon name="credit-card"
                                class="size-6 mx-auto mb-2 {{ $metodoPago === 'tarjeta' ? 'text-black dark:text-white' : 'text-zinc-400' }}" />
                            <div
                                class="text-xs font-bold {{ $metodoPago === 'tarjeta' ? 'text-zinc-900 dark:text-white' : 'text-zinc-500' }}">
                                {{ __('Tarjeta') }}</div>
                        </button>

                        <button wire:click.prevent="$set('metodoPago', 'yape_plin')"
                            class="border rounded-xl p-4 text-center transition-all {{ $metodoPago === 'yape_plin' ? 'border-black dark:border-white bg-zinc-50 dark:bg-zinc-950 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                            <flux:icon name="device-phone-mobile"
                                class="size-6 mx-auto mb-2 {{ $metodoPago === 'yape_plin' ? 'text-black dark:text-white' : 'text-zinc-400' }}" />
                            <div
                                class="text-xs font-bold {{ $metodoPago === 'yape_plin' ? 'text-zinc-900 dark:text-white' : 'text-zinc-500' }}">
                                {{ __('Yape / Plin') }}</div>
                        </button>

                        <button wire:click.prevent="$set('metodoPago', 'whatsapp')"
                            class="border rounded-xl p-4 text-center transition-all {{ $metodoPago === 'whatsapp' ? 'border-emerald-600 bg-emerald-50 dark:bg-emerald-950/20 shadow-sm' : 'border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-950' }}">
                            <flux:icon name="chat-bubble-left-right"
                                class="size-6 mx-auto mb-2 {{ $metodoPago === 'whatsapp' ? 'text-emerald-600' : 'text-zinc-400' }}" />
                            <div
                                class="text-xs font-bold {{ $metodoPago === 'whatsapp' ? 'text-emerald-700 dark:text-emerald-400' : 'text-zinc-500' }}">
                                {{ __('WhatsApp') }}</div>
                        </button>
                    </div>
                @endif
            </div>

            <!-- Sección 3: Productos en tu pedido (resumen compacto) -->
            <div
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 space-y-4">
                <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">
                    {{ __('Productos en tu pedido') }}</flux:heading>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($this->items as $item)
                        <div class="py-3 flex items-center gap-4 first:pt-0 last:pb-0">
                            <div
                                class="w-14 h-14 flex-shrink-0 bg-zinc-100 dark:bg-zinc-950 rounded-lg flex items-center justify-center border border-zinc-200/50 dark:border-zinc-800 overflow-hidden">
                                @if(!empty($item['imagen_url']))
                                    <img src="{{ $item['imagen_url'] }}" alt="{{ $item['nombre'] }}"
                                        class="w-full h-full object-cover" />
                                @else
                                    <flux:icon name="archive-box" class="size-6 text-zinc-300 dark:text-zinc-700" />
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold text-zinc-900 dark:text-white truncate">{{ $item['nombre'] }}
                                </div>
                                <div class="text-xxs text-zinc-500">{{ $item['detalles'] }}</div>
                                <div class="text-xxs text-zinc-400 mt-0.5">{{ __('Cant:') }} {{ $item['cantidad'] }}</div>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-sm font-extrabold text-zinc-900 dark:text-white">S/
                                    {{ number_format($item['subtotal'], 2) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Notas / Comentarios -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6">
                <flux:textarea wire:model.defer="comentarios" :label="__('¿Algún comentario adicional? (opcional)')"
                    placeholder="Ej: Entregar en portería, llamar antes, etc." rows="2" />
            </div>
        </div>

        <!-- Columna Derecha: Resumen de la Compra (Sticky) -->
        <div class="lg:col-span-1">
            <div
                class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 space-y-5 shadow-sm sticky top-24">
                <flux:heading size="lg" class="font-extrabold text-zinc-900 dark:text-white">
                    {{ __('Resumen de la compra') }}</flux:heading>

                @guest
                    <!-- Banner de Iniciar Sesión para Checkout -->
                    <div
                        class="p-3.5 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-xl flex items-start gap-3">
                        <flux:icon name="user-circle" class="size-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                        <div class="text-xs text-blue-900 dark:text-blue-200">
                            <p class="font-bold">{{ __('¿Ya tienes cuenta?') }}</p>
                            <p class="mt-0.5 text-blue-700 dark:text-blue-300">
                                {{ __('Inicia sesión para vincular tu pedido y acumular historial.') }}</p>
                            <div class="flex items-center gap-2 mt-2">
                                <a href="{{ route('login') }}" wire:navigate
                                    class="font-bold underline text-blue-700 dark:text-blue-300 hover:text-blue-900">{{ __('Iniciar Sesión') }}</a>
                                <span>•</span>
                                <a href="{{ route('register') }}" wire:navigate
                                    class="font-bold underline text-blue-700 dark:text-blue-300 hover:text-blue-900">{{ __('Crear Cuenta') }}</a>
                            </div>
                        </div>
                    </div>
                @endguest

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Productos') }} ({{ $this->resumen['total_items'] }})</span>
                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">S/
                            {{ number_format($this->resumen['subtotal'], 2) }}</span>
                    </div>

                    @if($this->resumen['descuento_cupon'] > 0)
                        <div class="flex justify-between">
                            <span class="text-zinc-500">{{ __('Cupón') }}</span>
                            <span class="font-semibold text-emerald-600">- S/
                                {{ number_format($this->resumen['descuento_cupon'], 2) }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Entregas') }}</span>
                        <span
                            class="font-semibold {{ $this->resumen['costo_envio'] > 0 ? 'text-zinc-800 dark:text-zinc-200' : 'text-emerald-600' }}">
                            {{ $this->resumen['costo_envio'] > 0 ? 'S/ ' . number_format($this->resumen['costo_envio'], 2) : __('Gratis') }}
                        </span>
                    </div>
                </div>

                <hr class="border-zinc-200 dark:border-zinc-800" />

                <div class="flex justify-between items-baseline">
                    <span class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Total') }}</span>
                    <span class="text-2xl font-extrabold text-zinc-900 dark:text-white">S/
                        {{ number_format($this->resumen['total'], 2) }}</span>
                </div>

                <!-- Coupon -->
                <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ __('¿Tienes un cupón?') }}</span>
                        @if($appliedCouponId)
                            <flux:button variant="ghost" size="sm" wire:click.prevent="cancelarCupon"
                                class="text-xxs text-rose-600 hover:text-rose-700">{{ __('Quitar') }}</flux:button>
                        @endif
                    </div>
                    @if(!$appliedCouponId)
                        <div class="flex gap-2">
                            <flux:input wire:model.defer="couponCode" placeholder="Código" size="sm" class="flex-1" />
                            <flux:button variant="outline" size="sm" wire:click.prevent="aplicarCupon"
                                class="font-semibold text-xs">{{ __('Aplicar') }}</flux:button>
                        </div>
                    @else
                        <div
                            class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 rounded-lg px-3 py-2">
                            <flux:icon name="check-circle" class="size-4 text-emerald-600" />
                            <span class="text-xxs font-bold text-emerald-700 dark:text-emerald-400">{{ $couponCode }}
                                {{ __('aplicado') }} (- S/ {{ number_format($couponDiscountAmount, 2) }})</span>
                        </div>
                    @endif
                </div>

                <!-- Action Buttons: WhatsApp or Direct Payment -->
                <div class="space-y-2.5">
                    @auth
                        <flux:button variant="primary" wire:click.prevent="confirmarCompra"
                            class="w-full h-12 rounded-xl font-bold text-base" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="confirmarCompra">
                                {{ (auth()->check() && auth()->user()->cliente && auth()->user()->cliente->tipo_cliente === 'mayorista') ? __('Enviar Orden de Compra') : __('Pagar ahora') }}
                            </span>
                            <span wire:loading wire:target="confirmarCompra">{{ __('Procesando...') }}</span>
                        </flux:button>
                    @else
                        <a href="{{ route('login') }}" wire:navigate
                            class="w-full h-12 rounded-xl font-bold text-base bg-zinc-900 dark:bg-white text-white dark:text-black flex items-center justify-center hover:opacity-90 transition-opacity">
                            {{ __('Iniciar Sesión para Pagar') }}
                        </a>
                    @endauth

                    <!-- Botón Pedir por WhatsApp -->
                    <a href="{{ $this->urlPedidoWhatsapp }}" target="_blank" rel="noopener noreferrer"
                        class="w-full h-11 rounded-xl font-bold text-sm bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center gap-2 transition-colors shadow-sm">
                        <flux:icon name="chat-bubble-left-right" class="size-5" />
                        {{ __('Pedir por WhatsApp') }}
                    </a>
                </div>

                <!-- Terms -->
                <p class="text-xxs text-zinc-400 text-center leading-relaxed">
                    {{ __('Al confirmar tu compra, aceptas los Términos y Condiciones de Venta y la Política de Privacidad de Mochilas Logan.') }}
                </p>
            </div>
        </div>
    </div>
</div>