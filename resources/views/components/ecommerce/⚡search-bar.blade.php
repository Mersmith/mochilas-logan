<?php

use Livewire\Component;

new class extends Component
{
    public string $q = '';

    public function mount()
    {
        $this->q = request('q', '');
    }

    public function search()
    {
        // Si la cadena de búsqueda está vacía, redirigimos al catálogo sin el parámetro 'q'
        if (trim($this->q) === '') {
            $this->redirectRoute('catalogo', navigate: true);
        } else {
            $this->redirectRoute('catalogo', ['q' => $this->q], navigate: true);
        }
    }
};
?>

<form wire:submit.prevent="search" class="w-full relative">
    <flux:input wire:model="q" placeholder="Buscar mochila o modelo..." icon="magnifying-glass" class="w-full bg-zinc-100 dark:bg-zinc-800 border-none" />
</form>