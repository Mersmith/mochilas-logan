<?php

namespace App\Services;

use App\Models\Carrito;
use App\Models\Variacion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Obtener o crear la instancia del carrito actual (para usuario o invitado).
     */
    public static function getOrCreateCart(): Carrito
    {
        $clienteId = Auth::user()?->cliente?->id;
        $sessionId = Session::getId();

        if ($clienteId) {
            $cart = Carrito::firstOrCreate(
                ['cliente_id' => $clienteId],
                ['ultimo_acceso_at' => now()]
            );

            // Si el usuario tenía un carrito previo de invitado en esta sesión, fusionarlo
            if ($sessionId) {
                $guestCart = Carrito::where('session_id', $sessionId)->whereNull('cliente_id')->first();
                if ($guestCart && $guestCart->id !== $cart->id) {
                    self::mergeCarts($guestCart, $cart);
                }
            }

            $cart->update(['ultimo_acceso_at' => now(), 'session_id' => $sessionId]);

            return $cart;
        }

        $cart = Carrito::firstOrCreate(
            ['session_id' => $sessionId, 'cliente_id' => null],
            ['ultimo_acceso_at' => now()]
        );

        $cart->update(['ultimo_acceso_at' => now()]);

        return $cart;
    }

    /**
     * Fusionar carrito de invitado hacia el carrito del usuario.
     */
    protected static function mergeCarts(Carrito $guestCart, Carrito $userCart): void
    {
        foreach ($guestCart->items as $item) {
            $existing = $userCart->items()->where('variacion_id', $item->variacion_id)->first();
            if ($existing) {
                $existing->update(['cantidad' => $existing->cantidad + $item->cantidad]);
            } else {
                $item->update(['carrito_id' => $userCart->id]);
            }
        }
        $guestCart->delete();
    }

    /**
     * Agregar o actualizar cantidad de una variación en el carrito.
     */
    public static function add(int $variacionId, int $cantidad = 1): array
    {
        $variacion = Variacion::with(['producto', 'inventarios'])->find($variacionId);

        if (! $variacion || ! $variacion->producto || ! $variacion->producto->activo || $variacion->producto->trashed()) {
            return ['success' => false, 'message' => __('Este producto ya no está disponible.')];
        }

        $stock = (int) $variacion->inventarios->sum('stock_base');
        if ($stock <= 0) {
            return ['success' => false, 'message' => __('Esta mochila no cuenta con stock disponible.')];
        }

        $cart = self::getOrCreateCart();
        $item = $cart->items()->where('variacion_id', $variacionId)->first();

        $nuevaCantidad = $item ? ($item->cantidad + $cantidad) : $cantidad;

        if ($nuevaCantidad > $stock) {
            $nuevaCantidad = $stock;
        }

        if ($item) {
            $item->update(['cantidad' => $nuevaCantidad]);
        } else {
            $cart->items()->create([
                'variacion_id' => $variacionId,
                'cantidad' => $nuevaCantidad,
            ]);
        }

        return ['success' => true, 'message' => __('Producto agregado a tu bolsa de compras.')];
    }

    /**
     * Actualizar la cantidad exacta de un ítem.
     */
    public static function updateQuantity(int $variacionId, int $cantidad): void
    {
        $cart = self::getOrCreateCart();
        $item = $cart->items()->where('variacion_id', $variacionId)->first();

        if (! $item) {
            return;
        }

        if ($cantidad <= 0) {
            $item->delete();

            return;
        }

        $variacion = Variacion::with('inventarios')->find($variacionId);
        $stock = $variacion ? (int) $variacion->inventarios->sum('stock_base') : 0;

        $cantFinal = min($cantidad, max(1, $stock));
        $item->update(['cantidad' => $cantFinal]);
    }

    /**
     * Remover un ítem del carrito.
     */
    public static function remove(int $variacionId): void
    {
        $cart = self::getOrCreateCart();
        $cart->items()->where('variacion_id', $variacionId)->delete();
    }

    /**
     * Vaciar el carrito.
     */
    public static function clear(): void
    {
        $cart = self::getOrCreateCart();
        $cart->items()->delete();
    }

    /**
     * Obtener los ítems validados en tiempo real.
     * Si un producto fue dado de baja o su stock cambió, se auto-ajusta y limpia.
     */
    public static function getValidatedItems(): array
    {
        $cart = self::getOrCreateCart();
        $cartItems = $cart->items()->with([
            'variacion.producto.marca',
            'variacion.producto.categoria',
            'variacion.producto.descuentos' => fn ($q) => $q->where('activo', true)
                ->where(fn ($sq) => $sq->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', now()))
                ->where(fn ($sq) => $sq->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now())),
            'variacion.valores.atributo',
            'variacion.precios.listaPrecio',
            'variacion.inventarios',
        ])->get();

        $items = [];
        $nombreListaPrecio = 'Precio Menor';
        if (Auth::check() && Auth::user()->cliente && Auth::user()->cliente->tipo_cliente === 'mayorista') {
            $nombreListaPrecio = Auth::user()->cliente->listaPrecio->nombre ?? 'Precio Mayor';
        }

        foreach ($cartItems as $cItem) {
            $variacion = $cItem->variacion;

            // Si la variación o producto ya no existe, está inactivo o borrado (soft delete)
            if (! $variacion || ! $variacion->producto || ! $variacion->producto->activo || $variacion->producto->trashed()) {
                $cItem->delete();

                continue;
            }

            $stock = (int) $variacion->inventarios->sum('stock_base');

            // Si ya no tiene stock, lo dejamos con cantidad 0 o lo eliminamos según preferencia
            if ($stock <= 0) {
                $cItem->delete();

                continue;
            }

            // Auto-ajuste si el cliente tenía más del stock actual
            $cantidad = min($cItem->cantidad, $stock);
            if ($cantidad !== $cItem->cantidad) {
                $cItem->update(['cantidad' => $cantidad]);
            }

            // Precio dinámico actual
            $basePrice = (float) ($variacion->precios->firstWhere('listaPrecio.nombre', $nombreListaPrecio)?->precio ?? 0.00);

            $activeDiscount = $variacion->producto->descuentos->first();
            $pct = $activeDiscount ? (int) $activeDiscount->porcentaje_descuento : 0;
            $finalPrice = $pct > 0 ? round($basePrice * (1 - $pct / 100), 2) : $basePrice;

            $descAttr = $variacion->valores->map(fn ($v) => $v->atributo->nombre.': '.$v->valor)->implode(' | ');

            $imagenUrl = $variacion->producto->getFirstMediaUrl('imagen_principal', 'thumb')
                ?: $variacion->producto->getFirstMediaUrl('imagen_principal')
                ?: $variacion->producto->getFirstMediaUrl('galeria', 'thumb')
                ?: $variacion->producto->getFirstMediaUrl('galeria');

            $items['var_'.$variacion->id] = [
                'variacion_id' => $variacion->id,
                'producto_id' => $variacion->producto->id,
                'slug' => Str::slug($variacion->producto->nombre),
                'nombre' => $variacion->producto->nombre,
                'marca' => $variacion->producto->marca->nombre ?? 'Logan',
                'sku' => $variacion->sku,
                'detalles' => $descAttr,
                'imagen_url' => $imagenUrl,
                'cantidad' => $cantidad,
                'stock' => $stock,
                'precio_base' => $basePrice,
                'precio_final' => $finalPrice,
                'porcentaje_descuento' => $pct,
                'subtotal' => round($finalPrice * $cantidad, 2),
            ];
        }

        return $items;
    }

    /**
     * Conteo rápido de unidades en el carrito para el icono del header.
     */
    public static function count(): int
    {
        $cart = self::getOrCreateCart();

        return (int) $cart->items()->sum('cantidad');
    }
}
