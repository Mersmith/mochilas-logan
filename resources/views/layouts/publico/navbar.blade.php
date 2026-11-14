<!-- Navbar Pública E-Commerce -->
<flux:header class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 shadow-xs block! h-auto! px-0! py-0!">
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-2.5 sm:py-3 flex flex-wrap items-center justify-between gap-y-3">
        <!-- 1. Logo & Enlace a Tienda (Visible en Móvil y Computadora) -->
        <div class="flex items-center gap-2 sm:gap-6 order-1">
            <x-app-logo href="{{ route('home') }}" wire:navigate />

            <flux:navbar class="-mb-px flex items-center">
                <flux:navbar.item :href="route('catalogo')" :current="request()->routeIs('catalogo')" wire:navigate
                    class="font-semibold text-sm">
                    {{ __('Tienda') }}
                </flux:navbar.item>
            </flux:navbar>
        </div>

        <!-- 2. Menú de Usuario / Login / Bolsa de Compras (A la derecha) -->
        <div class="flex items-center gap-2 order-2 md:order-3">
            <!-- Bolsa de Compras -->
            @php
                $cartCount = \App\Services\CartService::count();
            @endphp
            <a href="{{ route('carrito') }}" wire:navigate class="relative inline-block"
                title="{{ __('Bolsa de compras') }}">
                <flux:button variant="ghost" icon="shopping-bag" class="relative size-9" :as="'span'">
                    @if($cartCount > 0)
                        <span
                            class="absolute -top-1.5 -right-1.5 bg-rose-600 text-white rounded-full text-[9px] font-extrabold h-4.5 w-4.5 flex items-center justify-center shadow-sm">
                            {{ $cartCount }}
                        </span>
                    @endif
                </flux:button>
            </a>

            @auth
                <!-- Si el usuario es administrador, mostrar link a Dashboard -->
                @if(in_array(auth()->user()->role, ['admin', 'employee']))
                    <flux:navbar class="py-0! hidden sm:flex">
                        <flux:navbar.item :href="route('admin.dashboard')" wire:navigate
                            class="text-zinc-600 dark:text-zinc-400 hover:text-black dark:hover:text-white font-medium text-xs">
                            {{ __('Panel de Control (Admin)') }}
                        </flux:navbar.item>
                    </flux:navbar>
                @endif
                <x-desktop-user-menu />
            @else
                <!-- Menú Desplegable con Icono de Usuario (Móvil y Computadora) -->
                <flux:dropdown position="bottom" align="end">
                    <flux:button variant="ghost" icon="user" class="relative size-9" title="{{ __('Mi Cuenta') }}" />

                    <flux:menu class="w-48">
                        <div class="px-2 py-1.5 text-xs text-zinc-500 font-medium">
                            {{ __('Mi Cuenta') }}
                        </div>
                        <flux:menu.separator />
                        <flux:menu.item :href="route('login')" wire:navigate icon="arrow-right-end-on-rectangle">
                            {{ __('Ingresar') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('register')" wire:navigate icon="user-plus">
                            {{ __('Crear Cuenta') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endauth
        </div>

        <!-- 3. Buscador: En móvil se posiciona debajo a 100% de ancho con display flex; en desktop centrado -->
        <div class="w-full md:w-auto md:flex-1 order-3 md:order-2 flex justify-center md:px-6">
            <div class="w-full max-w-none md:max-w-2xl">
                <livewire:ecommerce.search-bar />
            </div>
        </div>
    </div>
</flux:header>
