<?php

use App\Models\Descuento;
use App\Models\Producto;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;

new #[Title('Nuevo Descuento')] class extends Component {
    public string $nombre = '';
    public string $porcentaje_descuento = '';
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public bool $activo = true;
    
    // Para la selección de productos
    public array $productos_seleccionados = [];
    public string $producto_id_agregar = '';

    #[Computed]
    public function productosDisponibles()
    {
        return Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']);
    }

    #[Computed]
    public function productosEnTabla()
    {
        if (empty($this->productos_seleccionados)) {
            return collect();
        }
        return Producto::whereIn('id', $this->productos_seleccionados)->get(['id', 'nombre']);
    }

    public function addProducto()
    {
        $this->validate([
            'producto_id_agregar' => 'required|exists:productos,id',
        ]);

        if (in_array($this->producto_id_agregar, $this->productos_seleccionados)) {
            Flux::toast(variant: 'warning', text: 'El producto ya está en la lista.');
            return;
        }

        $this->productos_seleccionados[] = $this->producto_id_agregar;
        $this->producto_id_agregar = '';
        Flux::toast(variant: 'success', text: 'Producto agregado a la lista temporal.');
    }

    public function removeProducto($id)
    {
        $id = (string) $id;
        $this->productos_seleccionados = array_values(array_filter($this->productos_seleccionados, fn($val) => $val !== $id));
        Flux::toast(variant: 'success', text: 'Producto removido de la lista temporal.');
    }

    public function guardar()
    {
        if (! auth()->user()->can('promociones.crear')) {
            abort(403);
        }

        $this->validate([
            'nombre' => 'required|string|max:255',
            'porcentaje_descuento' => 'required|integer|min:1|max:100',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'productos_seleccionados' => 'array',
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

<div class="space-y-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo Descuento') }}</flux:heading>
            <flux:subheading>{{ __('Crea un nuevo descuento general para productos.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.descuentos.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- COLUMNA IZQUIERDA: DATOS DEL DESCUENTO --}}
        <div class="lg:col-span-1">
            <form class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-5 h-fit">
                <flux:heading size="lg" class="mb-4">{{ __('Datos Principales') }}</flux:heading>
                
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

                <flux:switch wire:model="activo" label="Descuento Activo" description="Los descuentos inactivos no se aplican." />

                <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button type="button" class="w-full" wire:click="guardar" icon="check">{{ __('Guardar Descuento') }}</flux:button>
                </div>
            </form>
        </div>
        
        {{-- COLUMNA DERECHA: PRODUCTOS ASIGNADOS --}}
        <div class="lg:col-span-2 space-y-6">
            
            {{-- Formulario para Agregar Producto --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
                <flux:heading size="lg" class="mb-4">{{ __('Agregar Producto') }}</flux:heading>
                
                <form wire:submit.prevent="addProducto" class="flex flex-col sm:flex-row items-end gap-4">
                    <flux:field class="flex-1 w-full">
                        <flux:label>{{ __('Producto') }}</flux:label>
                        <flux:select wire:model="producto_id_agregar" placeholder="Seleccione un producto...">
                            <option value="">{{ __('Seleccione...') }}</option>
                            @foreach($this->productosDisponibles as $producto)
                                <option value="{{ $producto->id }}">{{ $producto->nombre }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="producto_id_agregar" />
                    </flux:field>
                    
                    <flux:button variant="primary" type="submit" icon="plus" class="w-full sm:w-auto">
                        {{ __('Agregar') }}
                    </flux:button>
                </form>
            </div>

            {{-- Tabla de Productos --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-zinc-200 dark:border-zinc-700 flex justify-between items-center bg-zinc-50 dark:bg-zinc-800/40">
                    <flux:heading size="md">{{ __('Productos Asignados') }}</flux:heading>
                    <span class="text-sm text-zinc-500 font-medium">{{ count($this->productos_seleccionados) }} ítems</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                                <th class="p-3">{{ __('Producto') }}</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse($this->productosEnTabla as $prodTabla)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="p-3">
                                        <div class="font-medium text-zinc-900 dark:text-white">
                                            {{ $prodTabla->nombre }}
                                        </div>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex justify-end">
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click="removeProducto({{ $prodTabla->id }})" title="Eliminar" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center py-12 text-zinc-500">
                                        {{ __('No hay productos asignados a este descuento.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</div>
