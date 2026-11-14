@component('layouts.publico')
    {{-- 1. Carrusel / Slider Principal de Portada --}}
    <x-slider-principal />

    {{-- 2. Sliders de Productos por Categoría --}}
    @php
        $categoriasSliders = \App\Models\Categoria::where('activo', true)
            ->whereHas('productos', fn($q) => $q->where('activo', true))
            ->with(['productos' => function($q) {
                $q->where('activo', true)
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
                  ->latest();
            }])
            ->orderBy('orden', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    @endphp

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8 sm:space-y-12 my-8 sm:my-12">
        @foreach($categoriasSliders as $categoria)
            <x-slider-productos :categoria="$categoria" :productos="$categoria->productos" />
        @endforeach
    </div>
@endcomponent
