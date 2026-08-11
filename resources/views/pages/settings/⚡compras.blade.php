<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

new #[Title('Mis Compras')] #[Layout('layouts.settings')] class extends Component {
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Mis Compras') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Mis Compras')" :subheading="__('Revisa el estado y detalle de tus pedidos')">
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 text-center text-zinc-500">
            Cuando tengas pedidos, en esta sección podrás revisar su estado y detalles en una sola vista.
        </div>
    </x-pages::settings.layout>
</section>
