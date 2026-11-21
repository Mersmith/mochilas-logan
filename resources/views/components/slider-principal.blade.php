@props([
    'sliders' => collect(),
    'autoplay' => true,
    'duration' => 5000,
])

@php
    $slidersCollection = $sliders instanceof \Illuminate\Support\Collection ? $sliders : collect($sliders ?? []);
    $total = $slidersCollection->count();
@endphp

@if($total > 0)
    <div
        x-data="{
            current: 0,
            total: {{ $total }},
            autoplay: @js($autoplay),
            duration: @js($duration),
            timer: null,
            touchStartX: 0,
            touchEndX: 0,

            init() {
                if (this.autoplay && this.total > 1) {
                    this.startAutoplay();
                }
            },
            next() {
                this.current = (this.current + 1) % this.total;
                this.restartAutoplay();
            },
            prev() {
                this.current = (this.current - 1 + this.total) % this.total;
                this.restartAutoplay();
            },
            goTo(index) {
                this.current = index;
                this.restartAutoplay();
            },
            startAutoplay() {
                this.stopAutoplay();
                this.timer = setInterval(() => {
                    this.current = (this.current + 1) % this.total;
                }, this.duration);
            },
            stopAutoplay() {
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
            },
            restartAutoplay() {
                if (this.autoplay && this.total > 1) {
                    this.startAutoplay();
                }
            },
            onTouchStart(e) {
                this.touchStartX = e.changedTouches[0].screenX;
            },
            onTouchEnd(e) {
                this.touchEndX = e.changedTouches[0].screenX;
                if (this.touchStartX - this.touchEndX > 40) {
                    this.next();
                } else if (this.touchEndX - this.touchStartX > 40) {
                    this.prev();
                }
            }
        }"
        x-init="init()"
        @mouseenter="stopAutoplay()"
        @mouseleave="startAutoplay()"
        @touchstart="onTouchStart($event)"
        @touchend="onTouchEnd($event)"
        @keydown.arrow-right.window="next()"
        @keydown.arrow-left.window="prev()"
        class="relative overflow-hidden rounded-3xl mx-4 sm:mx-6 lg:mx-8 my-8 shadow-2xl bg-zinc-950 group select-none"
    >
        <!-- Slides Container -->
        <div class="relative w-full h-[380px] sm:h-[460px] md:h-[500px] lg:h-[550px]">
            @foreach($slidersCollection as $index => $slider)
                <div
                    x-show="current === {{ $index }}"
                    x-transition:enter="transition ease-out duration-700 transform"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-300 transform"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute inset-0 w-full h-full"
                    style="{{ $index === 0 ? '' : 'display: none;' }}"
                >
                    @if($slider->link)
                        <a href="{{ $slider->link }}" wire:navigate class="block w-full h-full relative cursor-pointer group/slide">
                    @else
                        <div class="block w-full h-full relative">
                    @endif

                        <!-- Imagen Desktop (md en adelante) -->
                        <img
                            src="{{ $slider->img_computadora_url }}"
                            alt="{{ $slider->nombre ?? 'Mochilas Logan' }}"
                            class="hidden md:block w-full h-full object-cover group-hover/slide:scale-102 transition-transform duration-700 ease-out"
                            loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                        />

                        <!-- Imagen Móvil (hasta md) -->
                        <img
                            src="{{ $slider->img_movil_url }}"
                            alt="{{ $slider->nombre ?? 'Mochilas Logan' }}"
                            class="block md:hidden w-full h-full object-cover group-hover/slide:scale-102 transition-transform duration-700 ease-out"
                            loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                        />

                        <!-- Overlay degradado para dar contraste si hay título o detalles -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent pointer-events-none"></div>

                        @if($slider->nombre)
                            <div class="absolute bottom-10 left-6 sm:bottom-14 sm:left-12 lg:left-16 max-w-xl text-left pointer-events-none">
                                <h2 class="text-white font-extrabold text-2xl sm:text-4xl lg:text-5xl tracking-tight drop-shadow-md">
                                    {{ $slider->nombre }}
                                </h2>
                                @if($slider->link)
                                    <span class="inline-flex items-center gap-2 mt-3 px-5 py-2.5 text-xs sm:text-sm font-bold text-zinc-900 bg-white rounded-xl shadow-lg pointer-events-auto hover:bg-zinc-100 transition-colors">
                                        {{ __('Ver más') }}
                                        <flux:icon.arrow-right class="size-3.5" />
                                    </span>
                                @endif
                            </div>
                        @endif

                    @if($slider->link)
                        </a>
                    @else
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if($total > 1)
            <!-- Flecha Anterior -->
            <button
                type="button"
                @click.stop="prev()"
                aria-label="{{ __('Anterior') }}"
                class="absolute left-3 sm:left-5 top-1/2 -translate-y-1/2 z-20 size-10 sm:size-12 rounded-full bg-black/40 hover:bg-black/70 backdrop-blur-md text-white flex items-center justify-center transition-all opacity-0 group-hover:opacity-100 focus:opacity-100 hover:scale-110 shadow-lg cursor-pointer"
            >
                <flux:icon.chevron-left class="size-6" />
            </button>

            <!-- Flecha Siguiente -->
            <button
                type="button"
                @click.stop="next()"
                aria-label="{{ __('Siguiente') }}"
                class="absolute right-3 sm:right-5 top-1/2 -translate-y-1/2 z-20 size-10 sm:size-12 rounded-full bg-black/40 hover:bg-black/70 backdrop-blur-md text-white flex items-center justify-center transition-all opacity-0 group-hover:opacity-100 focus:opacity-100 hover:scale-110 shadow-lg cursor-pointer"
            >
                <flux:icon.chevron-right class="size-6" />
            </button>

            <!-- Indicadores (Dots) -->
            <div class="absolute bottom-4 inset-x-0 z-20 flex justify-center items-center gap-2 pointer-events-none">
                @foreach($slidersCollection as $index => $slider)
                    <button
                        type="button"
                        @click.stop="goTo({{ $index }})"
                        :class="current === {{ $index }} ? 'w-8 bg-white' : 'w-2.5 bg-white/50 hover:bg-white/80'"
                        class="h-2 rounded-full transition-all duration-300 pointer-events-auto shadow cursor-pointer"
                        aria-label="{{ __('Ir a diapositiva :num', ['num' => $index + 1]) }}"
                    ></button>
                @endforeach
            </div>
        @endif
    </div>
