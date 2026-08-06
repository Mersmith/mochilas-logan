<?php

use App\Models\Descuento;
use App\Models\Producto;
use App\Models\ProductoDescuento;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Descuentos')] class extends Component {
    // Parent Descuento Form
    public ?int $descuento_id = null;
    public string $nombre = '';
    public int $porcentaje_descuento = 0;
    public ?string $fecha_inicio = null;
    public ?string $fecha_fin = null;
    public bool $activo = true;

    // Child ProductoDescuento Form (for Modal)
    public ?int $manage_descuento_id = null;
    public string $manage_descuento_nombre = '';
    public ?int $producto_id = null;

    public function guardarDescuento(): void
    {
        if (!auth()->user()->can('descuentos.editar')) {
            abort(403, 'No tienes permiso para editar descuentos.');
        }

        $this->validate([
            'nombre' => 'required|string|max:255',
            'porcentaje_descuento' => 'required|integer|min:1|max:100',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'activo' => 'boolean',
        ]);

        if ($this->descuento_id) {
            $desc = Descuento::findOrFail($this->descuento_id);
            $desc->update([
                'nombre' => $this->nombre,
                'porcentaje_descuento' => $this->porcentaje_descuento,
                'fecha_inicio' => $this->fecha_inicio,
                'fecha_fin' => $this->fecha_fin,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Descuento actualizado.'));
        } else {
            Descuento::create([
                'nombre' => $this->nombre,
                'porcentaje_descuento' => $this->porcentaje_descuento,
                'fecha_inicio' => $this->fecha_inicio,
                'fecha_fin' => $this->fecha_fin,
                'activo' => $this->activo,
            ]);
            Flux::toast(variant: 'success', text: __('Descuento registrado.'));
        }

        $this->limpiarFormDescuento();
    }

    public function editarDescuento(int $id): void
    {
        $desc = Descuento::findOrFail($id);
        $this->descuento_id = $desc->id;
        $this->nombre = $desc->nombre;
        $this->porcentaje_descuento = $desc->porcentaje_descuento;
        // Format for HTML date input if not null
        $this->fecha_inicio = $desc->fecha_inicio ? \Carbon\Carbon::parse($desc->fecha_inicio)->format('Y-m-d\TH:i') : null;
        $this->fecha_fin = $desc->fecha_fin ? \Carbon\Carbon::parse($desc->fecha_fin)->format('Y-m-d\TH:i') : null;
        $this->activo = $desc->activo;
    }

    public function eliminarDescuento(int $id): void
    {
        if (!auth()->user()->can('descuentos.editar')) {
            abort(403, 'No tienes permiso para eliminar descuentos.');
        }

        $desc = Descuento::findOrFail($id);
        $desc->delete();
        Flux::toast(variant: 'success', text: __('Descuento eliminado.'));
    }

    public function limpiarFormDescuento(): void
    {
        $this->descuento_id = null;
        $this->nombre = '';
        $this->porcentaje_descuento = 0;
        $this->fecha_inicio = null;
        $this->fecha_fin = null;
        $this->activo = true;
    }

    // --- MANAGE PRODUCTOS EN DESCUENTO ---

    public function manageProductos(int $id): void
    {
        $desc = Descuento::findOrFail($id);
        $this->manage_descuento_id = $desc->id;
        $this->manage_descuento_nombre = $desc->nombre;
        $this->producto_id = null;
        
        $this->modal('modal-productos')->show();
    }

    public function agregarProducto(): void
    {
        if (!auth()->user()->can('descuentos.editar')) {
            abort(403, 'No tienes permiso para editar descuentos.');
        }

        $this->validate([
            'producto_id' => 'required|exists:productos,id',
        ]);

        // Prevent duplicate assignment
        $exists = ProductoDescuento::where('descuento_id', $this->manage_descuento_id)
            ->where('producto_id', $this->producto_id)
            ->exists();

        if ($exists) {
            Flux::toast(variant: 'danger', text: __('Este producto ya tiene este descuento asignado.'));
            return;
        }

        ProductoDescuento::create([
            'descuento_id' => $this->manage_descuento_id,
            'producto_id' => $this->producto_id,
        ]);

        $this->producto_id = null;
        Flux::toast(variant: 'success', text: __('Producto añadido al descuento.'));
    }

    public function quitarProducto(int $id): void
    {
        if (!auth()->user()->can('descuentos.editar')) {
            abort(403, 'No tienes permiso para editar descuentos.');
        }

        $pd = ProductoDescuento::findOrFail($id);
        $pd->delete();
        Flux::toast(variant: 'success', text: __('Producto removido del descuento.'));
    }

    #[Computed]
    public function descuentos()
    {
        return Descuento::orderBy('nombre', 'asc')->get(); // Using standard model, no relations preloaded if they don't exist yet
    }

    #[Computed]
    public function productosDisponibles()
    {
        return Producto::orderBy('nombre', 'asc')->get();
    }

    #[Computed]
    public function productosConDescuentoActual()
    {
        if (!$this->manage_descuento_id) {
            return [];
        }
        return ProductoDescuento::with('producto')
            ->where('descuento_id', $this->manage_descuento_id)
            ->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Descuentos') }}</flux:heading>
            <flux:subheading>{{ __('Crea campañas de descuentos por porcentaje y asígnalas a tus productos.') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- FORMULARIO DESCUENTO PADRE -->
        @can('descuentos.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $descuento_id ? __('Editar Descuento') : __('Nuevo Descuento') }}</flux:heading>
                
                <form wire:submit.prevent="guardarDescuento" class="space-y-4">
                    <flux:input wire:model="nombre" :label="__('Nombre de la Campaña')" placeholder="Ej. Black Friday, Cierra Puertas..." required />
                    
                    <flux:input type="number" wire:model="porcentaje_descuento" :label="__('Porcentaje (%)')" min="1" max="100" required />

                    <div class="grid grid-cols-2 gap-4">
                        <flux:input type="datetime-local" wire:model="fecha_inicio" :label="__('Fecha Inicio')" />
                        <flux:input type="datetime-local" wire:model="fecha_fin" :label="__('Fecha Fin')" />
                    </div>

                    <flux:checkbox wire:model="activo" :label="__('Activo')" />

                    <div class="flex gap-4 pt-2">
                        @if($descuento_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarFormDescuento">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $descuento_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan

        <!-- LISTA DESCUENTOS -->
        <div class="{{ auth()->user()->can('descuentos.editar') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Campañas Registradas') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Nombre') }}</th>
                            <th class="p-3 text-center">{{ __('Descuento') }}</th>
                            <th class="p-3">{{ __('Fechas') }}</th>
                            <th class="p-3 text-center">{{ __('Estado') }}</th>
                            @can('descuentos.editar')
                                <th class="p-3 text-right">{{ __('Acciones') }}</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->descuentos as $desc)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">{{ $desc->nombre }}</td>
                                <td class="p-3 text-center text-zinc-900 dark:text-white font-bold">
                                    -{{ $desc->porcentaje_descuento }}%
                                </td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400 text-xs">
                                    @if($desc->fecha_inicio)
                                        <div>Del: {{ \Carbon\Carbon::parse($desc->fecha_inicio)->format('d/m/Y H:i') }}</div>
                                    @else
                                        <div>Del: -</div>
                                    @endif
                                    @if($desc->fecha_fin)
                                        <div>Al: {{ \Carbon\Carbon::parse($desc->fecha_fin)->format('d/m/Y H:i') }}</div>
                                    @else
                                        <div>Al: -</div>
                                    @endif
                                </td>
                                <td class="p-3 text-center">
                                    @if($desc->activo)
                                        <flux:badge color="success">{{ __('Activo') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                    @endif
                                </td>
                                @can('descuentos.editar')
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" size="sm" wire:click.prevent="manageProductos({{ $desc->id }})">
                                            {{ __('Productos') }}
                                        </flux:button>
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editarDescuento({{ $desc->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarDescuento({{ $desc->id }})" wire:confirm="¿Está seguro de eliminar esta campaña?" />
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('descuentos.editar') ? 5 : 4 }}" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay descuentos registrados.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL PARA GESTIONAR PRODUCTOS DEL DESCUENTO -->
    <flux:modal name="modal-productos" class="w-full max-w-3xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Productos con Descuento: ') }} {{ $manage_descuento_nombre }}</flux:heading>
                <flux:subheading>{{ __('Agrega o quita productos de esta campaña de descuento.') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- FORM AÑADIR PRODUCTO -->
                <div>
                    <form wire:submit.prevent="agregarProducto" class="space-y-4">
                        <flux:select wire:model="producto_id" :label="__('Seleccionar Producto')" required searchable>
                            <flux:select.option value="">{{ __('Seleccione un producto...') }}</flux:select.option>
                            @foreach($this->productosDisponibles as $prod)
                                <flux:select.option :value="$prod->id">{{ $prod->nombre }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="flex pt-2">
                            <flux:button variant="primary" type="submit" class="w-full" icon="plus">
                                {{ __('Añadir al Descuento') }}
                            </flux:button>
                        </div>
                    </form>
                </div>

                <!-- LISTA PRODUCTOS -->
                <div>
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 h-[350px] overflow-y-auto">
                        @if(count($this->productosConDescuentoActual) > 0)
                            <ul class="space-y-2">
                                @foreach($this->productosConDescuentoActual as $pd)
                                    <li class="flex items-center justify-between p-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-sm">
                                        <div class="flex items-center gap-3 overflow-hidden">
                                            <div class="w-8 h-8 rounded bg-zinc-100 flex items-center justify-center shrink-0">
                                                <flux:icon.cube class="w-4 h-4 text-zinc-400" />
                                            </div>
                                            <span class="font-medium text-sm truncate">{{ $pd->producto->nombre ?? 'Producto Eliminado' }}</span>
                                        </div>
                                        <div class="flex items-center shrink-0 pl-2">
                                            <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="quitarProducto({{ $pd->id }})" title="Quitar descuento" />
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="flex flex-col items-center justify-center h-full text-zinc-500 text-sm space-y-2">
                                <flux:icon.inbox class="w-8 h-8 text-zinc-300" />
                                <p>{{ __('No hay productos en esta campaña.') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cerrar') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
