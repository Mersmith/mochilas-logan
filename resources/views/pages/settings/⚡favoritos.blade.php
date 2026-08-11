<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

new #[Title('Favoritos')] #[Layout('layouts.settings')] class extends Component {
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Favoritos') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Favoritos')" :subheading="__('Tus productos guardados')">
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 text-center text-zinc-500">
            Aún no has guardado productos en tus favoritos.
        </div>
    </x-pages::settings.layout>
</section>
