<?php

use App\Models\Atributo;
use App\Models\AtributoValor;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nuevo Atributo')] class extends Component {
    public string $nombre = '';
    public string $tipo_selector = 'texto'; // 'texto' or 'color_hex'
    public bool $activo = true;
    public array $valores = [];
    public string $nuevoValor = '';
    public ?string $nuevoColorHex = '#3b82f6';

    public function agregarValor()
    {
        $this->validate([
            'nuevoValor' => 'required|string|max:255',
            'nuevoColorHex' => 'nullable|string|max:20',
        ]);
        
        $esColor = $this->tipo_selector === 'color_hex';
        $val = trim($this->nuevoValor);

        // Check if not already in list
        $exists = collect($this->valores)->contains('valor', $val);
        if (!$exists) {
            $this->valores[] = [
                'valor' => $val,
                'codigo_color_hex' => $esColor ? ($this->nuevoColorHex ?: '#000000') : null,
            ];
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
            'tipo_selector' => 'required|in:texto,color_hex',
            'activo' => 'boolean',
        ]);

        $atributo = Atributo::create([
            'nombre' => $this->nombre,
            'tipo_selector' => $this->tipo_selector,
            'activo' => $this->activo,
        ]);

        foreach ($this->valores as $item) {
            AtributoValor::create([
                'atributo_id' => $atributo->id,
                'valor' => $item['valor'],
                'codigo_color_hex' => $this->tipo_selector === 'color_hex' ? ($item['codigo_color_hex'] ?? '#000000') : null,
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
            <flux:subheading>{{ __('Registra un nuevo atributo y define si se mostrará como selector de Color o Texto en la tienda.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.atributos.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardar" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:field>
                <flux:label>{{ __('Nombre del Atributo') }}</flux:label>
                <flux:input wire:model="nombre" placeholder="Ej. Color, Talla, Capacidad, Material..." required />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Tipo de Visualización') }}</flux:label>
                <flux:select wire:model.live="tipo_selector">
                    <option value="texto">{{ __('Botones de Texto (Ej. S, M, L, 20L, Cuero)') }}</option>
                    <option value="color_hex">{{ __('Selector de Color Hexadecimal (Círculos de color)') }}</option>
                </flux:select>
                <flux:error name="tipo_selector" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('Valores del atributo') }}</flux:label>
            <div class="flex items-center gap-2 mt-1">
                @if($tipo_selector === 'color_hex')
                    <div class="flex items-center gap-2 border border-zinc-200 dark:border-zinc-700 rounded-lg px-2 py-1.5 bg-zinc-50 dark:bg-zinc-800 shrink-0">
                        <input type="color" wire:model.live="nuevoColorHex" class="w-7 h-7 rounded-md cursor-pointer border-0 p-0 bg-transparent" title="Elegir color" />
                        <span class="text-xs font-mono text-zinc-500 uppercase">{{ $nuevoColorHex }}</span>
                    </div>
                @endif
                <flux:input wire:model="nuevoValor" placeholder="{{ $tipo_selector === 'color_hex' ? 'Nombre del color (Ej. Rojo Carmín, Azul Marino...)' : 'Ej. XL, 30 Litros, Lona...' }}" class="flex-1" wire:keydown.enter.prevent="agregarValor" />
                <flux:button wire:click.prevent="agregarValor" icon="plus">{{ __('Agregar') }}</flux:button>
            </div>
            <flux:error name="nuevoValor" />
            
            @if(count($valores) > 0)
                <div class="flex flex-wrap gap-2 mt-4">
                    @foreach($valores as $index => $item)
                        <span class="inline-flex items-center gap-2 py-1.5 pl-2.5 pr-2 rounded-full text-sm font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-sm">
                            @if(!empty($item['codigo_color_hex']))
                                <span class="size-3.5 rounded-full border border-black/20 dark:border-white/20 shadow-inner" style="background-color: {{ $item['codigo_color_hex'] }};"></span>
                            @endif
                            <span>{{ $item['valor'] }}</span>
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
