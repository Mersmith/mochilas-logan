@props([
    'categoria' => null,
    'productos' => null,
    'titulo' => null,
    'subtitulo' => null,
    'linkVerMas' => null,
])

@php
    $items = $productos;

    if ($items === null && $categoria) {
        $items = $categoria->productos()
            ->where('activo', true)
            ->with([
                'marca',
                'categoria',
                'descuentos' => fn($dq) => $dq->where('activo', true)
                    ->where(fn($sq) => $sq->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', now()))
                    ->where(fn($sq) => $sq->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now())),
                'variacions.precios.listaPrecio',
                'variacions.valores.atributo',
                'media',
            ])
            ->latest()
            ->take(12)
            ->get();
    }

    $title = $titulo ?? ($categoria ? $categoria->nombre : __('Productos Destacados'));
    $subtitle = $subtitulo ?? ($categoria ? $categoria->descripcion : null);
    $urlVerMas = $linkVerMas ?? ($categoria ? route('catalogo', ['categoria' => $categoria->id]) : route('catalogo'));
@endphp

@if($items && $items->isNotEmpty())
    <section
        x-data="{
            canScrollLeft: false,
            canScrollRight: true,
            checkScroll() {
                const el = this.$refs.slider;
                if (!el) return;
                this.canScrollLeft = el.scrollLeft > 15;
                this.canScrollRight = el.scrollLeft < (el.scrollWidth - el.clientWidth - 15);
            },
            scrollNext() {
                const el = this.$refs.slider;
                if (!el) return;
                const card = el.querySelector('[data-slider-card]');
                const distance = card ? (card.offsetWidth + 24) * 2 : 450;
                el.scrollBy({ left: distance, behavior: 'smooth' });
            },
            scrollPrev() {
                const el = this.$refs.slider;
                if (!el) return;
                const card = el.querySelector('[data-slider-card]');
                const distance = card ? (card.offsetWidth + 24) * 2 : 450;
                el.scrollBy({ left: -distance, behavior: 'smooth' });
            }
        }"
        x-init="$nextTick(() => checkScroll())"
        class="relative my-8 sm:my-12"
    >
        <!-- Cabecera del Slider: Título, Ver más y Controles de Navegación -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-4 pb-3 border-b border-zinc-200 dark:border-zinc-800">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-block w-2.5 h-6 bg-black dark:bg-white rounded-full"></span>
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-zinc-900 dark:text-white uppercase tracking-tight">
                        {{ $title }}
                    </h2>
                </div>
                @if($subtitle)
                    <p class="text-xs sm:text-sm text-zinc-500 dark:text-zinc-400 mt-1 pl-4.5">
                        {{ $subtitle }}
                    </p>
                @endif
            </div>

            <div class="flex items-center justify-between sm:justify-end gap-3">
                <a
                    href="{{ $urlVerMas }}"
                    wire:navigate
                    class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-bold text-zinc-800 dark:text-zinc-200 hover:text-black dark:hover:text-white transition-colors"
                >
                    <span>{{ __('Ver catálogo completo') }}</span>
                    <flux:icon.arrow-right class="size-4" />
                </a>

                <!-- Controles Prev / Next -->
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        @click="scrollPrev()"
                        :disabled="!canScrollLeft"
                        :class="canScrollLeft ? 'opacity-100 hover:bg-zinc-200 dark:hover:bg-zinc-700 active:scale-95' : 'opacity-40 cursor-not-allowed'"
                        class="size-9 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-white flex items-center justify-center transition-all shadow-xs"
                        aria-label="{{ __('Anterior') }}"
                    >
                        <flux:icon.chevron-left class="size-4" />
                    </button>

                    <button
                        type="button"
                        @click="scrollNext()"
                        :disabled="!canScrollRight"
                        :class="canScrollRight ? 'opacity-100 hover:bg-zinc-200 dark:hover:bg-zinc-700 active:scale-95' : 'opacity-40 cursor-not-allowed'"
                        class="size-9 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-800 dark:text-white flex items-center justify-center transition-all shadow-xs"
                        aria-label="{{ __('Siguiente') }}"
                    >
                        <flux:icon.chevron-right class="size-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Contenedor Deslizante de Productos -->
        <div
            x-ref="slider"
            @scroll.passive="checkScroll()"
            class="flex gap-4 sm:gap-6 overflow-x-auto scroll-smooth snap-x snap-mandatory py-4 [&::-webkit-scrollbar]:hidden"
            style="scrollbar-width: none; -ms-overflow-style: none;"
        >
            @foreach($items as $p)
                @php
                    $urlDetalle = route('producto.detalle', [
                        'producto' => $p->id,
                        'slug' => \Illuminate\Support\Str::slug($p->nombre),
                    ]);
                @endphp
                <div
                    data-slider-card
                    class="w-[200px] sm:w-[240px] md:w-[260px] lg:w-[280px] shrink-0 snap-start bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800/80 rounded-2xl overflow-hidden shadow-xs hover:shadow-xl transition-all duration-300 group flex flex-col justify-between relative"
                >
                    <!-- Badge de Descuento -->
                    @if($p->tiene_descuento)
                        <div class="absolute top-3 left-3 z-10 bg-rose-600 text-white font-black text-[10px] sm:text-xs px-2.5 py-0.5 rounded-full shadow-sm tracking-tight">
                            -{{ $p->porcentaje_descuento }}%
                        </div>
                    @endif

                    <!-- Imagen -->
                    <a href="{{ $urlDetalle }}" wire:navigate class="block relative aspect-[4/3] w-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden border-b border-zinc-100 dark:border-zinc-800/50">
                        <img
                            src="{{ $p->imagen_url }}"
                            alt="{{ $p->nombre }}"
                            class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500"
                            loading="lazy"
                        />
                        <div class="absolute inset-0 bg-black/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                    </a>

                    <!-- Información del Producto -->
                    <div class="p-4 flex flex-col flex-1 justify-between gap-3">
                        <div class="space-y-1">
                            @if($p->marca)
                                <div class="text-[10px] uppercase font-bold text-zinc-400 tracking-wider">
                                    {{ $p->marca->nombre }}
                                </div>
                            @endif

                            <a href="{{ $urlDetalle }}" wire:navigate class="block">
                                <h3 class="font-bold text-sm sm:text-base text-zinc-900 dark:text-white line-clamp-1 group-hover:text-black dark:group-hover:text-zinc-200 transition-colors">
                                    {{ $p->nombre }}
                                </h3>
                            </a>

                            @if($p->descripcion)
                                <p class="text-[11px] text-zinc-500 dark:text-zinc-400 line-clamp-2 leading-relaxed">
                                    {{ $p->descripcion }}
                                </p>
                            @endif
                        </div>

                        <!-- Precios y Enlace a Detalle -->
                        <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800/60 flex items-center justify-between">
                            <div>
                                @if($p->tiene_descuento)
                                    <div class="text-[11px] text-zinc-400 line-through font-semibold">
                                        S/ {{ number_format($p->precio_base, 2) }}
                                    </div>
                                    <div class="text-base sm:text-lg font-extrabold text-rose-600">
                                        S/ {{ number_format($p->precio_final, 2) }}
                                    </div>
                                @else
                                    <div class="text-[10px] text-zinc-400 font-semibold">
                                        {{ __('Desde') }}
                                    </div>
                                    <div class="text-base sm:text-lg font-extrabold text-zinc-900 dark:text-white">
                                        S/ {{ number_format($p->precio_final, 2) }}
                                    </div>
                                @endif
                            </div>

                            <a
                                href="{{ $urlDetalle }}"
                                wire:navigate
                                class="size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black flex items-center justify-center text-zinc-600 dark:text-zinc-300 transition-colors shadow-xs"
                                title="{{ __('Ver detalles') }}"
                            >
                                <flux:icon.chevron-right class="size-4" />
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
