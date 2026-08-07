<?php

use App\Models\Almacen;
use App\Models\Sede;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;

new #[Title('Nuevo Almacén')] class extends Component {
    public ?int $sede_id = null;
    public string $nombre = '';
    public string $ubicacion = '';
    public bool $activo = true;

    public function mount(): void
    {
        $sede = Sede::where('activo', true)->first();
        if ($sede) {
            $this->sede_id = $sede->id;
        }
    }

    #[Computed]
    public function sedes()
    {
        return Sede::where('activo', true)->orderBy('nombre')->get();
    }

    public function guardar()
    {
        if (! auth()->user()->can('almacenes.editar')) {
            abort(403);
        }

        $this->validate([
            'sede_id' => 'required|exists:sedes,id',
            'nombre' => 'required|string|max:255',
            'ubicacion' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]);

        Almacen::create([
            'sede_id' => $this->sede_id,
            'nombre' => $this->nombre,
            'ubicacion' => $this->ubicacion,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Almacén creado correctamente.');
        return redirect()->route('admin.almacenes.index');
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo Almacén') }}</flux:heading>
            <flux:subheading>{{ __('Registra un nuevo almacén para el sistema.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.almacenes.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <form wire:submit.prevent="guardar" class="grid grid-cols-1 gap-6">
            <flux:field>
                <flux:label>{{ __('Sede Perteneciente') }}</flux:label>
                <flux:select wire:model="sede_id" required>
                    @foreach($this->sedes as $sede)
                        <flux:select.option :value="$sede->id">{{ $sede->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="sede_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Nombre del Almacén') }}</flux:label>
                <flux:input wire:model="nombre" placeholder="Ej. Almacén Principal" required />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Ubicación Física') }}</flux:label>
                <flux:input wire:model="ubicacion" placeholder="Piso 1, Sección A..." />
                <flux:error name="ubicacion" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Estado') }}</flux:label>
                <div class="flex items-center gap-3 h-10">
                    <flux:switch wire:model="activo" />
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $activo ? __('Almacén activo') : __('Almacén inactivo') }}
                    </span>
                </div>
            </flux:field>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" href="{{ route('admin.almacenes.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    {{ __('Crear Almacén') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
