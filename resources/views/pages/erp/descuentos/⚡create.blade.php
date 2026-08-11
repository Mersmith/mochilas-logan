<?php

use App\Models\Descuento;
use App\Models\Producto;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nuevo Descuento')] class extends Component {
    public string $nombre = '';
    public string $porcentaje_descuento = '';
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public bool $activo = true;
    public array $productos_seleccionados = [];

    public function with(): array
    {
        return [
            'productos' => Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
        ];
    }

    public function guardar()
    {
        if (! auth()->user()->can('promociones.crear')) { // Asumiendo el permiso que pusimos
            abort(403);
        }

        $this->validate([
            'nombre' => 'required|string|max:255',
            'porcentaje_descuento' => 'required|integer|min:1|max:100',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'productos_seleccionados' => 'array',
            'productos_seleccionados.*' => 'exists:productos,id',
        ]);

        $descuento = Descuento::create([
            'nombre' => $this->nombre,
            'porcentaje_descuento' => $this->porcentaje_descuento,
            'fecha_inicio' => $this->fecha_inicio ?: null,
            'fecha_fin' => $this->fecha_fin ?: null,
            'activo' => $this->activo,
        ]);

        if (count($this->productos_seleccionados) > 0) {
            $descuento->productos()->sync($this->productos_seleccionados);
        }

        Flux::toast(variant: 'success', text: 'Descuento creado correctamente.');
        return redirect()->route('admin.descuentos.index');
    }
}; ?>

<div class="space-y-6 max-w-2xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo Descuento') }}</flux:heading>
            <flux:subheading>{{ __('Crea un nuevo descuento general para productos.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.descuentos.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardar" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        
        <flux:field>
            <flux:label>{{ __('Nombre del Descuento') }}</flux:label>
            <flux:input wire:model="nombre" placeholder="Ej. Cyber Days 2026" required />
            <flux:error name="nombre" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Porcentaje de Descuento (%)') }}</flux:label>
            <flux:input type="number" wire:model="porcentaje_descuento" placeholder="Ej. 15" min="1" max="100" required />
            <flux:error name="porcentaje_descuento" />
        </flux:field>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:field>
                <flux:label>{{ __('Fecha de Inicio (Opcional)') }}</flux:label>
                <flux:input type="datetime-local" wire:model="fecha_inicio" />
                <flux:error name="fecha_inicio" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Fecha de Fin (Opcional)') }}</flux:label>
                <flux:input type="datetime-local" wire:model="fecha_fin" />
                <flux:error name="fecha_fin" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('Aplicar a Productos (Opcional)') }}</flux:label>
            <flux:select wire:model="productos_seleccionados" multiple placeholder="Selecciona productos...">
                @foreach($productos as $producto)
                    <flux:select.option value="{{ $producto->id }}">{{ $producto->nombre }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="productos_seleccionados" />
        </flux:field>

        <flux:switch wire:model="activo" label="Descuento Activo" description="Los descuentos inactivos no se pueden aplicar a los productos." />

        <div class="flex justify-end pt-4">
            <flux:button variant="primary" type="submit" icon="check">{{ __('Guardar') }}</flux:button>
        </div>
    </form>
</div>

<div class="space-y-6 max-w-2xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo Descuento') }}</flux:heading>
            <flux:subheading>{{ __('Crea un nuevo descuento general para productos.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.descuentos.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardar" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        
        <flux:field>
            <flux:label>{{ __('Nombre del Descuento') }}</flux:label>
            <flux:input wire:model="nombre" placeholder="Ej. Cyber Days 2026" required />
            <flux:error name="nombre" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Porcentaje de Descuento (%)') }}</flux:label>
            <flux:input type="number" wire:model="porcentaje_descuento" placeholder="Ej. 15" min="1" max="100" required />
            <flux:error name="porcentaje_descuento" />
        </flux:field>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:field>
                <flux:label>{{ __('Fecha de Inicio (Opcional)') }}</flux:label>
                <flux:input type="datetime-local" wire:model="fecha_inicio" />
                <flux:error name="fecha_inicio" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Fecha de Fin (Opcional)') }}</flux:label>
                <flux:input type="datetime-local" wire:model="fecha_fin" />
                <flux:error name="fecha_fin" />
            </flux:field>
        </div>

        <flux:switch wire:model="activo" label="Descuento Activo" description="Los descuentos inactivos no se pueden aplicar a los productos." />

        <div class="flex justify-end pt-4">
            <flux:button variant="primary" type="submit" icon="check">{{ __('Guardar') }}</flux:button>
        </div>
    </form>
</div>
