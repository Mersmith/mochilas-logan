<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\Producto;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new #[Title('Favoritos')] #[Layout('layouts.settings')] class extends Component {
    public $favoritos = [];

    public function mount()
    {
        $this->loadFavoritos();
    }

    public function loadFavoritos()
    {
        $cliente = Auth::user()->cliente;
        if ($cliente) {
            $this->favoritos = $cliente->favoritos()->with(['variacions.precios', 'descuentos'])->get();
        }
    }

    public function removeFavorito($productoId)
    {
        $cliente = Auth::user()->cliente;
        if ($cliente) {
            $cliente->favoritos()->detach($productoId);
            Flux::toast(variant: 'success', text: __('Producto eliminado de tus favoritos.'));
            $this->loadFavoritos();
        }
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Favoritos') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Mis favoritos')" :subheading="__('Tus productos guardados')">
        
        @if($favoritos->count() > 0)
            <div class="space-y-4">
                <div class="flex items-center justify-between mb-4">
                    <span class="text-sm text-zinc-500 font-medium">{{ $favoritos->count() }} producto(s)</span>
                </div>

                @foreach($favoritos as $producto)
                    @php
                        // Calcular precios (simplificado para la vista)
                        $basePrice = 0.00;
                        $firstVar = $producto->variacions->first();
                        if ($firstVar) {
                            $basePrice = (float) ($firstVar->precios->first()?->precio ?? 0.00);
                        }

                        $activeDiscount = $producto->descuentos->first();
                        $finalPrice = $basePrice;
                        if ($activeDiscount) {
                            $pct = (int) $activeDiscount->porcentaje_descuento;
                            $finalPrice = round($basePrice * (1 - $pct / 100), 2);
                        }
                        
                        $stock = $firstVar ? (int) $firstVar->inventarios->sum('stock_base') : 0;
                    @endphp

                    <div class="flex flex-col sm:flex-row gap-6 p-4 sm:p-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl relative group">
                        
                        <!-- Checkbox y Foto -->
                        <div class="flex items-start gap-4">
                            <flux:checkbox wire:model="selected" value="{{ $producto->id }}" class="mt-2" />
                            
                            <a href="{{ route('producto.detalle', ['producto' => $producto->id, 'slug' => \Illuminate\Support\Str::slug($producto->nombre)]) }}" wire:navigate class="shrink-0 size-24 sm:size-32 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex items-center justify-center border border-zinc-200 dark:border-zinc-700">
                                @if($producto->getFirstMediaUrl('productos'))
                                    <img src="{{ $producto->getFirstMediaUrl('productos') }}" alt="{{ $producto->nombre }}" class="w-full h-full object-cover rounded-lg" />
                                @else
                                    <flux:icon name="archive-box" class="size-10 text-zinc-300 dark:text-zinc-600" />
                                @endif
                            </a>
                        </div>

                        <!-- Detalles -->
                        <div class="flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-xs font-bold text-zinc-400 uppercase tracking-wide mb-1">{{ $producto->marca->nombre ?? 'MARCA' }}</p>
                                        <a href="{{ route('producto.detalle', ['producto' => $producto->id, 'slug' => \Illuminate\Support\Str::slug($producto->nombre)]) }}" wire:navigate class="text-base font-bold text-zinc-900 dark:text-white hover:underline line-clamp-2">
                                            {{ $producto->nombre }}
                                        </a>
                                        
                                        @if($stock > 0)
                                            <p class="text-xs font-medium text-emerald-600 mt-2">Disponible en tienda</p>
                                        @else
                                            <p class="text-xs font-medium text-rose-600 mt-2">No disponible</p>
                                        @endif
                                    </div>
                                    
                                    <div class="text-right pl-4">
                                        <div class="flex items-baseline gap-2 justify-end">
                                            <span class="text-xl font-extrabold text-zinc-900 dark:text-white">S/ {{ number_format($finalPrice, 2) }}</span>
                                            @if($activeDiscount)
                                                <flux:badge color="red" size="sm">-{{ $activeDiscount->porcentaje_descuento }}%</flux:badge>
                                            @endif
                                        </div>
                                        @if($activeDiscount)
                                            <span class="text-xs text-zinc-400 line-through">S/ {{ number_format($basePrice, 2) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-6 mt-4 sm:mt-0">
                                <a href="{{ route('catalogo') }}" wire:navigate class="text-sm font-semibold text-zinc-600 hover:text-black dark:text-zinc-400 dark:hover:text-white underline decoration-zinc-300 underline-offset-4">Buscar similares</a>
                                
                                <button wire:click.prevent="removeFavorito({{ $producto->id }})" class="p-2 text-zinc-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors" title="Eliminar de favoritos">
                                    <flux:icon name="trash" class="size-5" />
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-xl p-12 text-center">
                <div class="flex justify-center mb-4">
                    <div class="size-16 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                        <flux:icon name="heart" class="size-8 text-zinc-400" />
                    </div>
                </div>
                <h3 class="text-lg font-bold text-zinc-900 dark:text-white mb-2">Aún no has guardado productos en tus favoritos</h3>
                <p class="text-zinc-500 text-sm mb-6">Explora nuestra tienda y guarda los productos que más te gusten.</p>
                <flux:button variant="primary" href="{{ route('catalogo') }}" wire:navigate>Explorar tienda</flux:button>
            </div>
        @endif

    </x-pages::settings.layout>
</section>
