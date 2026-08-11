@component('layouts.publico')
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
@endcomponent
