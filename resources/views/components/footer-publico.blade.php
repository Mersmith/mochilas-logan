@props([
    'empresa' => null,
])

@php
    $empresa = $empresa ?? \App\Models\EmpresaConfiguracion::getEmpresa();
@endphp

<footer class="bg-zinc-900 text-zinc-300 mt-16">
    {{-- Banda superior --}}
    <div class="border-b border-zinc-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">

                {{-- Columna 1: Marca --}}
                <div class="sm:col-span-2 lg:col-span-1">
                    <div class="flex items-center gap-2 mb-4">
                        <flux:brand href="{{ route('home') }}" wire:navigate>
                            <x-slot name="logo"
                                class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground">
                                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
                            </x-slot>
                            <span class="text-white font-bold text-lg">{{ $empresa->nombre_comercial }}</span>
                        </flux:brand>
                    </div>

                    @if ($empresa->direccion_fiscal)
                        <p class="text-sm text-zinc-400 flex items-start gap-2 mb-2">
                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ $empresa->direccion_fiscal }}
                        </p>
                    @endif

                    @if ($empresa->horario_atencion)
                        <p class="text-sm text-zinc-400 flex items-start gap-2 mb-4">
                            <svg class="w-4 h-4 mt-0.5 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ $empresa->horario_atencion }}
                        </p>
                    @endif

                    {{-- Redes Sociales --}}
                    <div class="flex items-center gap-3 mt-2">
                        @if ($empresa->facebook_url)
                            <a href="{{ $empresa->facebook_url }}" target="_blank" rel="noopener noreferrer"
                                class="text-zinc-400 hover:text-white transition-colors" aria-label="Facebook">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                                </svg>
                            </a>
                        @endif

                        @if ($empresa->instagram_url)
                            <a href="{{ $empresa->instagram_url }}" target="_blank" rel="noopener noreferrer"
                                class="text-zinc-400 hover:text-white transition-colors" aria-label="Instagram">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                                </svg>
                            </a>
                        @endif

                        @if ($empresa->tiktok_url)
                            <a href="{{ $empresa->tiktok_url }}" target="_blank" rel="noopener noreferrer"
                                class="text-zinc-400 hover:text-white transition-colors" aria-label="TikTok">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.26 8.26 0 004.84 1.55V6.79a4.85 4.85 0 01-1.07-.1z" />
                                </svg>
                            </a>
                        @endif

                        @if ($empresa->whatsapp_pedidos)
                            <a href="https://wa.me/{{ $empresa->whatsapp_pedidos }}" target="_blank"
                                rel="noopener noreferrer" class="text-zinc-400 hover:text-[#25D366] transition-colors"
                                aria-label="WhatsApp">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                </svg>
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Columna 2: Tienda --}}
                <div>
                    <h3 class="text-white font-semibold text-sm uppercase tracking-wider mb-4">Tienda</h3>
                    <ul class="space-y-2 text-sm">
                        <li>
                            <a href="{{ route('catalogo') }}" wire:navigate class="hover:text-white transition-colors">
                                Catálogo
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('catalogo') }}" wire:navigate class="hover:text-white transition-colors">
                                Novedades
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('catalogo') }}" wire:navigate class="hover:text-white transition-colors">
                                Ofertas
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('catalogo') }}" wire:navigate class="hover:text-white transition-colors">
                                Mochilas Escolares
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('catalogo') }}" wire:navigate class="hover:text-white transition-colors">
                                Mochilas de Viaje
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- Columna 3: Ayuda --}}
                <div>
                    <h3 class="text-white font-semibold text-sm uppercase tracking-wider mb-4">Ayuda</h3>
                    <ul class="space-y-2 text-sm">
                        <li>
                            <a href="#" class="hover:text-white transition-colors">
                                Cómo comprar
                            </a>
                        </li>
                        <li>
                            <a href="#" class="hover:text-white transition-colors">
                                Envíos y delivery
                            </a>
                        </li>
                        <li>
                            <a href="#" class="hover:text-white transition-colors">
                                Cambios y devoluciones
                            </a>
                        </li>
                        <li>
                            <a href="#" class="hover:text-white transition-colors">
                                Preguntas frecuentes
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- Columna 4: Contacto --}}
                <div>
                    <h3 class="text-white font-semibold text-sm uppercase tracking-wider mb-4">Contacto</h3>
                    <ul class="space-y-3 text-sm">
                        @if ($empresa->telefono_contacto)
                            <li class="flex items-center gap-2 text-zinc-400">
                                <svg class="w-4 h-4 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                <a href="tel:{{ $empresa->telefono_contacto }}" class="hover:text-white transition-colors">
                                    {{ $empresa->telefono_contacto }}
                                </a>
                            </li>
                        @endif

                        @if ($empresa->email_contacto)
                            <li class="flex items-center gap-2 text-zinc-400">
                                <svg class="w-4 h-4 shrink-0 text-zinc-500" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <a href="mailto:{{ $empresa->email_contacto }}" class="hover:text-white transition-colors">
                                    {{ $empresa->email_contacto }}
                                </a>
                            </li>
                        @endif

                        @if ($empresa->whatsapp_pedidos)
                            <li class="flex items-center gap-2 text-zinc-400">
                                <svg class="w-4 h-4 shrink-0 text-[#25D366]" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                </svg>
                                <a href="https://wa.me/{{ $empresa->whatsapp_pedidos }}" target="_blank"
                                    rel="noopener noreferrer" class="hover:text-white transition-colors">
                                    +{{ $empresa->whatsapp_pedidos }}
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>

            </div>
        </div>
    </div>

    {{-- Banda inferior: copyright --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-zinc-500">
            <p>
                &copy; {{ date('Y') }} {{ $empresa->nombre_comercial }}. Todos los derechos reservados.
            </p>
            @if ($empresa->ruc)
                <p>RUC: {{ $empresa->ruc }}</p>
            @endif
            <div class="flex gap-4">
                <a href="#" class="hover:text-zinc-300 transition-colors">Política de privacidad</a>
                <a href="#" class="hover:text-zinc-300 transition-colors">Términos y condiciones</a>
            </div>
        </div>
    </div>
</footer>