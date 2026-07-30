<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <!-- Navbar Pública E-Commerce -->
        <flux:header container class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 shadow-xs">
            <!-- Logo Mochilas Logan -->
            <x-app-logo href="{{ route('catalogo') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden ml-6">
                <flux:navbar.item :href="route('catalogo')" :current="request()->routeIs('catalogo')" wire:navigate class="font-semibold text-sm">
                    {{ __('Tienda') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <!-- Menú de Usuario / Login -->
            <div class="flex items-center gap-4">
                @auth
                    <!-- Si el usuario es administrador, mostrar link a Dashboard -->
                    @if(in_array(auth()->user()->role, ['admin', 'employee']))
                        <flux:navbar class="py-0!">
                            <flux:navbar.item :href="route('admin.dashboard')" wire:navigate class="text-zinc-600 dark:text-zinc-400 hover:text-black dark:hover:text-white font-medium text-xs">
                                {{ __('Panel de Control (Admin)') }}
                            </flux:navbar.item>
                        </flux:navbar>
                    @endif
                    <x-desktop-user-menu />
                @else
                    <flux:navbar class="py-0! gap-2">
                        <flux:navbar.item :href="route('login')" wire:navigate class="text-sm font-semibold">
                            {{ __('Ingresar') }}
                        </flux:navbar.item>
                        <flux:navbar.item :href="route('register')" wire:navigate class="text-sm font-semibold bg-zinc-900 text-white dark:bg-white dark:text-zinc-950 rounded-lg px-4 py-2 hover:bg-zinc-800 dark:hover:bg-zinc-100 transition-colors">
                            {{ __('Crear Cuenta') }}
                        </flux:navbar.item>
                    </flux:navbar>
                @endauth
            </div>
        </flux:header>

        <!-- Contenido de E-Commerce -->
        <flux:main container class="py-10">
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
