<?php

use App\Models\Producto;
use App\Models\Variacion;
use App\Models\Descuento;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Detalle de Mochila'), Layout('layouts.publico')] class extends Component {
    public Producto $producto;
    public string $slug;

    // Track selected variation options
    public array $selectedAttributes = [];
    public int $quantity = 1;

    /**
     * Mount the component.
     */
    public function mount(Producto $producto, string $slug): void
    {
        $this->producto = $producto->load([
            'categoria', 
            'marca', 
            'descuentos' => fn($q) => $q->where('activo', true)
                ->where(fn($sq) => $sq->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', now()))
                ->where(fn($sq) => $sq->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now())),
            'variacions.precios.listaPrecio', 
            'variacions.valores.atributo',
            'variacions.inventarios'
        ]);

        $this->slug = $slug;

        // Auto-select first options of attributes
        $available = $this->atributosDisponibles;
        foreach ($available as $name => $vals) {
            if (!empty($vals)) {
                $this->selectedAttributes[$name] = reset($vals);
            }
        }
    }

    /**
     * Get structured list of unique attributes of this product.
     */
    #[Computed]
    public function atributosDisponibles(): array
    {
        $attributes = [];
        foreach ($this->producto->variacions as $v) {
            foreach ($v->valores as $val) {
                $attributes[$val->atributo->nombre][] = $val->valor;
            }
        }
        
        foreach ($attributes as $name => $values) {
            $attributes[$name] = array_unique($values);
        }

        return $attributes;
    }

    /**
     * Get current selected variation based on attributes choice.
     */
    #[Computed]
    public function selectedVariation(): ?Variacion
    {
        return $this->producto->variacions->first(function ($v) {
            foreach ($this->selectedAttributes as $attrName => $selectedVal) {
                $hasVal = $v->valores->contains(function ($val) use ($attrName, $selectedVal) {
                    return $val->atributo->nombre === $attrName && $val->valor === $selectedVal;
                });
                if (!$hasVal) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Select an attribute option.
     */
    public function selectOption(string $name, string $value): void
    {
        $this->selectedAttributes[$name] = $value;
        $this->quantity = 1; // Reset quantity on variation change
    }

    /**
     * Add selected variation to e-commerce session cart.
     */
    public function addToCart(): void
    {
        $variation = $this->selectedVariation;
        if (!$variation) {
            Flux::toast(variant: 'danger', text: __('Seleccione una variación válida.'));
            return;
        }

        // Check stock
        $stock = (int) $variation->inventarios->sum('stock_base');
        if ($stock <= 0) {
            Flux::toast(variant: 'danger', text: __('Esta mochila no cuenta con stock disponible.'));
            return;
        }

        if ($this->quantity > $stock) {
            Flux::toast(variant: 'danger', text: __('La cantidad solicitada supera el stock disponible.'));
            return;
        }

        // Calculate pricing
        $basePrice = (float) ($variation->precios->firstWhere('listaPrecio.nombre', 'Precio Menor')?->precio ?? 0.00);
        $activeDiscount = $this->producto->descuentos->first();
        if ($activeDiscount) {
            $pct = (int) $activeDiscount->porcentaje_descuento;
            $finalPrice = round($basePrice * (1 - $pct / 100), 2);
        } else {
            $finalPrice = $basePrice;
        }

        // Get details
        $descAttr = $variation->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');

        $cart = session()->get('public_cart', []);
        
        $key = 'var_' . $variation->id;

        if (isset($cart[$key])) {
            $cart[$key]['cantidad'] += $this->quantity;
            // Cap at stock
            if ($cart[$key]['cantidad'] > $stock) {
                $cart[$key]['cantidad'] = $stock;
            }
        } else {
            $cart[$key] = [
                'variacion_id' => $variation->id,
                'nombre' => $this->producto->nombre,
                'sku' => $variation->sku,
                'detalles' => $descAttr,
                'cantidad' => $this->quantity,
                'precio' => $finalPrice,
            ];
        }

        session()->put('public_cart', $cart);

        Flux::toast(variant: 'success', text: __('¡Mochila agregada a tu bolsa de compras!'));
        
        // Redirect back to refresh cart count in layout
        $this->redirect(route('producto.detalle', ['producto' => $this->producto->id, 'slug' => $this->slug]), navigate: true);
    }
}; ?>

<div class="max-w-6xl mx-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-8 shadow-sm">
    <!-- Volver al catálogo link -->
    <div class="mb-6">
        <a href="{{ route('catalogo') }}" wire:navigate class="inline-flex items-center text-xs font-semibold text-zinc-500 hover:text-black dark:hover:text-white transition-colors gap-1.5">
            <flux:icon name="arrow-left" class="size-4" />
            {{ __('Volver a la tienda') }}
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
        <!-- Columna Izquierda: Galería de imágenes (Placeholder) -->
        <div class="space-y-6">
            <div class="aspect-square w-full bg-zinc-100 dark:bg-zinc-950 rounded-2xl flex items-center justify-center border border-zinc-200/50 dark:border-zinc-800 relative overflow-hidden group">
                <flux:icon name="archive-box" class="size-36 text-zinc-300 dark:text-zinc-700" />
                
                @if($this->producto->descuentos->isNotEmpty())
                    <div class="absolute top-6 left-6 bg-rose-600 text-white font-extrabold text-xs px-3.5 py-1.5 rounded-full shadow-md">
                        -{{ $this->producto->descuentos->first()->porcentaje_descuento }}% {{ __('Dcto.') }}
                    </div>
                @endif
            </div>

            <!-- Miniaturas -->
            <div class="grid grid-cols-4 gap-4">
                <div class="aspect-square bg-zinc-100 dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-xl flex items-center justify-center cursor-pointer">
                    <flux:icon name="archive-box" class="size-8 text-zinc-400" />
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Panel de compra y variaciones -->
        <div class="space-y-6">
            <!-- Marca y Categoría -->
            <div class="flex items-center gap-2 text-xxs font-extrabold tracking-wider text-zinc-400 uppercase">
                <span>{{ $producto->marca->nombre }}</span>
                <span>•</span>
                <span>{{ $producto->categoria->nombre }}</span>
            </div>

            <!-- Nombre -->
            <flux:heading size="xl" class="font-extrabold tracking-tight text-zinc-900 dark:text-white">
                {{ $producto->nombre }}
            </flux:heading>

            <!-- Descripción -->
            <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                {{ $producto->descripcion ?: __('Esta mochila Logan ofrece materiales de alta resistencia, compartimentos acolchados y costuras reforzadas ideales para uso diario o escolar.') }}
            </p>

            <hr class="border-zinc-200 dark:border-zinc-800" />

            <!-- Selectores de Variación -->
            @if(!empty($this->atributosDisponibles))
                <div class="space-y-4">
                    @foreach($this->atributosDisponibles as $name => $values)
                        <div class="space-y-2">
                            <flux:text size="sm" class="font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">{{ $name }}</flux:text>
                            
                            <div class="flex flex-wrap gap-2">
                                @foreach($values as $val)
                                    @php
                                        $isSelected = ($selectedAttributes[$name] ?? '') === $val;
                                    @endphp
                                    @if(strtolower($name) === 'color')
                                        @php
                                            $colMap = [
                                                'rojo' => 'bg-red-500',
                                                'azul' => 'bg-blue-500',
                                                'verde' => 'bg-green-500',
                                                'amarillo' => 'bg-yellow-400',
                                                'negro' => 'bg-black border border-zinc-700',
                                                'blanco' => 'bg-white border border-zinc-300',
                                                'gris' => 'bg-zinc-400',
                                                'celeste' => 'bg-sky-400',
                                                'rosado' => 'bg-pink-400',
                                                'naranja' => 'bg-orange-500',
                                            ];
                                            $colClass = $colMap[strtolower(trim($val))] ?? 'bg-gradient-to-r from-zinc-300 to-zinc-500';
                                        @endphp
                                        <button wire:click.prevent="selectOption('{{ $name }}', '{{ $val }}')" class="size-8 rounded-full {{ $colClass }} relative focus:outline-none transition-all {{ $isSelected ? 'ring-4 ring-offset-2 ring-black dark:ring-white scale-110 shadow-lg' : 'hover:scale-105' }}" title="{{ $val }}">
                                            @if($isSelected)
                                                <span class="absolute inset-0 flex items-center justify-center">
                                                    <flux:icon name="check" class="size-4 {{ strtolower($val) === 'blanco' ? 'text-black' : 'text-white' }} font-bold" />
                                                </span>
                                            @endif
                                        </button>
                                    @else
                                        <!-- Botones estándar para otros atributos (Tamaño, Material, etc.) -->
                                        <button wire:click.prevent="selectOption('{{ $name }}', '{{ $val }}')" class="px-4 py-2 rounded-lg text-xs font-bold transition-all border {{ $isSelected ? 'bg-black text-white border-black dark:bg-white dark:text-black dark:border-white shadow-md' : 'bg-white dark:bg-zinc-950 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50' }}">
                                            {{ $val }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <hr class="border-zinc-200 dark:border-zinc-800" />

            <!-- Precios y Stock -->
            @php
                $var = $this->selectedVariation;
                $stock = $var ? (int) $var->inventarios->sum('stock_base') : 0;
                
                $basePrice = 0.00;
                if ($var) {
                    $basePrice = (float) ($var->precios->firstWhere('listaPrecio.nombre', 'Precio Menor')?->precio ?? 0.00);
                } else {
                    $firstVar = $producto->variacions->first();
                    if ($firstVar) {
                        $basePrice = (float) ($firstVar->precios->firstWhere('listaPrecio.nombre', 'Precio Menor')?->precio ?? 0.00);
                    }
                }

                $activeDiscount = $producto->descuentos->first();
                if ($activeDiscount) {
                    $pct = (int) $activeDiscount->porcentaje_descuento;
                    $finalPrice = round($basePrice * (1 - $pct / 100), 2);
                } else {
                    $finalPrice = $basePrice;
                }
            @endphp

            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-zinc-500 font-semibold">{{ __('Precio Internet') }}</flux:text>
                    <div class="flex items-baseline gap-3 mt-1">
                        <span class="text-3xl font-extrabold text-zinc-900 dark:text-white">S/ {{ number_format($finalPrice, 2) }}</span>
                        
                        @if($activeDiscount)
                            <span class="text-sm text-zinc-400 line-through">S/ {{ number_format($basePrice, 2) }}</span>
                        @endif
                    </div>
                </div>

                <div class="text-right">
                    <span class="text-xs font-semibold {{ $stock > 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        @if($stock > 0)
                            {{ __('Stock Disponible: ') }}{{ $stock }}{{ __(' unidades') }}
                        @else
                            {{ __('Agotado Temporalmente') }}
                        @endif
                    </span>
                </div>
            </div>

            <!-- Cantidad y Botón de Compra -->
            @if($stock > 0)
                <div class="flex items-center gap-4 pt-2">
                    <!-- Selector de Cantidad -->
                    <div class="flex items-center border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden bg-zinc-50 dark:bg-zinc-950 h-11">
                        <button type="button" wire:click.prevent="$set('quantity', {{ max(1, $quantity - 1) }})" class="px-3 text-zinc-500 hover:text-black dark:hover:text-white font-bold">-</button>
                        <input type="text" readonly value="{{ $quantity }}" class="w-10 text-center font-bold text-sm bg-transparent border-none focus:outline-none" />
                        <button type="button" wire:click.prevent="$set('quantity', {{ min($stock, $quantity + 1) }})" class="px-3 text-zinc-500 hover:text-black dark:hover:text-white font-bold">+</button>
                    </div>

                    <!-- Botón Agregar al Carrito -->
                    <flux:button variant="primary" wire:click.prevent="addToCart" class="flex-1 font-bold h-11 rounded-xl" icon="shopping-bag">
                        {{ __('Agregar al Carro') }}
                    </flux:button>
                </div>
            @else
                <div class="pt-2">
                    <flux:button disabled class="w-full h-11 rounded-xl">
                        {{ __('No disponible') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </div>
</div>
