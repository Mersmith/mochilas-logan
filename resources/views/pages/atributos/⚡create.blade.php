<?php

use App\Models\Atributo;
use App\Models\AtributoValor;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nuevo Atributo')] class extends Component {
    public string $nombre = '';
    public bool $activo = true;
    public array $valores = [];
    public string $nuevoValor = '';

    public function agregarValor()
    {
        $this->validate([
            'nuevoValor' => 'required|string|max:255',
        ]);
        
        if (!in_array($this->nuevoValor, $this->valores)) {
            $this->valores[] = $this->nuevoValor;
        }
        $this->nuevoValor = '';
    }

    public function removerValor($index)
    {
        if (isset($this->valores[$index])) {
            unset($this->valores[$index]);
            $this->valores = array_values($this->valores);
        }
    }

    public function guardar()
    {
        if (! auth()->user()->can('atributos.editar')) {
            abort(403);
        }

        $this->validate([
            'nombre' => 'required|string|max:255|unique:atributos,nombre',
            'activo' => 'boolean',
        ]);

        $atributo = Atributo::create([
            'nombre' => $this->nombre,
            'activo' => $this->activo,
        ]);

        foreach ($this->valores as $valor) {
            AtributoValor::create([
                'atributo_id' => $atributo->id,
                'valor' => $valor,
            ]);
        }

        Flux::toast(variant: 'success', text: 'Registro creado correctamente.');
        return redirect()->route('admin.atributos.index');
    }
}; ?>

<div class="space-y-6 max-w-2xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo Atributo') }}</flux:heading>
            <flux:subheading>{{ __('Registra un nuevo atributo y sus posibles valores.') }}</flux:subheading>
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
            
            @if(count($valores) > 0)
                <div class="flex flex-wrap gap-2 mt-4">
                    @foreach($valores as $index => $valor)
                        <span class="inline-flex items-center gap-1.5 py-1.5 pl-3 pr-2 rounded-full text-sm font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ $valor }}
                            <button type="button" wire:click="removerValor({{ $index }})" class="shrink-0 h-4 w-4 rounded-full inline-flex items-center justify-center text-zinc-400 hover:bg-zinc-200 hover:text-zinc-500 focus:outline-none focus:bg-zinc-500 focus:text-white">
                                <flux:icon.x-mark class="size-3" />
                            </button>
                        </span>
                    @endforeach
                </div>
            @endif
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
            <flux:button variant="primary" type="submit" icon="check">{{ __('Guardar') }}</flux:button>
        </div>
    </form>
</div>
