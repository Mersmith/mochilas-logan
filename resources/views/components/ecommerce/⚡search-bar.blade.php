<?php

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\TipoProducto;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $q = '';

    public function mount(): void
    {
        $this->q = request('q', '');
    }

    public function updatedQ(): void
    {
        unset($this->productos);
    }

    public function search(): void
    {
        $termino = trim($this->q);
        if ($termino === '') {
            $this->redirectRoute('catalogo', navigate: true);
        } else {
            $this->redirectRoute('catalogo', ['q' => $termino], navigate: true);
        }
    }

    public function selectKeyword(string $keyword): void
    {
        $this->q = $keyword;
        $this->search();
    }

    /**
     * Categorías o términos populares para el menú lateral.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function populares(): array
    {
        $categorias = Categoria::where('activo', true)->pluck('nombre')->toArray();
        $tipos = TipoProducto::where('activo', true)->pluck('nombre')->toArray();
        $marcas = Marca::where('activo', true)->pluck('nombre')->toArray();

        $items = array_values(array_unique(array_filter([...$categorias, ...$tipos, ...$marcas])));

        if (empty($items)) {
            $items = ['Mochila', 'Escolar', 'Urbana', 'Viaje', 'Laptop', 'Cartera'];
        }

        return array_slice($items, 0, 7);
    }

    /**
     * Productos sugeridos o resultado de búsqueda en vivo.
     */
    #[Computed]
    public function productos()
    {
        $query = Producto::query()
            ->where('activo', true)
            ->with([
                'marca',
                'categoria',
                'descuentos' => fn ($dq) => $dq->where('activo', true)
                    ->where(fn ($sq) => $sq->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', now()))
                    ->where(fn ($sq) => $sq->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now())),
                'variacions.precios.listaPrecio',
                'variacions.valores.atributo',
                'media',
            ]);

        $termino = trim($this->q);

        if ($termino !== '') {
            $query->where(function ($sq) use ($termino) {
                $sq->where('nombre', 'like', "%{$termino}%")
                    ->orWhere('descripcion', 'like', "%{$termino}%")
                    ->orWhereHas('marca', fn ($mq) => $mq->where('nombre', 'like', "%{$termino}%"))
                    ->orWhereHas('categoria', fn ($cq) => $cq->where('nombre', 'like', "%{$termino}%"));
            });
        }

        return $query->latest()->take(6)->get();
    }
};
?>

<div
    x-data="{
        isOpen: false,
        openSearch() {
            this.isOpen = true;
            this.$nextTick(() => {
                if (this.$refs.searchInput) {
                    this.$refs.searchInput.focus();
                }
            });
        },
        closeSearch() {
            this.isOpen = false;
        }
    }"
    @keydown.escape.window="closeSearch()"
    class="w-full relative"
