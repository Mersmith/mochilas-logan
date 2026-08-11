<?php

use App\Models\Producto;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Descuento;
use App\Models\Atributo;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;

new #[Title('Catálogo de Mochilas'), Layout('layouts.publico')] class extends Component {
    use WithPagination;
    
    // Filters tracked in URL query string
    #[Url(as: 'categoria')]
    public ?int $selectedCategoria = null;

    #[Url(as: 'marca')]
    public array $selectedMarcas = [];

    #[Url(as: 'atributos')]
    public array $selectedAtributos = [];

    #[Url(as: 'precio_min')]
    public ?float $precioMin = null;

    #[Url(as: 'precio_max')]
    public ?float $precioMax = null;

    #[Url(as: 'ordenar')]
    public string $ordenarPor = 'recomendados';

    // Search query
    #[Url(as: 'q')]
    public string $search = '';

    /**
     * Clear all active filters.
     */
    public function limpiarFiltros(): void
    {
        $this->selectedCategoria = null;
        $this->selectedMarcas = [];
        $this->selectedAtributos = [];
        $this->precioMin = null;
        $this->precioMax = null;
        $this->ordenarPor = 'recomendados';
        $this->search = '';
        $this->resetPage();
    }

    public function updating($property)
    {
        if (in_array($property, ['selectedCategoria', 'selectedMarcas', 'selectedAtributos', 'precioMin', 'precioMax', 'ordenarPor', 'search'])) {
            $this->resetPage();
        }
    }

    /**
     * Computed categories list with count.
     */
    #[Computed]
    public function categorias()
    {
        return Categoria::where('activo', true)
            ->withCount(['productos' => fn($q) => $q->where('activo', true)])
            ->get();
    }

    /**
     * Computed brands list with count.
     */
    #[Computed]
    public function marcas()
    {
        return Marca::where('activo', true)
            ->withCount(['productos' => fn($q) => $q->where('activo', true)])
            ->get();
    }

    /**
     * Computed dynamic attributes and values.
     */
    #[Computed]
    public function atributosFiltro()
    {
        return Atributo::with(['valores' => function($q) {
            $q->whereHas('variacions.producto', fn($pq) => $pq->where('activo', true));
        }])->where('activo', true)->get();
    }

    /**
     * Computed products listing with filters and pricing logic applied.
     */
    #[Computed]
    public function productos()
    {
        $query = Producto::with([
            'categoria', 
            'marca', 
            'descuentos' => fn($q) => $q->where('activo', true)
                ->where(fn($sq) => $sq->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', now()))
                ->where(fn($sq) => $sq->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now())),
            'variacions.precios.listaPrecio', 
            'variacions.valores.atributo'
        ])
        ->where('activo', true);

        // Filter by Category
        if ($this->selectedCategoria) {
            $query->where('categoria_id', $this->selectedCategoria);
        }

        // Filter by Brands
        if (!empty($this->selectedMarcas)) {
            $query->whereIn('marca_id', $this->selectedMarcas);
        }

        // Filter by Attributes
        if (!empty($this->selectedAtributos)) {
            $query->whereHas('variacions.valores', function ($q) {
                $q->whereIn('atributo_valores.id', $this->selectedAtributos);
            });
        }

        // Filter by Search Query
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('nombre', 'like', '%' . $this->search . '%')
                  ->orWhere('descripcion', 'like', '%' . $this->search . '%');
            });
        }

        $nombreListaPrecio = 'Precio Menor';
        if (auth()->check() && auth()->user()->cliente && auth()->user()->cliente->tipo_cliente === 'mayorista') {
            $nombreListaPrecio = auth()->user()->cliente->listaPrecio->nombre ?? 'Precio Mayor';
        }

        // Fetch products and resolve dynamic prices & discounts
        $products = $query->get()->map(function ($product) use ($nombreListaPrecio) {
            // Get base price (from first variation, list price based on user type)
            $firstVariation = $product->variacions->first();
            $basePrice = 0.00;
            if ($firstVariation) {
                $basePrice = (float) ($firstVariation->precios->firstWhere('listaPrecio.nombre', $nombreListaPrecio)?->precio ?? 0.00);
            }
            $product->precio_base = $basePrice;

            // Get active discount
            $activeDiscount = $product->descuentos->first();
            if ($activeDiscount) {
                $pct = (int) $activeDiscount->porcentaje_descuento;
                $product->tiene_descuento = true;
                $product->porcentaje_descuento = $pct;
                $product->precio_final = round($basePrice * (1 - $pct / 100), 2);
            } else {
                $product->tiene_descuento = false;
                $product->porcentaje_descuento = 0;
                $product->precio_final = $basePrice;
            }

            // Extract unique colors for dots
            $colors = [];
            foreach ($product->variacions as $v) {
                foreach ($v->valores as $val) {
                    if (strtolower($val->atributo->nombre) === 'color') {
                        $colors[] = $val->valor;
                    }
                }
            }
            $product->colores_disponibles = array_unique($colors);

            return $product;
        });

        // Filter by price range
        if ($this->precioMin !== null) {
            $products = $products->filter(fn($p) => $p->precio_final >= $this->precioMin);
        }
        if ($this->precioMax !== null) {
            $products = $products->filter(fn($p) => $p->precio_final <= $this->precioMax);
        }

        // Sorting
        if ($this->ordenarPor === 'precio_bajo') {
            $products = $products->sortBy('precio_final');
        } elseif ($this->ordenarPor === 'precio_alto') {
            $products = $products->sortByDesc('precio_final');
        } elseif ($this->ordenarPor === 'descuento') {
            $products = $products->sortByDesc('porcentaje_descuento');
        } else {
            // default/recomendados: latest
            $products = $products->sortByDesc('created_at');
        }

        // Paginación Manual de la Colección (20 por página)
        $perPage = 20;
        $page = $this->getPage();
        
        $results = $products->forPage($page, $perPage);
        
        return new LengthAwarePaginator($results, $products->count(), $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);
    }
}; ?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Breadcrumbs / Top header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between border-b border-zinc-200 dark:border-zinc-700 pb-6 mb-8 gap-4">
        <div>
            <flux:heading size="xl" class="font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ __('Catálogo Oficial de Mochilas') }}</flux:heading>
            <flux:subheading class="mt-1 text-sm text-zinc-500">{{ __('Descubre mochilas de alta resistencia, diseños ergonómicos y promociones escolares.') }}</flux:subheading>
        </div>
        
        <div class="flex items-center gap-4">
            <!-- Search bar -->
            <div class="w-64 sm:w-80">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Buscar mochila o modelo..." icon="magnifying-glass" class="w-full bg-white dark:bg-zinc-900" />
            </div>

            <!-- Sort dropdown -->
            <div class="w-48">
                <flux:select wire:model.live="ordenarPor" class="w-full bg-white dark:bg-zinc-900">
                    <flux:select.option value="recomendados">{{ __('Recomendados') }}</flux:select.option>
                    <flux:select.option value="precio_bajo">{{ __('Precio: Menor a Mayor') }}</flux:select.option>
                    <flux:select.option value="precio_alto">{{ __('Precio: Mayor a Menor') }}</flux:select.option>
                    <flux:select.option value="descuento">{{ __('Mayor Descuento') }}</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Filtros Laterales (Columna 1) -->
        <div class="space-y-8 bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 h-fit shadow-sm">
            <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-4">
                <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wider">{{ __('Filtrar por') }}</span>
                <flux:button variant="ghost" size="sm" wire:click="limpiarFiltros" class="text-xs text-zinc-500 hover:text-zinc-900">{{ __('Limpiar todo') }}</flux:button>
            </div>

            <!-- Filtro de Categorías -->
            <div class="space-y-3">
                <flux:text size="sm" class="font-bold text-zinc-900 dark:text-white uppercase tracking-wider">{{ __('Categoría') }}</flux:text>
                <div class="flex flex-col gap-1">
                    <button wire:click.prevent="$set('selectedCategoria', null)" class="text-left px-3 py-2 rounded-lg text-xs font-semibold flex justify-between items-center transition-all {{ is_null($selectedCategoria) ? 'bg-black text-white dark:bg-white dark:text-black shadow-md' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200/50 dark:hover:bg-zinc-800/50' }}">
                        <span>{{ __('Todas las categorías') }}</span>
                    </button>
                    @foreach($this->categorias as $cat)
                        @if($cat->productos_count > 0)
                            <button wire:click.prevent="$set('selectedCategoria', {{ $cat->id }})" class="text-left px-3 py-2 rounded-lg text-xs font-semibold flex justify-between items-center transition-all {{ $selectedCategoria === $cat->id ? 'bg-black text-white dark:bg-white dark:text-black shadow-md' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-200/50 dark:hover:bg-zinc-800/50' }}">
                                <span>{{ $cat->nombre }}</span>
                                <span class="px-2 py-0.5 text-xxs rounded-full {{ $selectedCategoria === $cat->id ? 'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-black' : 'bg-zinc-200 dark:bg-zinc-800 text-zinc-500' }}">{{ $cat->productos_count }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Filtro de Marcas -->
            <div class="space-y-3">
                <flux:text size="sm" class="font-bold text-zinc-900 dark:text-white uppercase tracking-wider">{{ __('Marca') }}</flux:text>
                <div class="space-y-2">
                    @foreach($this->marcas as $m)
                        @if($m->productos_count > 0)
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="brand-{{ $m->id }}" value="{{ $m->id }}" wire:model.live="selectedMarcas" class="rounded border-zinc-300 dark:border-zinc-700 text-black focus:ring-black dark:focus:ring-white size-4" />
                                <label for="brand-{{ $m->id }}" class="text-xs text-zinc-600 dark:text-zinc-400 font-medium select-none cursor-pointer flex justify-between w-full">
                                    <span>{{ $m->nombre }}</span>
                                    <span class="text-xxs text-zinc-400">({{ $m->productos_count }})</span>
                                </label>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Filtro de Atributos Dinámicos (Colores, Tallas, etc) -->
            @foreach($this->atributosFiltro as $attr)
                @if($attr->valores->count() > 0)
                    <div class="space-y-3">
                        <flux:text size="sm" class="font-bold text-zinc-900 dark:text-white uppercase tracking-wider">{{ $attr->nombre }}</flux:text>
                        <div class="space-y-2">
                            @foreach($attr->valores as $val)
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="attr-{{ $val->id }}" value="{{ $val->id }}" wire:model.live="selectedAtributos" class="rounded border-zinc-300 dark:border-zinc-700 text-black focus:ring-black dark:focus:ring-white size-4" />
                                    <label for="attr-{{ $val->id }}" class="text-xs text-zinc-600 dark:text-zinc-400 font-medium select-none cursor-pointer flex items-center gap-2 w-full">
                                        @if(strtolower(trim($attr->nombre)) === 'color' && $val->codigo_color_hex)
                                            <span class="size-3.5 rounded-full border border-zinc-300 shadow-sm" style="background-color: {{ $val->codigo_color_hex }}"></span>
                                        @endif
                                        <span>{{ $val->valor }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            <!-- Rango de Precios -->
            <div class="space-y-3">
                <flux:text size="sm" class="font-bold text-zinc-900 dark:text-white uppercase tracking-wider">{{ __('Rango de Precio') }}</flux:text>
                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model.live.debounce.500ms="precioMin" type="number" placeholder="Min S/" size="sm" class="bg-white dark:bg-zinc-950" />
                    <flux:input wire:model.live.debounce.500ms="precioMax" type="number" placeholder="Max S/" size="sm" class="bg-white dark:bg-zinc-950" />
                </div>
            </div>
        </div>

        <!-- Grid de Tarjetas de Producto (Columna 2-4) -->
        <div class="lg:col-span-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                @forelse($this->productos as $p)
                    <!-- Tarjeta de Producto -->
                    <a href="{{ route('producto.detalle', ['producto' => $p->id, 'slug' => \Illuminate\Support\Str::slug($p->nombre)]) }}" wire:navigate class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800/80 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 group flex flex-col justify-between relative h-full">
                        
                        <!-- Badge de descuento -->
                        @if($p->tiene_descuento)
                            <div class="absolute top-4 left-4 z-10 bg-rose-600 text-white font-bold text-xxs px-2.5 py-1 rounded-full shadow-sm">
                                -{{ $p->porcentaje_descuento }}%
                            </div>
                        @endif

                        <!-- Contenido Superior -->
                        <div>
                            <!-- Placeholder de Imagen Premium -->
                            <div class="aspect-[4/3] w-full bg-gradient-to-br from-zinc-100 to-zinc-200 dark:from-zinc-800 dark:to-zinc-900 relative overflow-hidden flex items-center justify-center border-b border-zinc-100 dark:border-zinc-800/50">
                                <flux:icon name="archive-box" class="size-16 text-zinc-300 dark:text-zinc-700 group-hover:scale-110 transition-transform duration-300" />
                                <div class="absolute inset-0 bg-black/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </div>

                            <!-- Info del Producto -->
                            <div class="p-5 space-y-2">
                                <div class="flex items-center justify-between text-xxs uppercase font-bold text-zinc-400 tracking-widest">
                                    <span>{{ $p->marca->nombre }}</span>
                                    <span>{{ $p->categoria->nombre }}</span>
                                </div>

                                <flux:heading size="md" class="font-bold text-zinc-900 dark:text-white line-clamp-1 group-hover:text-black dark:group-hover:text-zinc-200 transition-colors">
                                    {{ $p->nombre }}
                                </flux:heading>

                                <p class="text-xxs text-zinc-500 dark:text-zinc-400 line-clamp-2 h-8">
                                    {{ $p->descripcion ?: __('Sin descripción detallada.') }}
                                </p>

                                <!-- Variaciones de Colores -->
                                @if(!empty($p->colores_disponibles))
                                    <div class="flex items-center gap-1.5 pt-2">
                                        @foreach($p->colores_disponibles as $colName)
                                            @php
                                                // Mapeo simple de colores comunes a clases de Tailwind
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
                                                $colClass = $colMap[strtolower(trim($colName))] ?? 'bg-gradient-to-r from-zinc-300 to-zinc-500';
                                            @endphp
                                            <span class="size-3.5 rounded-full inline-block {{ $colClass }} shadow-inner" title="{{ $colName }}"></span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Precios y Acciones (Pie de Tarjeta) -->
                        <div class="p-5 pt-0 border-t border-zinc-100 dark:border-zinc-800/40 mt-4 flex items-center justify-between">
                            <div>
                                @if($p->tiene_descuento)
                                    <div class="text-xxs text-zinc-400 line-through font-semibold">S/ {{ number_format($p->precio_base, 2) }}</div>
                                    <div class="text-base font-extrabold text-rose-600">S/ {{ number_format($p->precio_final, 2) }}</div>
                                @else
                                    <div class="text-xs text-zinc-400 font-semibold">{{ __('Desde') }}</div>
                                    <div class="text-base font-extrabold text-zinc-900 dark:text-white">S/ {{ number_format($p->precio_final, 2) }}</div>
                                @endif
                            </div>

                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-zinc-600 dark:text-zinc-400 group-hover:text-black dark:group-hover:text-white transition-colors">
                                {{ __('Ver detalles') }}
                                <flux:icon name="chevron-right" class="size-4" />
                            </span>
                        </div>

                    </a>
                @empty
                    <div class="col-span-full text-center py-20 bg-zinc-50 dark:bg-zinc-900 border border-dashed border-zinc-200 dark:border-zinc-800 rounded-3xl">
                        <flux:icon name="magnifying-glass" class="size-12 mx-auto text-zinc-400 dark:text-zinc-600 mb-4" />
                        <flux:heading size="lg" class="text-zinc-600 dark:text-zinc-400">{{ __('No se encontraron mochilas') }}</flux:heading>
                        <flux:subheading class="mt-1">{{ __('Intenta modificar tus filtros de búsqueda o categoría.') }}</flux:subheading>
                    </div>
                @endforelse
            </div>

            <!-- Paginación -->
            @if($this->productos->hasPages())
                <div class="mt-10">
                    {{ $this->productos->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
