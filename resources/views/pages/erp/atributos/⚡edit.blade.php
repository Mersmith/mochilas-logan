<?php

use App\Models\Atributo;
use App\Models\AtributoValor;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Atributo')] class extends Component {
    public Atributo $atributo;
    public string $nombre = '';
    public bool $activo = true;
    public string $nuevoValor = '';

    public function mount(Atributo $atributo)
    {
        $this->atributo = $atributo;
        $this->nombre = $atributo->nombre;
        $this->activo = $atributo->activo;
    }

    public function agregarValor()
    {
        $this->validate([
            'nuevoValor' => 'required|string|max:255',
        ]);
        
        AtributoValor::firstOrCreate([
            'atributo_id' => $this->atributo->id,
            'valor' => $this->nuevoValor,
        ]);
        
        $this->nuevoValor = '';
        Flux::toast(variant: 'success', text: 'Valor agregado.');
    }

    public function removerValor($valorId)
    {
        AtributoValor::where('id', $valorId)->delete();
        Flux::toast(variant: 'success', text: 'Valor eliminado.');
    }

    public function guardar()
    {
        if (! auth()->user()->can('atributos.editar')) {
            abort(403);
        }

        $this->validate([
            'nombre' => 'required|string|max:255|unique:atributos,nombre,' . $this->atributo->id,
            'activo' => 'boolean',
        ]);

        $this->atributo->update([
            'nombre' => $this->nombre,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Registro actualizado correctamente.');
        return redirect()->route('admin.atributos.index');
    }
}; ?>

<div class="space-y-6 max-w-2xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Atributo') }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos del atributo y sus valores.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.atributos.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardar" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        <flux:field>
            <flux:label>{{ __('Nombre') }}</flux:label>
            <flux:input wire:model="nombre" placeholder="Ej. Talla, Color..." required />
            <flux:error name="nombre" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Valores del atributo') }}</flux:label>
            <div class="flex gap-2 mt-1">
                <flux:input wire:model="nuevoValor" placeholder="Ej. Rojo, XL..." class="flex-1" wire:keydown.enter.prevent="agregarValor" />
                <flux:button wire:click.prevent="agregarValor">{{ __('Agregar') }}</flux:button>
            </div>
            <flux:error name="nuevoValor" />
            
            <div class="flex flex-wrap gap-2 mt-4">
                @foreach($atributo->valores as $valorObj)
                    <span class="inline-flex items-center gap-1.5 py-1.5 pl-3 pr-2 rounded-full text-sm font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                        {{ $valorObj->valor }}
                        <button type="button" wire:click="removerValor({{ $valorObj->id }})" class="shrink-0 h-4 w-4 rounded-full inline-flex items-center justify-center text-zinc-400 hover:bg-zinc-200 hover:text-zinc-500 focus:outline-none focus:bg-zinc-500 focus:text-white">
                            <flux:icon.x-mark class="size-3" />
                        </button>
                    </span>
                @endforeach
            </div>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Estado') }}</flux:label>
            <div class="flex items-center gap-3 h-10 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3">
                <flux:switch wire:model="activo" />
                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $activo ? __('Activo') : __('Inactivo') }}
                </span>
            </div>
        </flux:field>

        <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button variant="ghost" href="{{ route('admin.atributos.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
            <flux:button variant="primary" type="submit" icon="check">{{ __('Actualizar') }}</flux:button>
        </div>
    </form>
</div>
