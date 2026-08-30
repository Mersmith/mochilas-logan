<?php

use App\Models\Almacen;
use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\ListaPrecio;
use App\Models\ProductoEmpaque;
use App\Models\Sede;
use App\Models\Serie;
use App\Models\TipoDocumento;
use App\Models\UnidadMedida;
use App\Models\User;
use App\Models\Variacion;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Registrar Venta')] class extends Component {
    public ?int $sede_id = null;
    public ?int $almacen_id = null;
    public ?int $cliente_id = null;
    public ?int $lista_precio_id = null;
    public ?int $tipo_documento_id = null;
    public string $serie = '';
    public int $correlativo = 1;
    public string $fecha_venta = '';
    public string $tipo_pago = 'online';
    public string $metodo_pago = 'efectivo';
    public string $nota = '';

    // Pistoleo / Escáner de Código de Barras
    public string $barcode = '';

    // Carrito de Compra
    public array $cart = [];

    // Filtro de catálogo izquierdo
    public string $searchProducto = '';

    // Cupones de Descuento
    public string $couponCode = '';
    public ?int $appliedCouponId = null;
    public float $couponDiscountAmount = 0.00;

    // Modalidad de Pago y Calculadora de Vuelto
    public string $modalidad_pago = 'simple'; // 'simple' | 'mixto'
    public float $monto_efectivo_recibido = 0.00;

    // Pago Mixto / Fraccionado
    public float $monto_pago_efectivo = 0.00;
    public float $monto_pago_yape_plin = 0.00;
    public float $monto_pago_tarjeta = 0.00;
    public float $monto_pago_transferencia = 0.00;

    // Modal Rápido de Cliente
    public string $modal_tipo_persona = 'natural';
    public string $modal_tipo_cliente = 'minorista';
    public ?string $modal_dni = '';
    public ?string $modal_ruc = '';
    public string $modal_nombre = '';
    public ?string $modal_email = '';
    public ?string $modal_telefono = '';
    public ?int $modal_lista_precio_id = null;

    // Modal de Venta Exitosa
    public bool $showSuccessModal = false;
    public ?int $completedVentaId = null;
    public string $completedComprobante = '';
    public float $completedTotal = 0.00;
    public float $completedVuelto = 0.00;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->fecha_venta = now()->format('Y-m-d');

        $primeraSede = Sede::where('activo', true)->first();
        if ($primeraSede) {
            $this->sede_id = $primeraSede->id;
            $this->almacen_id = Almacen::where('sede_id', $primeraSede->id)->where('activo', true)->first()?->id;
        }

        $primeraLista = ListaPrecio::where('activo', true)->first();
        if ($primeraLista) {
            $this->lista_precio_id = $primeraLista->id;
            $this->modal_lista_precio_id = $primeraLista->id;
        }

        $cliente = User::first();
        if ($cliente) {
            $this->cliente_id = $cliente->id;
        }

        $this->actualizarSeries();
    }

    /**
     * Load next serial and correlative based on Sede and TipoDocumento.
     */
    public function actualizarSeries(): void
    {
        if (! $this->tipo_documento_id) {
            $tipoDoc = TipoDocumento::where('codigo_sunat', '03')->where('activo', true)->first()
                ?? TipoDocumento::where('activo', true)->first();
            if ($tipoDoc) {
                $this->tipo_documento_id = $tipoDoc->id;
            }
        }

        if ($this->sede_id && $this->tipo_documento_id) {
            $serie = Serie::where('sede_id', $this->sede_id)
                ->where('tipo_documento_id', $this->tipo_documento_id)
                ->where('activo', true)
                ->first();

            if ($serie) {
                $this->serie = $serie->serie;
                $this->correlativo = (int) $serie->correlativo;
                return;
            }
        }

        $this->serie = '';
        $this->correlativo = 1;
    }

    public function updatedSedeId(): void
    {
        $this->almacen_id = $this->almacenes->first()?->id;
        $this->cart = [];
        $this->actualizarSeries();
    }

    public function updatedAlmacenId(): void
    {
        $this->cart = [];
        $this->actualizarSeries();
    }

    public function updatedTipoDocumentoId(): void
    {
        $this->actualizarSeries();
    }

    public function updatedClienteId(): void
    {
        if (! $this->cliente_id) {
            return;
        }

        $cliente = Cliente::where('user_id', $this->cliente_id)->first();
        if ($cliente && $cliente->lista_precio_id) {
            $this->lista_precio_id = $cliente->lista_precio_id;
            $this->updatedListaPrecioId();
            Flux::toast(variant: 'success', text: __('Tarifa actualizada automáticamente según el perfil del cliente.'));
        }
    }

    public function updatedListaPrecioId(): void
    {
        foreach ($this->cart as $index => &$item) {
            $item['lista_precio_id'] = $this->lista_precio_id;
            $precioObj = collect($item['precios'])->firstWhere('lista_precio_id', $this->lista_precio_id)
                ?? collect($item['precios'])->first();
            $item['precio_unitario'] = $precioObj ? (float) $precioObj['precio'] : 0.00;
            $item['subtotal'] = round($item['cantidad'] * $item['precio_unitario'], 2);
            $item['total'] = $item['subtotal'];
        }
    }

    /**
     * Escaneo de Código de Barras / SKU con lectora láser.
     */
    public function scanBarcode(): void
    {
        $code = trim($this->barcode);
        if (empty($code)) {
            return;
        }

        if (! $this->almacen_id) {
            Flux::toast(variant: 'danger', text: __('Debe seleccionar un almacén origen primero.'));
            $this->barcode = '';
            return;
        }

        $var = Variacion::with(['producto', 'valores.atributo', 'precios.listaPrecio'])
            ->where('activo', true)
            ->where(function ($q) use ($code) {
                $q->where('sku', $code)
                    ->orWhere('codigo_barras', $code)
                    ->orWhereHas('producto', fn ($p) => $p->where('sku', $code));
            })
            ->first();

        if (! $var) {
            Flux::toast(variant: 'warning', text: __("No se encontró ningún producto con el código ':code'.", ['code' => $code]));
            $this->barcode = '';
            return;
        }

        // Si ya está en el carrito, incrementar cantidad
        $cartIndex = collect($this->cart)->search(fn ($item) => $item['variacion_id'] === $var->id);

        if ($cartIndex !== false) {
            $this->cart[$cartIndex]['cantidad']++;
            $this->updatedCart($this->cart[$cartIndex]['cantidad'], "cart.{$cartIndex}.cantidad");
            Flux::toast(variant: 'success', text: __('Cantidad +1: ') . $var->producto->nombre);
        } else {
            $this->addToCart($var->id);
        }

        $this->barcode = '';
    }

    /**
     * Add a variation to the cart.
     */
    public function addToCart(int $variacionId): void
    {
        if (! $this->almacen_id) {
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

        // Unidad base
        $unidadBase = UnidadMedida::where('nombre', 'like', '%UNIDAD%')
            ->orWhereIn('abreviacion', ['UND', 'U', 'NIU'])
            ->first() ?? UnidadMedida::first();

        $baseId = $unidadBase?->id ?? 1;
        $baseNombre = $unidadBase?->nombre ?? 'Unidad';

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

        // Agregar la presentación base al inicio
        array_unshift($empaques, [
            'unidad_medida_id' => $baseId,
            'nombre' => $baseNombre,
            'factor' => 1,
        ]);

        // Cargar todas las listas de precios disponibles
        $preciosDisponibles = $var->precios->map(function ($vp) {
            return [
                'lista_precio_id' => $vp->lista_precio_id,
                'nombre' => $vp->listaPrecio?->nombre ?? 'Precio',
                'precio' => (float) $vp->precio,
            ];
        })->values()->toArray();

        if (empty($preciosDisponibles)) {
            $preciosDisponibles = [
                ['lista_precio_id' => null, 'nombre' => 'General', 'precio' => 0.00],
            ];
        }

        // Precio según la lista global seleccionada o la primera disponible
        $precioSeleccionado = collect($preciosDisponibles)->firstWhere('lista_precio_id', $this->lista_precio_id)
            ?? $preciosDisponibles[0];

        $listaPrecioId = $precioSeleccionado['lista_precio_id'];
        $precioUnitario = $precioSeleccionado['precio'];

        $desc = $var->valores->map(fn ($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');

        $this->cart[] = [
            'variacion_id' => $var->id,
            'nombre' => $var->producto->nombre . ' (' . $desc . ')',
            'sku' => $var->sku,
            'unidad_medida_id' => $baseId,
            'factor_conversion' => 1,
            'empaques' => $empaques,
            'precios' => $preciosDisponibles,
            'lista_precio_id' => $listaPrecioId,
            'cantidad' => 1,
            'cantidad_base' => 1,
            'precio_unitario' => $precioUnitario,
            'subtotal' => $precioUnitario,
            'total' => $precioUnitario,
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
        if (preg_match('/^cart\.(\d+)\.(unidad_medida_id|cantidad|lista_precio_id|precio_unitario)$/', $name, $matches)) {
            $index = (int) $matches[1];
            $field = $matches[2];

            $item = &$this->cart[$index];

            if ($field === 'unidad_medida_id') {
                $empaque = collect($item['empaques'])->firstWhere('unidad_medida_id', $item['unidad_medida_id']);
                $item['factor_conversion'] = $empaque ? $empaque['factor'] : 1;
            }

            if ($field === 'lista_precio_id') {
                $precioObj = collect($item['precios'])->firstWhere('lista_precio_id', $item['lista_precio_id']);
                $item['precio_unitario'] = $precioObj ? (float) $precioObj['precio'] : 0.00;
            }

            $cantidad = (int) ($item['cantidad'] ?: 1);
            $factor = (int) ($item['factor_conversion'] ?: 1);
            $item['cantidad_base'] = $cantidad * $factor;

            // Validar que no supere el stock disponible
            if ($item['cantidad_base'] > $item['stock_disponible']) {
                Flux::toast(variant: 'danger', text: __('La cantidad solicitada supera el stock físico disponible (' . $item['stock_disponible'] . ' uds.).'));
                $item['cantidad'] = floor($item['stock_disponible'] / $factor) ?: 1;
                $item['cantidad_base'] = $item['cantidad'] * $factor;
            }

            $item['subtotal'] = round($item['cantidad'] * $item['precio_unitario'], 2);
            $item['total'] = $item['subtotal'];
        }
    }

    /**
     * Calculadora de Vuelto: Botones rápidos de billetes.
     */
    public function establecerMontoRecibido(float $monto): void
    {
        $this->monto_efectivo_recibido = $monto;
    }

    public function pagarMontoExacto(): void
    {
        $subtotal = collect($this->cart)->sum('total');
        $total = max(0, $subtotal - $this->couponDiscountAmount);
        $this->monto_efectivo_recibido = $total;
    }

    /**
     * Apply coupon discount.
     */
    public function aplicarCupon(): void
    {
        if (empty($this->couponCode)) {
            Flux::toast(variant: 'danger', text: __('Ingrese un código de cupón.'));
            return;
        }

        $cupon = Cupon::where('codigo', strtoupper($this->couponCode))
            ->where('activo', true)
            ->first();

        if (! $cupon) {
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

    public function cancelarCupon(): void
    {
        $this->appliedCouponId = null;
        $this->couponCode = '';
        $this->couponDiscountAmount = 0.00;
    }

    /**
     * Modal rápido para nuevo cliente.
     */
    public function abrirModalCliente(): void
    {
        $this->reset(['modal_dni', 'modal_ruc', 'modal_nombre', 'modal_email', 'modal_telefono']);
        $this->modal_tipo_persona = 'natural';
        $this->modal_tipo_cliente = 'minorista';
        $this->modal_lista_precio_id = $this->lista_precio_id ?? ListaPrecio::first()?->id;
        $this->modal('modal-nuevo-cliente')->show();
    }

    public function guardarClienteRapido(): void
    {
        $rules = [
            'modal_tipo_persona' => 'required|in:natural,juridica',
            'modal_tipo_cliente' => 'required|in:minorista,mayorista,emprendedor',
            'modal_nombre' => 'required|string|max:255',
            'modal_email' => 'nullable|email|unique:users,email',
            'modal_telefono' => 'nullable|string|max:20',
            'modal_lista_precio_id' => 'required|exists:lista_precios,id',
        ];

        if ($this->modal_tipo_persona === 'natural') {
            $rules['modal_dni'] = 'required|string|size:8|unique:clientes,dni';
            $this->modal_ruc = null;
        } else {
            $rules['modal_ruc'] = 'required|string|size:11|unique:clientes,ruc';
            $this->modal_dni = null;
        }

        $this->validate($rules);

        $emailFinal = $this->modal_email ?: ($this->modal_dni ? "dni_{$this->modal_dni}@mochilaslogan.local" : "ruc_{$this->modal_ruc}@mochilaslogan.local");

        $newUser = DB::transaction(function () use ($emailFinal) {
            $user = User::create([
                'name' => $this->modal_nombre,
                'email' => $emailFinal,
                'password' => bcrypt(Str::random(12)),
                'activo' => true,
            ]);

            $user->assignRole('cliente');

            Cliente::create([
                'user_id' => $user->id,
                'tipo_persona' => $this->modal_tipo_persona,
                'tipo_cliente' => $this->modal_tipo_cliente,
                'lista_precio_id' => $this->modal_lista_precio_id,
                'dni' => $this->modal_dni,
                'ruc' => $this->modal_ruc,
                'razon_social' => $this->modal_tipo_persona === 'juridica' ? $this->modal_nombre : null,
                'telefono' => $this->modal_telefono,
                'activo' => true,
            ]);

            return $user;
        });

        $this->cliente_id = $newUser->id;
        $this->lista_precio_id = $this->modal_lista_precio_id;
        $this->updatedListaPrecioId();

        $this->modal('modal-nuevo-cliente')->close();
        Flux::toast(variant: 'success', text: __('Cliente registrado y seleccionado para esta venta.'));
    }

    /**
     * Store sale in database and decrease stock.
     */
    public function guardar(): void
    {
        $this->validate([
            'sede_id' => 'required|exists:sedes,id',
            'almacen_id' => 'required|exists:almacens,id',
            'cliente_id' => 'required|exists:users,id',
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'serie' => 'required|string|max:10',
            'correlativo' => 'required|integer|min:1',
            'fecha_venta' => 'required|date',
            'cart' => 'required|array|min:1',
        ], [
            'cart.min' => __('Debe agregar al menos un producto al carrito para registrar la venta.'),
        ]);

        $subtotal = collect($this->cart)->sum('total');
        $descuento = $this->couponDiscountAmount;
        $total = max(0, $subtotal - $descuento);

        // Validación para Pago Mixto
        if ($this->modalidad_pago === 'mixto') {
            $sumaMixta = (float) ($this->monto_pago_efectivo + $this->monto_pago_yape_plin + $this->monto_pago_tarjeta + $this->monto_pago_transferencia);
            if (abs($sumaMixta - $total) > 0.05) {
                Flux::toast(variant: 'danger', text: __('En Pago Mixto, la suma de los montos (S/ ' . number_format($sumaMixta, 2) . ') debe ser igual al Total (S/ ' . number_format($total, 2) . ').'));
                return;
            }
        }

        $sesionActiva = $this->sesionActiva;
        $comentariosFinal = $this->nota;

        if ($this->modalidad_pago === 'mixto') {
            $desgloseTexto = "[PAGO MIXTO]: Efectivo S/ " . number_format($this->monto_pago_efectivo, 2) .
                " | Yape/Plin S/ " . number_format($this->monto_pago_yape_plin, 2) .
                " | Tarjeta S/ " . number_format($this->monto_pago_tarjeta, 2) .
                " | Transferencia S/ " . number_format($this->monto_pago_transferencia, 2);
            $comentariosFinal = trim($comentariosFinal . "\n" . $desgloseTexto);
        } elseif ($this->metodo_pago === 'efectivo' && $this->monto_efectivo_recibido > $total) {
            $vueltoCalc = $this->monto_efectivo_recibido - $total;
            $comentariosFinal = trim($comentariosFinal . "\n[PAGO EFECTIVO]: Pagó con S/ " . number_format($this->monto_efectivo_recibido, 2) . " | Vuelto: S/ " . number_format($vueltoCalc, 2));
        }

        $ventaCreada = DB::transaction(function () use ($subtotal, $descuento, $total, $sesionActiva, $comentariosFinal) {
            // Crear la Venta
            $venta = Venta::create([
                'user_id' => $this->cliente_id,
                'caja_sesion_id' => $sesionActiva?->id,
                'tipo_documento_id' => $this->tipo_documento_id,
                'serie' => $this->serie,
                'correlativo' => $this->correlativo,
                'estado_pago' => 'pagado',
                'estado_despacho' => 'entregado',
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'costo_envio' => 0.00,
                'total' => $total,
                'tipo_pago' => $this->tipo_pago,
                'metodo_pago' => $this->modalidad_pago === 'mixto' ? 'mixto' : $this->metodo_pago,
                'cupon_id' => $this->appliedCouponId,
                'comentarios' => $comentariosFinal,
            ]);

            // Reducir usos restantes del cupón
            if ($this->appliedCouponId) {
                Cupon::where('id', $this->appliedCouponId)->decrement('usos_restantes');
            }

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
                    'descuento' => 0.00,
                    'total' => $item['total'],
                ]);

                // Descontar Stock
                $inv = Inventario::where('almacen_id', $this->almacen_id)
                    ->where('variacion_id', $item['variacion_id'])
                    ->first();

                if (! $inv || $inv->stock_base < $item['cantidad_base']) {
                    throw new \Exception("Stock insuficiente para el producto {$item['nombre']} en el almacén.");
                }

                $stockAnterior = $inv->stock_base;
                $inv->decrement('stock_base', $item['cantidad_base']);
                $stockPosterior = $inv->stock_base;

                // Registrar en Kardex
                Kardex::create([
                    'almacen_id' => $this->almacen_id,
                    'variacion_id' => $item['variacion_id'],
                    'tipo_transaccion' => 'Salida',
                    'concepto' => 'Venta ' . $this->serie . '-' . str_pad((string) $this->correlativo, 6, '0', STR_PAD_LEFT),
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
            if ($this->sede_id && $this->tipo_documento_id && $this->serie) {
                $serieModel = Serie::where('sede_id', $this->sede_id)
                    ->where('tipo_documento_id', $this->tipo_documento_id)
                    ->where('serie', $this->serie)
                    ->first();

                if ($serieModel && $this->correlativo >= $serieModel->correlativo) {
                    $serieModel->update(['correlativo' => $this->correlativo + 1]);
                }
            }

            // Actualizar totales en la sesión de caja
            if ($sesionActiva) {
                if ($this->modalidad_pago === 'mixto') {
                    $sesionActiva->total_ventas_efectivo += $this->monto_pago_efectivo;
                    $sesionActiva->total_ventas_yape_plin += $this->monto_pago_yape_plin;
                    $sesionActiva->total_ventas_tarjeta += $this->monto_pago_tarjeta;
                    $sesionActiva->total_ventas_transferencia += $this->monto_pago_transferencia;
                    $sesionActiva->monto_cierre_esperado = round((float) $sesionActiva->monto_inicial + (float) $sesionActiva->total_ventas_efectivo + (float) $sesionActiva->total_ingresos_extra - (float) $sesionActiva->total_egresos_gastos, 2);
                    $sesionActiva->save();
                } else {
                    $sesionActiva->recalcularTotales();
                }
            }

            return $venta;
        });

        // Configurar modal de éxito con vuelto
        $this->completedVentaId = $ventaCreada->id;
        $this->completedComprobante = $ventaCreada->tipoDocumento->nombre . ' ' . $ventaCreada->serie . '-' . str_pad($ventaCreada->correlativo, 6, '0', STR_PAD_LEFT);
        $this->completedTotal = $total;
        $this->completedVuelto = ($this->metodo_pago === 'efectivo' && $this->monto_efectivo_recibido > $total) ? round($this->monto_efectivo_recibido - $total, 2) : 0.00;
        $this->showSuccessModal = true;

        Flux::toast(variant: 'success', text: __('Venta completada con éxito.'));
    }

    public function reiniciarVenta(): void
    {
        $this->cart = [];
        $this->cancelarCupon();
        $this->monto_efectivo_recibido = 0.00;
        $this->monto_pago_efectivo = 0.00;
        $this->monto_pago_yape_plin = 0.00;
        $this->monto_pago_tarjeta = 0.00;
        $this->monto_pago_transferencia = 0.00;
        $this->nota = '';
        $this->showSuccessModal = false;
        $this->actualizarSeries();
    }

    #[Computed]
    public function sedes()
    {
        return Sede::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function almacenes()
    {
        if (! $this->sede_id) {
            return collect();
        }

        return Almacen::where('sede_id', $this->sede_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    #[Computed]
    public function listasPrecios()
    {
        return ListaPrecio::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function clientes()
    {
        return User::where('activo', true)->orderBy('name')->get();
    }

    #[Computed]
    public function tipoDocumentos()
    {
        return TipoDocumento::where('activo', true)->orderBy('nombre')->get();
    }

    /**
     * Catalog of active products on warehouse.
     */
    #[Computed]
    public function catalogo()
    {
        if (! $this->almacen_id) {
            return collect();
        }

        return Variacion::with(['producto', 'valores.atributo', 'precios.listaPrecio', 'inventarios' => function ($query) {
            $query->where('almacen_id', $this->almacen_id);
        }])
            ->whereHas('producto', function ($q) {
                $q->where('nombre', 'like', '%' . $this->searchProducto . '%')
                    ->orWhere('sku', 'like', '%' . $this->searchProducto . '%');
            })
            ->where('activo', true)
            ->get();
    }

    #[Computed]
    public function sesionActiva()
    {
        if (! $this->sede_id) {
            return null;
        }

        return CajaSesion::with(['caja', 'user'])
            ->whereHas('caja', fn ($q) => $q->where('sede_id', $this->sede_id))
            ->where('estado', 'abierta')
            ->first();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Registrar Nueva Venta (POS)') }}</flux:heading>
            <flux:subheading>{{ __('Pistoleo de códigos de barra, calculadora de vuelto y cobro rápido.') }}</flux:subheading>
        </div>

        @if($this->sesionActiva)
            <div class="flex items-center gap-2 px-3 py-1.5 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-lg text-xs">
                <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="font-semibold text-emerald-800 dark:text-emerald-300">
                    {{ __('Turno Activo: ') }}{{ $this->sesionActiva->caja->nombre }} ({{ $this->sesionActiva->user->name }})
                </span>
            </div>
        @else
            <div class="flex items-center gap-2 px-3 py-1.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg text-xs text-amber-700 dark:text-amber-300">
                <flux:icon.exclamation-triangle class="size-4 text-amber-600" />
                <span>{{ __('Sin caja abierta en esta sede.') }}</span>
                <a href="{{ route('admin.cajas.index') }}" class="underline font-bold hover:text-amber-900 dark:hover:text-amber-100" wire:navigate>{{ __('Abrir Caja') }}</a>
            </div>
        @endif
    </div>

    <!-- Barra de Pistoleo / Escáner de Código de Barras -->
    <div class="bg-zinc-900 text-white rounded-xl p-4 shadow-md flex flex-col sm:flex-row items-center gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-zinc-800 rounded-lg text-emerald-400">
                <flux:icon.qr-code class="size-6" />
            </div>
            <div>
                <div class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ __('Lector de Código de Barras / SKU') }}</div>
                <div class="text-xxs text-zinc-400">{{ __('Escanea con la pistola láser o digita el SKU y presiona Enter.') }}</div>
            </div>
        </div>

        <div class="flex-1 w-full">
            <input 
                type="text" 
                wire:model="barcode" 
                wire:keydown.enter.prevent="scanBarcode"
                placeholder="{{ __('Escanear código de barras o SKU...') }}" 
                class="w-full bg-zinc-800 border-2 border-emerald-500/60 focus:border-emerald-400 text-white rounded-lg px-4 py-2 text-sm font-mono tracking-wider focus:outline-none placeholder-zinc-500 shadow-inner"
                autofocus
            />
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Panel Izquierdo: Catálogo de Productos y SKUs en Almacén -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm h-fit">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Catálogo del Almacén') }}</flux:heading>
            </div>
            
            <!-- Buscador Catálogo -->
            <flux:input wire:model.live="searchProducto" placeholder="Buscar por SKU o descripción..." icon="magnifying-glass" />

            <!-- Lista de Variaciones -->
            <div class="divide-y divide-zinc-200 dark:divide-zinc-800 max-h-[500px] overflow-y-auto pr-2">
                @forelse($this->catalogo as $var)
                    @php
                        $desc = $var->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
                        $stock = $var->inventarios->firstWhere('almacen_id', $almacen_id)?->stock_base ?? 0;
                        
                        // Buscar precio según la lista global seleccionada o el primer precio
                        $precioObj = $var->precios->firstWhere('lista_precio_id', $lista_precio_id) ?? $var->precios->first();
                        $precioMostrar = $precioObj?->precio ?? 0.00;
                    @endphp
                    <div class="py-3 flex items-center justify-between gap-4 first:pt-0 last:pb-0">
                        <div class="space-y-0.5">
                            <div class="font-semibold text-zinc-900 dark:text-white text-xs">{{ $var->producto->nombre }}</div>
                            <div class="text-xxs text-zinc-500">{{ $desc }} (SKU: {{ $var->sku }})</div>
                            <div class="flex items-center gap-3 mt-1 text-xxs">
                                <span class="font-bold text-zinc-900 dark:text-zinc-300">S/ {{ number_format($precioMostrar, 2) }}</span>
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

        <!-- Panel Derecho: Carrito de Compras y Cobro POS -->
        <div class="lg:col-span-2 space-y-6">
            <form wire:submit.prevent="guardar" class="space-y-6">
                <!-- Tarjeta Datos de Facturación -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm">
                    <flux:heading size="lg">{{ __('Datos de Facturación') }}</flux:heading>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Sede -->
                        <flux:field>
                            <flux:label>{{ __('Sede') }}</flux:label>
                            <flux:select wire:model.live="sede_id">
                                <option value="">{{ __('Seleccione Sede...') }}</option>
                                @foreach($this->sedes as $s)
                                    <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="sede_id" />
                        </flux:field>

                        <!-- Almacén Origen -->
                        <flux:field>
                            <flux:label>{{ __('Almacén Origen') }}</flux:label>
                            <flux:select wire:model.live="almacen_id" :disabled="!$sede_id">
                                <option value="">{{ __('Seleccione Almacén...') }}</option>
                                @foreach($this->almacenes as $alm)
                                    <option value="{{ $alm->id }}">{{ $alm->nombre }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="almacen_id" />
                        </flux:field>

                        <!-- Tarifa / Lista de Precios Global -->
                        <flux:field>
                            <flux:label>{{ __('Tarifa de Precios') }}</flux:label>
                            <flux:select wire:model.live="lista_precio_id">
                                @foreach($this->listasPrecios as $lp)
                                    <option value="{{ $lp->id }}">{{ $lp->nombre }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="lista_precio_id" />
                        </flux:field>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Cliente con Botón de Creación Rápida -->
                        <div class="md:col-span-1">
                            <flux:label class="mb-1.5">{{ __('Cliente') }}</flux:label>
                            <div class="flex gap-2">
                                <flux:select wire:model.live="cliente_id" class="flex-1">
                                    <option value="">{{ __('Seleccionar Cliente...') }}</option>
                                    @foreach($this->clientes as $cli)
                                        <option value="{{ $cli->id }}">{{ $cli->name }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:button icon="plus" variant="primary" wire:click="abrirModalCliente" title="{{ __('Registrar nuevo cliente') }}" />
                            </div>
                            <flux:error name="cliente_id" />
                        </div>

                        <!-- Comprobante -->
                        <flux:field>
                            <flux:label>{{ __('Comprobante') }}</flux:label>
                            <flux:select wire:model.live="tipo_documento_id">
                                <option value="">{{ __('Seleccione Comprobante...') }}</option>
                                @foreach($this->tipoDocumentos as $td)
                                    <option value="{{ $td->id }}">{{ $td->nombre }}</option>
                                @endforeach
                            </flux:select>
                            <flux:error name="tipo_documento_id" />
                        </flux:field>

                        <div class="grid grid-cols-2 gap-4">
                            <!-- Serie -->
                            <flux:field>
                                <flux:label>{{ __('Serie') }}</flux:label>
                                <flux:input wire:model="serie" placeholder="Ej. F001" required />
                                <flux:error name="serie" />
                            </flux:field>

                            <!-- Correlativo -->
                            <flux:field>
                                <flux:label>{{ __('Correlativo') }}</flux:label>
                                <flux:input type="number" wire:model="correlativo" placeholder="Ej. 1" required />
                                <flux:error name="correlativo" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta Carrito -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
                    <flux:heading size="lg">{{ __('Carrito de Venta') }}</flux:heading>

                    @if(empty($cart))
                        <div class="text-center py-12 text-zinc-500 text-sm">
                            <flux:icon.shopping-cart class="size-8 mx-auto mb-2 text-zinc-400" />
                            {{ __('El carrito de compras está vacío. Pistolea un código de barras o agrega productos desde el catálogo.') }}
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold">
                                        <th class="pb-2">{{ __('Producto / SKU') }}</th>
                                        <th class="pb-2">{{ __('Presentación') }}</th>
                                        <th class="pb-2">{{ __('Lista de Precio') }}</th>
                                        <th class="pb-2">{{ __('Cant.') }}</th>
                                        <th class="pb-2">{{ __('Total Base') }}</th>
                                        <th class="pb-2">{{ __('P. Unit.') }}</th>
                                        <th class="pb-2">{{ __('Total') }}</th>
                                        <th class="pb-2 text-center">{{ __('Acción') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach($cart as $index => $item)
                                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                                            <td class="py-3 pr-2">
                                                <div class="font-medium text-zinc-900 dark:text-white">{{ $item['nombre'] }}</div>
                                                <div class="text-xxs text-zinc-500 font-mono">{{ $item['sku'] }}</div>
                                            </td>

                                            <td class="py-3 pr-2">
                                                <select wire:model.live="cart.{{ $index }}.unidad_medida_id" class="text-xs py-1 px-2 border rounded-md dark:bg-zinc-800 dark:border-zinc-700">
                                                    @foreach($item['empaques'] as $emp)
                                                        <option value="{{ $emp['unidad_medida_id'] }}">{{ $emp['nombre'] }} (x{{ $emp['factor'] }})</option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            <td class="py-3 pr-2">
                                                <select wire:model.live="cart.{{ $index }}.lista_precio_id" class="text-xs py-1 px-2 border rounded-md dark:bg-zinc-800 dark:border-zinc-700">
                                                    @foreach($item['precios'] as $pr)
                                                        <option value="{{ $pr['lista_precio_id'] }}">{{ $pr['nombre'] }} (S/ {{ number_format($pr['precio'], 2) }})</option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            <td class="py-3 pr-2 w-20">
                                                <input type="number" min="1" wire:model.live.debounce.300ms="cart.{{ $index }}.cantidad" class="w-16 text-xs py-1 px-2 border rounded-md dark:bg-zinc-800 dark:border-zinc-700 text-center font-bold" />
                                            </td>

                                            <td class="py-3 pr-2 text-zinc-500 font-medium">
                                                {{ $item['cantidad_base'] }} uds.
                                            </td>

                                            <td class="py-3 pr-2 font-semibold text-zinc-800 dark:text-zinc-200">
                                                S/ {{ number_format($item['precio_unitario'], 2) }}
                                            </td>

                                            <td class="py-3 pr-2 font-bold text-zinc-900 dark:text-white">
                                                S/ {{ number_format($item['total'], 2) }}
                                            </td>

                                            <td class="py-3 text-center">
                                                <flux:button variant="ghost" icon="trash" size="xs" wire:click.prevent="removeFromCart({{ $index }})" class="text-rose-600 hover:text-rose-700" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- Tarjeta Métodos de Pago, Calculadora de Vuelto y Totales -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Sección Izquierda: Modalidad de Pago y Calculadora de Vuelto -->
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <flux:heading size="lg">{{ __('Forma de Pago') }}</flux:heading>
                            <div class="flex items-center gap-1 bg-zinc-100 dark:bg-zinc-800 p-1 rounded-lg text-xs">
                                <button type="button" wire:click="$set('modalidad_pago', 'simple')" class="px-3 py-1 rounded-md font-medium transition {{ $modalidad_pago === 'simple' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500' }}">
                                    {{ __('Pago Simple') }}
                                </button>
                                <button type="button" wire:click="$set('modalidad_pago', 'mixto')" class="px-3 py-1 rounded-md font-medium transition {{ $modalidad_pago === 'mixto' ? 'bg-white dark:bg-zinc-700 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500' }}">
                                    {{ __('Pago Mixto') }}
                                </button>
                            </div>
                        </div>

                        @php
                            $subtotal = collect($cart)->sum('total');
                            $total = max(0, $subtotal - $couponDiscountAmount);
                        @endphp

                        @if($modalidad_pago === 'simple')
                            <!-- Método Único -->
                            <flux:field>
                                <flux:label>{{ __('Método de Pago') }}</flux:label>
                                <flux:select wire:model.live="metodo_pago">
                                    <option value="efectivo">💵 {{ __('Efectivo') }}</option>
                                    <option value="yape_plin">📱 {{ __('Yape / Plin') }}</option>
                                    <option value="tarjeta">💳 {{ __('Tarjeta de Débito / Crédito') }}</option>
                                    <option value="transferencia">🏦 {{ __('Transferencia Bancaria') }}</option>
                                </flux:select>
                            </flux:field>

                            <!-- Calculadora de Vuelto para Efectivo -->
                            @if($metodo_pago === 'efectivo')
                                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl space-y-3">
                                    <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wide">
                                        {{ __('Calculadora de Vuelto') }}
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 items-center">
                                        <flux:field>
                                            <flux:label>{{ __('Pagó con (S/)') }}</flux:label>
                                            <flux:input type="number" step="0.50" min="0" wire:model.live="monto_efectivo_recibido" class="font-bold text-base" />
                                        </flux:field>

                                        <!-- Vuelto Calculado -->
                                        @php
                                            $montoRec = (float) $monto_efectivo_recibido;
                                            $vuelto = $montoRec >= $total ? round($montoRec - $total, 2) : 0;
                                            $falta = $montoRec > 0 && $montoRec < $total ? round($total - $montoRec, 2) : 0;
                                        @endphp
                                        <div class="p-3 rounded-lg border {{ $montoRec >= $total && $total > 0 ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-700' : ($falta > 0 ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-300 dark:border-rose-700' : 'bg-zinc-100 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700') }} text-center">
                                            @if($montoRec >= $total && $total > 0)
                                                <div class="text-xxs uppercase font-bold text-emerald-800 dark:text-emerald-300">{{ __('Vuelto a Entregar') }}</div>
                                                <div class="text-xl font-black text-emerald-700 dark:text-emerald-300">S/ {{ number_format($vuelto, 2) }}</div>
                                            @elseif($falta > 0)
                                                <div class="text-xxs uppercase font-bold text-rose-800 dark:text-rose-300">{{ __('Falta Abonar') }}</div>
                                                <div class="text-lg font-bold text-rose-700 dark:text-rose-300">- S/ {{ number_format($falta, 2) }}</div>
                                            @else
                                                <div class="text-xxs text-zinc-500 uppercase">{{ __('Vuelto') }}</div>
                                                <div class="text-base font-bold text-zinc-400">S/ 0.00</div>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Botones Rápidos de Billetes -->
                                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                        <button type="button" wire:click="pagarMontoExacto" class="px-2.5 py-1 text-xs font-semibold bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-600 transition">
                                            {{ __('Exacto') }}
                                        </button>
                                        <button type="button" wire:click="establecerMontoRecibido(10)" class="px-2.5 py-1 text-xs font-semibold bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-600 transition">
                                            S/ 10
                                        </button>
                                        <button type="button" wire:click="establecerMontoRecibido(20)" class="px-2.5 py-1 text-xs font-semibold bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-600 transition">
                                            S/ 20
                                        </button>
                                        <button type="button" wire:click="establecerMontoRecibido(50)" class="px-2.5 py-1 text-xs font-semibold bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-600 transition">
                                            S/ 50
                                        </button>
                                        <button type="button" wire:click="establecerMontoRecibido(100)" class="px-2.5 py-1 text-xs font-semibold bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-600 transition">
                                            S/ 100
                                        </button>
                                        <button type="button" wire:click="establecerMontoRecibido(200)" class="px-2.5 py-1 text-xs font-semibold bg-white dark:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-600 transition">
                                            S/ 200
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @else
                            <!-- Pago Mixto / Fraccionado -->
                            <div class="space-y-3 p-4 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                                <div class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase">
                                    {{ __('Desglose de Pago Fraccionado') }}
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <flux:field>
                                        <flux:label class="text-xs">💵 {{ __('Efectivo (S/)') }}</flux:label>
                                        <flux:input type="number" step="0.50" min="0" wire:model.live="monto_pago_efectivo" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label class="text-xs">📱 {{ __('Yape / Plin (S/)') }}</flux:label>
                                        <flux:input type="number" step="0.50" min="0" wire:model.live="monto_pago_yape_plin" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label class="text-xs">💳 {{ __('Tarjeta (S/)') }}</flux:label>
                                        <flux:input type="number" step="0.50" min="0" wire:model.live="monto_pago_tarjeta" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label class="text-xs">🏦 {{ __('Transferencia (S/)') }}</flux:label>
                                        <flux:input type="number" step="0.50" min="0" wire:model.live="monto_pago_transferencia" />
                                    </flux:field>
                                </div>

                                @php
                                    $sumaMixta = (float) ($monto_pago_efectivo + $monto_pago_yape_plin + $monto_pago_tarjeta + $monto_pago_transferencia);
                                    $difMixta = round($total - $sumaMixta, 2);
                                @endphp

                                <div class="p-2.5 rounded-lg border text-xs flex justify-between items-center {{ $difMixta == 0 && $total > 0 ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 text-emerald-800 dark:text-emerald-300 font-bold' : 'bg-amber-50 dark:bg-amber-950/40 border-amber-300 text-amber-800 dark:text-amber-300' }}">
                                    <span>{{ __('Suma ingresada: S/ ') . number_format($sumaMixta, 2) }}</span>
                                    @if($difMixta == 0 && $total > 0)
                                        <span>✅ {{ __('Cuadrado') }}</span>
                                    @elseif($difMixta > 0)
                                        <span>⚠️ {{ __('Faltan: S/ ') . number_format($difMixta, 2) }}</span>
                                    @else
                                        <span>🔴 {{ __('Excedente: S/ ') . number_format(abs($difMixta), 2) }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Cupones y Notas -->
                        <div class="space-y-3 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:label>{{ __('Cupón de Descuento') }}</flux:label>
                            <div class="flex gap-2">
                                <flux:input wire:model="couponCode" placeholder="Ej. VERANO2026" class="uppercase" />
                                @if($appliedCouponId)
                                    <flux:button variant="danger" wire:click.prevent="cancelarCupon">{{ __('Quitar') }}</flux:button>
                                @else
                                    <flux:button variant="primary" wire:click.prevent="aplicarCupon">{{ __('Aplicar') }}</flux:button>
                                @endif
                            </div>

                            <flux:field>
                                <flux:label>{{ __('Nota de Venta / Observación') }}</flux:label>
                                <flux:textarea wire:model="nota" rows="2" placeholder="Opcional..." />
                            </flux:field>
                        </div>
                    </div>

                    <!-- Sección Derecha: Resumen de Totales y Cobro Final -->
                    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm flex flex-col justify-between">
                        <flux:heading size="lg">{{ __('Resumen de Totales') }}</flux:heading>

                        <div class="space-y-3 border-t border-b border-zinc-200 dark:border-zinc-800 py-4 text-sm">
                            <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                                <span>{{ __('Subtotal') }}</span>
                                <span class="font-medium text-zinc-900 dark:text-white">S/ {{ number_format($subtotal, 2) }}</span>
                            </div>

                            @if($couponDiscountAmount > 0)
                                <div class="flex justify-between text-emerald-600 font-medium">
                                    <span>{{ __('Descuento Cupón') }}</span>
                                    <span>- S/ {{ number_format($couponDiscountAmount, 2) }}</span>
                                </div>
                            @endif

                            <div class="flex justify-between text-lg font-bold text-zinc-900 dark:text-white pt-3 border-t border-zinc-200 dark:border-zinc-800">
                                <span>{{ __('Total a Cobrar') }}</span>
                                <span class="text-2xl font-black text-emerald-600">S/ {{ number_format($total, 2) }}</span>
                            </div>
                        </div>

                        <div class="pt-2">
                            <flux:button variant="primary" type="submit" class="w-full !py-3 !text-lg !font-bold" :disabled="empty($cart)">
                                💳 {{ __('Completar Venta y Cobrar') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 1: Registro Rápido de Cliente -->
    <flux:modal name="modal-nuevo-cliente" class="md:w-96 space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Nuevo Cliente Rápido') }}</flux:heading>
            <flux:subheading>{{ __('Ingresa los datos para registrar y seleccionar al cliente de inmediato.') }}</flux:subheading>
        </div>

        <form wire:submit.prevent="guardarClienteRapido" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>{{ __('Tipo de Persona') }}</flux:label>
                    <flux:select wire:model.live="modal_tipo_persona">
                        <option value="natural">{{ __('Persona Natural') }}</option>
                        <option value="juridica">{{ __('Persona Jurídica') }}</option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Tipo Cliente') }}</flux:label>
                    <flux:select wire:model="modal_tipo_cliente">
                        <option value="minorista">{{ __('Minorista') }}</option>
                        <option value="mayorista">{{ __('Mayorista') }}</option>
                        <option value="emprendedor">{{ __('Emprendedor') }}</option>
                    </flux:select>
                </flux:field>
            </div>

            @if($modal_tipo_persona === 'natural')
                <flux:field>
                    <flux:label>{{ __('DNI (8 dígitos)') }}</flux:label>
                    <flux:input wire:model="modal_dni" maxlength="8" placeholder="12345678" required />
                    <flux:error name="modal_dni" />
                </flux:field>
            @else
                <flux:field>
                    <flux:label>{{ __('RUC (11 dígitos)') }}</flux:label>
                    <flux:input wire:model="modal_ruc" maxlength="11" placeholder="20123456789" required />
                    <flux:error name="modal_ruc" />
                </flux:field>
            @endif

            <flux:field>
                <flux:label>{{ $modal_tipo_persona === 'natural' ? __('Nombre Completo') : __('Razón Social') }}</flux:label>
                <flux:input wire:model="modal_nombre" placeholder="Ej. Juan Pérez / Inversiones SAC" required />
                <flux:error name="modal_nombre" />
            </flux:field>

            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>{{ __('Teléfono') }}</flux:label>
                    <flux:input wire:model="modal_telefono" placeholder="999888777" />
                    <flux:error name="modal_telefono" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Tarifa Asignada') }}</flux:label>
                    <flux:select wire:model="modal_lista_precio_id">
                        @foreach($this->listasPrecios as $lp)
                            <option value="{{ $lp->id }}">{{ $lp->nombre }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="modal_lista_precio_id" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Correo Electrónico (Opcional)') }}</flux:label>
                <flux:input type="email" wire:model="modal_email" placeholder="cliente@ejemplo.com" />
                <flux:error name="modal_email" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" type="button" x-on:click="$dispatch('close-modal', 'modal-nuevo-cliente')">
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Guardar y Seleccionar') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- MODAL 2: Venta Completada con Éxito y Ticket -->
    @if($showSuccessModal && $completedVentaId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 w-full max-w-md rounded-2xl p-6 shadow-2xl space-y-6 text-center animate-in fade-in zoom-in-95 duration-200">
                
                <div class="size-16 bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto shadow-inner">
                    <flux:icon.check-circle class="size-10" />
                </div>

                <div>
                    <flux:heading size="xl" class="text-emerald-600">{{ __('¡Venta Registrada!') }}</flux:heading>
                    <div class="text-sm font-semibold text-zinc-700 dark:text-zinc-300 mt-1">{{ $completedComprobante }}</div>
                    <div class="text-xs text-zinc-500">{{ __('Stock descontado y registrado en Kardex.') }}</div>
                </div>

                <!-- Caja de Vuelto si hubo efectivo -->
                @if($completedVuelto > 0)
                    <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-xl space-y-1">
                        <div class="text-xs uppercase font-bold text-emerald-800 dark:text-emerald-300">{{ __('Vuelto a Entregar al Cliente') }}</div>
                        <div class="text-3xl font-black text-emerald-700 dark:text-emerald-300">
                            S/ {{ number_format($completedVuelto, 2) }}
                        </div>
                    </div>
                @endif

                <div class="text-sm font-bold text-zinc-900 dark:text-white">
                    {{ __('Total Cobrado: ') }} S/ {{ number_format($completedTotal, 2) }}
                </div>

                <!-- Acciones de Ticket e Impresión -->
                <div class="space-y-2 pt-2">
                    <flux:button variant="primary" class="w-full !py-2.5 !text-base" icon="printer" :href="route('admin.ventas.ticket', $completedVentaId)" target="_blank">
                        🖨️ {{ __('Imprimir Ticket Térmico (80mm)') }}
                    </flux:button>

                    <flux:button variant="ghost" class="w-full" wire:click="reiniciarVenta">
                        ➕ {{ __('Iniciar Siguiente Venta') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