@else
    <!-- Fallback si no hay sliders registrados -->
    <div class="relative bg-zinc-900 overflow-hidden rounded-3xl mx-4 sm:mx-6 lg:mx-8 my-8 shadow-2xl">
        <div class="absolute inset-0">
            <img src="{{ asset('assets/imagen/default.jpg') }}" alt="Mochilas Logan" class="w-full h-full object-cover opacity-40">
            <div class="absolute inset-0 bg-gradient-to-t from-zinc-900 via-zinc-900/60 to-transparent"></div>
        </div>
        
        <div class="relative px-6 py-24 sm:px-12 sm:py-32 lg:px-16 flex flex-col items-center justify-center text-center">
            <flux:heading size="xl" class="text-white font-extrabold text-4xl sm:text-5xl lg:text-6xl tracking-tight mb-4">
                {{ __('Mochilas de alta resistencia') }}
            </flux:heading>
            <p class="mt-4 text-lg sm:text-xl text-zinc-300 max-w-2xl mx-auto mb-10">
                {{ __('Descubre nuestra nueva colección escolar y urbana. Diseños ergonómicos, compartimientos inteligentes y la durabilidad que necesitas.') }}
            </p>
            
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="{{ route('catalogo') }}" wire:navigate class="inline-flex items-center justify-center px-8 py-4 text-sm font-bold text-zinc-900 bg-white rounded-xl hover:bg-zinc-100 transition-colors shadow-lg">
                    {{ __('Ver Catálogo Completo') }}
                </a>
            </div>
        </div>
    </div>
@endif
