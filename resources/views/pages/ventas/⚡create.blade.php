<?php

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Almacen;
use App\Models\User;
use App\Models\TipoDocumento;
use App\Models\Variacion;
use App\Models\ProductoEmpaque;
use App\Models\Inventario;
use App\Models\Kardex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Registrar Venta')] class extends Component {
    public ?int $almacen_id = null;
    public ?int $cliente_id = null;
    public ?int $tipo_documento_id = null;
    public string $serie = '';
    public int $correlativo = 1;
    public string $fecha_venta = '';
    public string $tipo_pago = 'online';
    public string $metodo_pago = 'efectivo';
    public string $nota = '';

    // Carrito de Compra
    public array $cart = [];
    
    // Filtro de catálogo izquierdo
    public string $searchProducto = '';

    // Cupones de Descuento
    public string $couponCode = '';
    public ?int $appliedCouponId = null;
    public float $couponDiscountAmount = 0.00;

    /**
     * Apply coupon discount.
     */
    public function aplicarCupon(): void
    {
        if (empty($this->couponCode)) {
            Flux::toast(variant: 'danger', text: __('Ingrese un código de cupón.'));
            return;
        }

        $cupon = \App\Models\Cupon::where('codigo', strtoupper($this->couponCode))
            ->where('activo', true)
            ->first();

        if (!$cupon) {
            Flux::toast(variant: 'danger', text: __('El código de cupón ingresado no es válido o está inactivo.'));
            $this->cancelarCupon();
            return;
        }

        $now = now();
        if (($cupon->fecha_inicio && $now->lt($cupon->fecha_inicio)) || ($cupon->fecha_expiracion && $now->gt($cupon->fecha_expiracion))) {
            Flux::toast(variant: 'danger', text: __('El cupón ha expirado o aún no está vigente.'));
            $this->cancelarCupon();
            return;
        }

        if ($cupon->usos_restantes <= 0) {
            Flux::toast(variant: 'danger', text: __('El cupón ya ha superado el límite de usos permitidos.'));
            $this->cancelarCupon();
            return;
        }

        $subtotalActual = collect($this->cart)->sum('total');
        if ($subtotalActual < $cupon->monto_minimo_compra) {
            Flux::toast(variant: 'danger', text: __('El monto mínimo de compra para este cupón es de S/ ' . number_format($cupon->monto_minimo_compra, 2)));
            $this->cancelarCupon();
            return;
        }

        $this->appliedCouponId = $cupon->id;
        if ($cupon->tipo_descuento === 'porcentaje') {
            $this->couponDiscountAmount = round($subtotalActual * ($cupon->valor_descuento / 100), 2);
        } else {
            $this->couponDiscountAmount = round(min($cupon->valor_descuento, $subtotalActual), 2);
        }

        Flux::toast(variant: 'success', text: __('Cupón aplicado. Descuento: S/ ' . number_format($this->couponDiscountAmount, 2)));
    }

    /**
     * Cancel coupon.
     */
    public function cancelarCupon(): void
    {
        $this->appliedCouponId = null;
        $this->couponCode = '';
        $this->couponDiscountAmount = 0.00;
    }

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->fecha_venta = now()->format('Y-m-d');
        
        $almacen = Almacen::where('activo', true)->first();
        if ($almacen) {
            $this->almacen_id = $almacen->id;
        }

        $cliente = User::first();
        if ($cliente) {
            $this->cliente_id = $cliente->id;
        }

        $this->actualizarSeries();
    }

    /**
     * Load next serial and correlative.
     */
    public function actualizarSeries(): void
    {
        if (!$this->tipo_documento_id) {
            // Boleta por defecto (codigo_sunat = 03)
            $tipoDoc = TipoDocumento::where('codigo_sunat', '03')->first();
            if ($tipoDoc) {
                $this->tipo_documento_id = $tipoDoc->id;
            }
        }

        if ($this->tipo_documento_id && $this->almacen_id) {
            $almacen = Almacen::find($this->almacen_id);
            if ($almacen) {
                $serie = DB::table('series')
                    ->where('sede_id', $almacen->sede_id)
                    ->where('tipo_documento_id', $this->tipo_documento_id)
                    ->where('activo', true)
                    ->first();
                    
                if ($serie) {
                    $this->serie = $serie->serie;
                    $this->correlativo = $serie->correlativo + 1;
                    return;
                }
            }
        }

        // Serie por defecto en caso de no encontrarse configuración
        $this->serie = 'F001';
        $this->correlativo = 1;
    }

    public function updatedAlmacenId(): void
    {
        $this->actualizarSeries();
        $this->cart = []; // Resetear carrito al cambiar almacén
    }

    public function updatedTipoDocumentoId(): void
    {
        $this->actualizarSeries();
    }

    /**
     * Add a variation to the cart.
     */
    public function addToCart(int $variacionId): void
    {
        if (!$this->almacen_id) {
            Flux::toast(variant: 'danger', text: __('Debe seleccionar un almacén origen.'));
            return;
        }

        // Buscar stock disponible
        $inv = Inventario::where('almacen_id', $this->almacen_id)
            ->where('variacion_id', $variacionId)
            ->first();

        $stockDisponible = $inv ? $inv->stock_base : 0;

        if ($stockDisponible <= 0) {
            Flux::toast(variant: 'danger', text: __('Esta variación no cuenta con stock disponible en este almacén.'));
            return;
        }

        // Evitar duplicados en el carrito
        $cartIndex = collect($this->cart)->firstWhere('variacion_id', $variacionId);
        if ($cartIndex !== null) {
            Flux::toast(variant: 'warning', text: __('El producto ya está en el carrito.'));
            return;
        }

        $var = Variacion::with(['producto', 'valores.atributo', 'precios.listaPrecio'])->findOrFail($variacionId);
        
        // Empaques del producto
        $empaques = ProductoEmpaque::with('unidadMedida')
            ->where('producto_id', $var->producto_id)
            ->get()
            ->map(function ($pe) {
                return [
                    'unidad_medida_id' => $pe->unidad_medida_id,
                    'nombre' => $pe->unidadMedida->nombre,
                    'factor' => $pe->factor_conversion,
                ];
            })->toArray();

        // Agregar la presentación base "Unidad" por defecto
        array_unshift($empaques, [
            'unidad_medida_id' => 1, // Unidad
            'nombre' => 'Unidad',
            'factor' => 1
        ]);

        // Cargar precios de lista
        $precioMenor = $var->precios->firstWhere('listaPrecio.nombre', 'Precio Menor')?->precio ?? 0.00;
        $precioMayor = $var->precios->firstWhere('listaPrecio.nombre', 'Precio Mayor')?->precio ?? 0.00;

        $desc = $var->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');

        $this->cart[] = [
            'variacion_id' => $var->id,
            'nombre' => $var->producto->nombre . ' (' . $desc . ')',
            'sku' => $var->sku,
            'unidad_medida_id' => 1, // Unidad por defecto
            'factor_conversion' => 1,
            'empaques' => $empaques,
            'cantidad' => 1,
            'cantidad_base' => 1,
            'precio_menor' => $precioMenor,
            'precio_mayor' => $precioMayor,
            'precio_tipo' => 'menor', // menor o mayor
            'precio_unitario' => $precioMenor,
            'subtotal' => $precioMenor,
            'total' => $precioMenor,
            'stock_disponible' => $stockDisponible,
        ];
    }

    /**
     * Remove item from cart.
     */
    public function removeFromCart(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    /**
     * Recalculate totals on row updates.
     */
    public function updatedCart($value, $name): void
    {
        // El name tiene formato: "cart.INDEX.KEY"
        if (preg_match('/^cart\.(\d+)\.(unidad_medida_id|cantidad|precio_tipo|precio_unitario)$/', $name, $matches)) {
            $index = (int)$matches[1];
            $field = $matches[2];

            $item = &$this->cart[$index];

            if ($field === 'unidad_medida_id') {
                $empaque = collect($item['empaques'])->firstWhere('unidad_medida_id', $item['unidad_medida_id']);
                $item['factor_conversion'] = $empaque ? $empaque['factor'] : 1;
            }

            if ($field === 'precio_tipo') {
                $item['precio_unitario'] = $item['precio_tipo'] === 'menor' ? $item['precio_menor'] : $item['precio_mayor'];
            }

            $cantidad = (int)($item['cantidad'] ?: 1);
            $factor = (int)($item['factor_conversion'] ?: 1);
            $item['cantidad_base'] = $cantidad * $factor;

            // Validar que no supere el stock disponible
            if ($item['cantidad_base'] > $item['stock_disponible']) {
                Flux::toast(variant: 'danger', text: __('La cantidad solicitada supera el stock físico disponible (' . $item['stock_disponible'] . ' uds.).'));
                $item['cantidad'] = floor($item['stock_disponible'] / $factor) ?: 1;
                $item['cantidad_base'] = $item['cantidad'] * $factor;
            }

            $precio = (float)($item['precio_unitario'] ?: 0);
            $item['total'] = round($item['cantidad_base'] * $precio, 2);
            $item['subtotal'] = round($item['total'] / 1.18, 2);
        }
    }

    /**
     * Computed totals of the entire shopping cart.
     */
    #[Computed]
    public function totales(): array
    {
        $subtotalCar = collect($this->cart)->sum('total');

        if ($this->appliedCouponId) {
            $cupon = \App\Models\Cupon::find($this->appliedCouponId);
            if ($cupon) {
                if ($cupon->tipo_descuento === 'porcentaje') {
                    $this->couponDiscountAmount = round($subtotalCar * ($cupon->valor_descuento / 100), 2);
                } else {
                    $this->couponDiscountAmount = round(min($cupon->valor_descuento, $subtotalCar), 2);
                }
            }
        } else {
            $this->couponDiscountAmount = 0.00;
        }

        $total = max(0, $subtotalCar - $this->couponDiscountAmount);
        $subtotal = round($total / 1.18, 2);
        $impuesto = round($total - $subtotal, 2);

        return [
            'subtotal' => $subtotal,
            'impuesto' => $impuesto,
            'total' => $total,
            'descuento' => $this->couponDiscountAmount,
        ];
    }

    /**
     * Save the sale.
     */
    public function guardar(): void
    {
        $this->validate([
            'almacen_id' => 'required|integer|exists:almacens,id',
            'cliente_id' => 'required|integer|exists:users,id',
            'tipo_documento_id' => 'required|integer|exists:tipo_documentos,id',
            'serie' => 'required|string|max:10',
            'correlativo' => 'required|integer|min:1',
            'metodo_pago' => 'required|string',
        ]);

        if (empty($this->cart)) {
            Flux::toast(variant: 'danger', text: __('Debe agregar al menos un producto al carrito de compras.'));
            return;
        }

        DB::transaction(function () {
            $tot = $this->totales;

            // Registrar cabecera
            $venta = Venta::create([
                'user_id' => $this->cliente_id,
                'tipo_documento_id' => $this->tipo_documento_id,
                'serie' => $this->serie,
                'correlativo' => $this->correlativo,
                'subtotal' => $tot['subtotal'],
                'descuento' => $tot['descuento'],
                'total' => $tot['total'],
                'impuesto' => $tot['impuesto'],
                'estado_pago' => 'pagado',
                'estado_despacho' => 'entregado',
                'tipo_pago' => $this->tipo_pago,
                'metodo_pago' => $this->metodo_pago,
                'cupon_id' => $this->appliedCouponId,
                'comentarios' => $this->nota,
            ]);

            // Decrementar usos del cupón si se aplicó
            if ($this->appliedCouponId) {
                \App\Models\Cupon::find($this->appliedCouponId)->decrement('usos_restantes');
            }

            // Registrar detalles, descontar stock e impactar Kardex
            foreach ($this->cart as $item) {
                VentaDetalle::create([
                    'venta_id' => $venta->id,
                    'variacion_id' => $item['variacion_id'],
                    'unidad_medida_id' => $item['unidad_medida_id'],
                    'cantidad' => $item['cantidad'],
                    'factor_conversion' => $item['factor_conversion'],
                    'cantidad_base' => $item['cantidad_base'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $item['subtotal'],
                    'total' => $item['total'],
                ]);

                // Descontar Stock
                $inv = Inventario::where('almacen_id', $this->almacen_id)
                    ->where('variacion_id', $item['variacion_id'])
                    ->first();

                $stockAnterior = $inv->stock_base;
                $inv->decrement('stock_base', $item['cantidad_base']);
                $stockPosterior = $inv->stock_base;

                // Registrar en Kardex
                Kardex::create([
                    'almacen_id' => $this->almacen_id,
                    'variacion_id' => $item['variacion_id'],
                    'tipo_transaccion' => 'Salida',
                    'concepto' => 'Venta ' . $this->serie . '-' . str_pad($this->correlativo, 6, '0', STR_PAD_LEFT),
                    'cantidad' => $item['cantidad_base'],
                    'stock_anterior' => $stockAnterior,
                    'stock_posterior' => $stockPosterior,
                    'costo_unitario' => $item['precio_unitario'],
                    'costo_total' => $item['total'],
                    'valor_total_almacen' => $stockPosterior * $item['precio_unitario'],
                    'origen_documento_id' => $venta->id,
                    'origen_documento_type' => Venta::class,
                    'creado_por_usuario_id' => Auth::id(),
                ]);
            }

            // Incrementar correlativo de serie
            DB::table('series')
                ->where('sede_id', Almacen::find($this->almacen_id)->sede_id)
                ->where('tipo_documento_id', $this->tipo_documento_id)
                ->where('serie', $this->serie)
                ->increment('correlativo');
        });

        Flux::toast(variant: 'success', text: __('Venta registrada y stock descontado del almacén.'));
        $this->redirect(route('ventas.index'), navigate: true);
    }

    /**
     * Computed options.
     */
    #[Computed]
    public function almacenes()
    {
        return Almacen::where('activo', true)->get();
    }

    #[Computed]
    public function clientes()
    {
        return User::all();
    }

    #[Computed]
    public function tipoDocumentos()
    {
        // Boleta, Factura, Nota de Venta (codigo_sunat = 01, 03, 00)
        return TipoDocumento::whereIn('codigo_sunat', ['01', '03', '00'])->get();
    }

    /**
     * Catalog of active products on warehouse.
     */
    #[Computed]
    public function catalogo()
    {
        if (!$this->almacen_id) {
            return collect();
        }

        return Variacion::with(['producto', 'valores.atributo', 'precios', 'inventarios' => function($query) {
            $query->where('almacen_id', $this->almacen_id);
        }])
        ->whereHas('producto', function ($q) {
            $q->where('nombre', 'like', '%' . $this->searchProducto . '%')
              ->orWhere('sku', 'like', '%' . $this->searchProducto . '%');
        })
        ->where('activo', true)
        ->get();
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Registrar Nueva Venta (POS)') }}</flux:heading>
        <flux:subheading>{{ __('Selecciona productos del almacén e ingresa los datos del cliente para facturar.') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Panel Izquierdo: Catálogo de Productos y SKUs en Almacén -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm h-fit">
            <flux:heading size="lg">{{ __('Catálogo del Almacén') }}</flux:heading>
            
            <!-- Buscador Catálogo -->
            <flux:input wire:model.live="searchProducto" placeholder="Buscar por SKU o descripción..." icon="magnifying-glass" />

            <!-- Lista de Variaciones -->
            <div class="divide-y divide-zinc-200 dark:divide-zinc-800 max-h-[500px] overflow-y-auto pr-2">
                @forelse($this->catalogo as $var)
                    @php
                        $desc = $var->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
                        $stock = $var->inventarios->firstWhere('almacen_id', $almacen_id)?->stock_base ?? 0;
                        $precioMenor = $var->precios->firstWhere('listaPrecio.nombre', 'Precio Menor')?->precio ?? 0.00;
                    @endphp
                    <div class="py-3 flex items-center justify-between gap-4 first:pt-0 last:pb-0">
                        <div class="space-y-0.5">
                            <div class="font-semibold text-zinc-900 dark:text-white text-xs">{{ $var->producto->nombre }}</div>
                            <div class="text-xxs text-zinc-500">{{ $desc }} (SKU: {{ $var->sku }})</div>
                            <div class="flex items-center gap-3 mt-1 text-xxs">
                                <span class="font-bold text-zinc-900 dark:text-zinc-300">S/ {{ number_format($precioMenor, 2) }}</span>
                                <span class="text-zinc-500">|</span>
                                <span class="font-medium {{ $stock > 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ __('Stock: ') }}{{ $stock }}</span>
                            </div>
                        </div>

                        @if($stock > 0)
                            <flux:button variant="ghost" icon="plus" size="sm" wire:click.prevent="addToCart({{ $var->id }})" title="Añadir al carrito" />
                        @else
                            <span class="text-xxs text-zinc-400 italic">{{ __('Agotado') }}</span>
                        @endif
                    </div>
                @empty
                    <div class="text-center py-8 text-zinc-500 text-xs">
                        {{ __('No hay variaciones con stock en este almacén.') }}
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Panel Derecho: Carrito de Compras y Detalles del Comprobante -->
        <div class="lg:col-span-2 space-y-6">
            <form wire:submit.prevent="guardar" class="space-y-6">
                <!-- Tarjeta Datos de Facturación -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm">
                    <flux:heading size="lg">{{ __('Datos de Facturación') }}</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Almacén Origen -->
                        <flux:select wire:model.live="almacen_id" :label="__('Almacén Origen')">
                            @foreach($this->almacenes as $alm)
                                <flux:select.option value="{{ $alm->id }}">{{ $alm->nombre }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <!-- Tipo de Pago -->
                        <flux:select wire:model="tipo_pago" :label="__('Tipo de Pago')">
                            <flux:select.option value="online">{{ __('Online') }}</flux:select.option>
                            <flux:select.option value="contraentrega">{{ __('Contraentrega') }}</flux:select.option>
                        </flux:select>

                        <!-- Método de Pago -->
                        <flux:select wire:model="metodo_pago" :label="__('Método de Pago')">
                            <flux:select.option value="efectivo">{{ __('Efectivo') }}</flux:select.option>
                            <flux:select.option value="tarjeta">{{ __('Tarjeta de Crédito/Débito') }}</flux:select.option>
                            <flux:select.option value="transferencia">{{ __('Transferencia Bancaria') }}</flux:select.option>
                            <flux:select.option value="yape_plin">{{ __('Yape / Plin') }}</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Cliente -->
                        <flux:select wire:model="cliente_id" :label="__('Cliente')" placeholder="Seleccionar Cliente...">
                            @foreach($this->clientes as $cli)
                                <flux:select.option value="{{ $cli->id }}">{{ $cli->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <!-- Comprobante -->
                        <flux:select wire:model.live="tipo_documento_id" :label="__('Comprobante')">
                            @foreach($this->tipoDocumentos as $td)
                                <flux:select.option value="{{ $td->id }}">{{ $td->nombre }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Serie -->
                            <flux:input wire:model="serie" :label="__('Serie')" placeholder="F001" readonly />
                            <!-- Correlativo -->
                            <flux:input wire:model="correlativo" :label="__('Correlativo')" readonly />
                        </div>
                    </div>
                </div>

                <!-- Tarjeta Carrito -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
                    <flux:heading size="lg">{{ __('Carrito de Venta') }}</flux:heading>

                    @if(empty($cart))
                        <div class="text-center py-12 text-zinc-500 text-sm">
                            {{ __('El carrito de compras está vacío. Agrega productos desde el catálogo izquierdo.') }}
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-medium">
                                        <th class="pb-3 w-1/3">{{ __('Producto / SKU') }}</th>
                                        <th class="pb-3 w-1/4 text-center">{{ __('Presentación') }}</th>
                                        <th class="pb-3 w-12 text-center">{{ __('Cant.') }}</th>
                                        <th class="pb-3 w-20 text-center">{{ __('Tarifa') }}</th>
                                        <th class="pb-3 w-20 text-right">{{ __('Precio Unit.') }}</th>
                                        <th class="pb-3 w-20 text-right">{{ __('Total') }}</th>
                                        <th class="pb-3 w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($cart as $index => $item)
                                        <tr class="align-top py-2">
                                            <td class="py-3 pr-2">
                                                <div class="font-medium text-zinc-900 dark:text-white">{{ $item['nombre'] }}</div>
                                                <div class="text-xxs text-zinc-500">SKU: {{ $item['sku'] }} (Stock: {{ $item['stock_disponible'] }})</div>
                                            </td>

                                            <td class="py-3 pr-2">
                                                <flux:select wire:model.live="cart.{{ $index }}.unidad_medida_id">
                                                    @foreach($item['empaques'] as $emp)
                                                        <flux:select.option value="{{ $emp['unidad_medida_id'] }}">{{ $emp['nombre'] }} (x{{ $emp['factor'] }})</flux:select.option>
                                                    @endforeach
                                                </flux:select>
                                            </td>

                                            <td class="py-3 pr-2">
                                                <flux:input wire:model.live="cart.{{ $index }}.cantidad" type="number" min="1" class="text-center" />
                                            </td>

                                            <td class="py-3 pr-2">
                                                <flux:select wire:model.live="cart.{{ $index }}.precio_tipo">
                                                    <flux:select.option value="menor">{{ __('Precio Menor') }}</flux:select.option>
                                                    <flux:select.option value="mayor">{{ __('Precio Mayor') }}</flux:select.option>
                                                </flux:select>
                                            </td>

                                            <td class="py-3 pr-2">
                                                <flux:input wire:model.live="cart.{{ $index }}.precio_unitario" type="number" step="0.01" class="text-right" />
                                            </td>

                                            <td class="py-3 pr-2 text-right align-middle font-semibold text-zinc-900 dark:text-white">
                                                S/ {{ number_format($item['total'], 2) }}
                                            </td>

                                            <td class="py-3 text-right align-middle">
                                                <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="removeFromCart({{ $index }})" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Aplicar Cupón -->
                        <div class="flex items-center gap-4 border-t border-zinc-200 dark:border-zinc-700 pt-4 mt-4 justify-end w-full">
                            <div class="w-64">
                                <flux:input wire:model="couponCode" placeholder="Ingresar cupón de descuento..." :disabled="(bool)$appliedCouponId" />
                            </div>
                            @if(!$appliedCouponId)
                                <flux:button variant="ghost" wire:click.prevent="aplicarCupon">{{ __('Aplicar Cupón') }}</flux:button>
                            @else
                                <flux:button variant="ghost" color="rose" wire:click.prevent="cancelarCupon">{{ __('Quitar Cupón') }}</flux:button>
                            @endif
                        </div>

                        <!-- Totales Checkout -->
                        <div class="flex flex-col items-end gap-1.5 border-t border-zinc-200 dark:border-zinc-700 pt-4 text-xs text-zinc-600 dark:text-zinc-400 w-full">
                            <div class="flex justify-between w-48">
                                <span>Subtotal:</span>
                                <span>S/ {{ number_format($this->totales['subtotal'], 2) }}</span>
                            </div>
                            @if($this->totales['descuento'] > 0)
                                <div class="flex justify-between w-48 text-emerald-600 font-semibold">
                                    <span>Descuento:</span>
                                    <span>- S/ {{ number_format($this->totales['descuento'], 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between w-48">
                                <span>IGV (18%):</span>
                                <span>S/ {{ number_format($this->totales['impuesto'], 2) }}</span>
                            </div>
                            <div class="flex justify-between w-48 text-base font-bold text-zinc-900 dark:text-white border-t border-zinc-100 dark:border-zinc-800 pt-2">
                                <span>Total:</span>
                                <span>S/ {{ number_format($this->totales['total'], 2) }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Botones de Acción -->
                <div class="flex items-center justify-end gap-4">
                    <flux:button variant="ghost" :href="route('ventas.index')" wire:navigate>
                        {{ __('Cancelar') }}
                    </flux:button>
                    
                    <flux:button variant="primary" type="submit" :disabled="empty($cart)">
                        {{ __('Registrar y Cobrar Venta') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</div>