>
    <!-- Barra visible en el navbar (actúa como input y trigger) -->
    <div class="relative w-full">
        <form wire:submit.prevent="search" class="w-full relative">
            <div class="relative flex items-center">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="q"
                    @focus="openSearch()"
                    placeholder="{{ __('Buscar mochila o modelo...') }}"
                    class="w-full h-10 pl-10 pr-10 rounded-xl bg-zinc-100 dark:bg-zinc-800/80 hover:bg-zinc-200/70 dark:hover:bg-zinc-800 text-sm text-zinc-900 dark:text-white placeholder:text-zinc-500 dark:placeholder:text-zinc-400 border border-transparent focus:border-zinc-300 dark:focus:border-zinc-700 focus:outline-none transition-colors"
                />
                <div class="absolute left-3 pointer-events-none text-zinc-400 dark:text-zinc-500">
                    <flux:icon name="magnifying-glass" class="size-4" />
                </div>
                <button
                    type="submit"
                    class="absolute right-2 p-1.5 text-zinc-400 hover:text-black dark:hover:text-white rounded-lg transition-colors"
                    aria-label="{{ __('Buscar') }}"
                >
                    <flux:icon name="arrow-right" class="size-4" />
                </button>
            </div>
        </form>
    </div>

    <!-- Modal / Overlay de Búsqueda estilo Porta (Pantalla grande y responsiva) -->
    <template x-teleport="body">
        <div
            x-show="isOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex flex-col justify-start"
            @click.self="closeSearch()"
        >
            <!-- Panel contenedor blanco / oscuro -->
            <div
                x-show="isOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-4"
                class="w-full bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 shadow-2xl pt-4 pb-8 px-4 sm:px-6 lg:px-8 max-h-[92vh] overflow-y-auto"
            >
                <div class="max-w-7xl mx-auto">
                    <!-- Fila Superior: Buscador grande + Botón Cerrar -->
                    <div class="flex items-center gap-4 pb-6 border-b border-zinc-100 dark:border-zinc-800">
                        <form wire:submit.prevent="search" class="flex-1 flex items-center gap-3">
                            <flux:icon name="magnifying-glass" class="size-6 text-zinc-400 shrink-0" />
                            <input
                                x-ref="searchInput"
                                type="text"
                                wire:model.live.debounce.300ms="q"
                                placeholder="{{ __('Buscar...') }}"
                                class="w-full text-lg sm:text-2xl font-semibold bg-transparent border-none text-zinc-900 dark:text-white placeholder:text-zinc-400 focus:outline-none"
                            />
                            <button
                                type="submit"
                                class="p-2 text-zinc-500 hover:text-black dark:hover:text-white rounded-lg transition-colors"
                                title="{{ __('Buscar') }}"
                            >
                                <flux:icon name="arrow-right" class="size-6" />
                            </button>
                        </form>

                        <!-- Botón cerrar (X) -->
                        <button
                            type="button"
                            @click="closeSearch()"
                            class="p-2 rounded-xl text-zinc-400 hover:text-zinc-800 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
                            aria-label="{{ __('Cerrar buscador') }}"
                        >
                            <flux:icon name="x-mark" class="size-6" />
                        </button>
                    </div>

                    <!-- Contenido: Dos columnas (Sidebar Populares/Sugerencias + Grid de Productos) -->
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-8 pt-6">

                        <!-- Columna Izquierda: POPULARES o SUGERENCIAS -->
                        <div class="md:col-span-3 lg:col-span-2 space-y-4">
                            <h3 class="text-xs font-bold text-zinc-400 dark:text-zinc-500 uppercase tracking-wider">
                                {{ trim($q) === '' ? __('Populares') : __('Sugerencias') }}
                            </h3>

                            @if(trim($q) !== '')
                                <ul class="space-y-2 text-sm">
                                    <li>
                                        <button
                                            type="button"
                                            wire:click="search"
                                            @click="closeSearch()"
                                            class="text-left text-zinc-800 dark:text-zinc-200 hover:underline font-medium flex items-center gap-1.5"
                                        >
                                            <flux:icon name="magnifying-glass" class="size-3.5 text-zinc-400" />
                                            <span>{{ __('Buscar ":term" en todo', ['term' => $q]) }}</span>
                                        </button>
                                    </li>
                                </ul>
                            @else
                                <ul class="space-y-2.5 text-sm">
                                    @foreach($this->populares as $pop)
                                        <li>
                                            <button
                                                type="button"
                                                wire:click="selectKeyword('{{ $pop }}')"
                                                @click="closeSearch()"
                                                class="text-zinc-600 dark:text-zinc-400 hover:text-black dark:hover:text-white transition-colors text-left"
                                            >
                                                {{ $pop }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <!-- Columna Derecha: Tarjetas de Productos (Grid responsivo estilo Porta) -->
                        <div class="md:col-span-9 lg:col-span-10">
                            @if($this->productos->isNotEmpty())
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                                    @foreach($this->productos as $producto)
                                        <a
                                            href="{{ route('producto.detalle', ['producto' => $producto->id, 'slug' => $producto->slug]) }}"
                                            wire:navigate
                                            @click="closeSearch()"
                                            class="group flex flex-col bg-white dark:bg-zinc-950/40 rounded-xl p-2.5 transition-all hover:shadow-md border border-zinc-100 dark:border-zinc-800/80 hover:border-zinc-300 dark:hover:border-zinc-700"
                                        >
                                            <!-- Contenedor Imagen -->
                                            <div class="relative aspect-square w-full rounded-lg bg-zinc-50 dark:bg-zinc-900 overflow-hidden mb-2.5 flex items-center justify-center">
                                                @if($producto->imagen_url)
                                                    <img
                                                        src="{{ $producto->imagen_url }}"
                                                        alt="{{ $producto->nombre }}"
                                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                                        loading="lazy"
                                                    />
                                                @else
                                                    <flux:icon name="archive-box" class="size-10 text-zinc-300 dark:text-zinc-700" />
                                                @endif

                                                @if($producto->tiene_descuento)
                                                    <span class="absolute top-1.5 left-1.5 bg-rose-600 text-white text-[9px] font-extrabold px-1.5 py-0.5 rounded">
                                                        -{{ $producto->porcentaje_descuento }}%
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Swatches de Color si existen -->
                                            @if(!empty($producto->colores_disponibles))
                                                <div class="flex items-center gap-1 mb-2">
                                                    @foreach(array_slice($producto->colores_disponibles, 0, 4) as $col)
                                                        <span
                                                            class="size-3.5 rounded-full border border-zinc-300 dark:border-zinc-700 shadow-xs"
                                                            style="background-color: {{ $col['hex'] ?? '#71717a' }};"
                                                            title="{{ $col['nombre'] }}"
                                                        ></span>
                                                    @endforeach
                                                </div>
                                            @endif

                                            <!-- Nombre y Marca -->
                                            <div class="text-xs font-semibold text-zinc-900 dark:text-white line-clamp-2 leading-tight group-hover:text-zinc-600 dark:group-hover:text-zinc-300 transition-colors">
                                                {{ $producto->nombre }}
                                            </div>

                                            <!-- Precios -->
                                            <div class="mt-2 flex items-baseline gap-1.5">
                                                <span class="text-xs sm:text-sm font-black text-zinc-900 dark:text-white">
                                                    S/ {{ number_format($producto->precio_final, 2) }}
                                                </span>
                                                @if($producto->tiene_descuento)
                                                    <span class="text-[10px] text-zinc-400 line-through">
                                                        S/ {{ number_format($producto->precio_base, 2) }}
                                                    </span>
                                                @endif
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="py-12 text-center">
                                    <flux:icon name="magnifying-glass" class="size-10 mx-auto text-zinc-300 dark:text-zinc-700 mb-3" />
                                    <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                                        {{ __('No encontramos mochilas para ":term"', ['term' => $q]) }}
                                    </p>
                                    <p class="text-xs text-zinc-400 mt-1">
                                        {{ __('Intenta con otra palabra clave o revisa los productos populares.') }}
                                    </p>
                                </div>
                            @endif
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
