<?php

use App\Models\Categoria;
use App\Models\SliderPrincipal;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Inicio - Mochilas Logan'), Layout('layouts.publico')] class extends Component {
    /**
     * Sliders principales de portada activos y ordenados.
     */
    #[Computed]
    public function slidersPrincipales()
    {
        return SliderPrincipal::activos()->ordenados()->get();
    }

    /**
     * Categorías activas con productos para los sliders del home.
     */
    #[Computed]
    public function categoriasSliders()
    {
        return Categoria::where('activo', true)
            ->whereHas('productos', fn ($q) => $q->where('activo', true))
            ->with(['productos' => function ($q) {
                $q->where('activo', true)
                    ->with([
                        'marca',
                        'categoria',
                        'descuentos' => fn ($dq) => $dq->where('activo', true)
                            ->where(fn ($sq) => $sq->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', now()))
                            ->where(fn ($sq) => $sq->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now())),
                        'variacions.precios.listaPrecio',
                        'variacions.valores.atributo',
                        'media',
                    ])
                    ->latest();
            }])
            ->orderBy('orden', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }
}; ?>

<div class="space-y-8 sm:space-y-12 my-4 sm:my-8">
    {{-- 1. Carrusel / Slider Principal de Portada --}}
    <x-slider-principal :sliders="$this->slidersPrincipales" />

    {{-- 2. Sliders de Productos por Categoría --}}
    <div class="space-y-8 sm:space-y-12">
        @foreach($this->categoriasSliders as $categoria)
            <x-slider-productos :categoria="$categoria" :productos="$categoria->productos" />
        @endforeach
    </div>
</div>
