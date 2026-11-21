<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen flex flex-col bg-white dark:bg-zinc-800">
        @include('layouts.publico.navbar')

        <!-- Contenido de E-Commerce -->
        <main class="flex-1 py-10 max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        <!-- Pie de página -->
        <x-footer-publico :empresa="$empresa" />

        <!-- WhatsApp flotante -->
        <x-whatsapp-flotante :empresa="$empresa" />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
