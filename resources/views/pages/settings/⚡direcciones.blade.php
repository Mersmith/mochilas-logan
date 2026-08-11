<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

new #[Title('Mis Direcciones')] #[Layout('layouts.settings')] class extends Component {
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Mis Direcciones') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Mis Direcciones')" :subheading="__('Administra tus direcciones de envío')">
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 text-center text-zinc-500">
            Aún no tienes direcciones registradas.
        </div>
    </x-pages::settings.layout>
</section>
