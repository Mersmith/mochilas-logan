<?php

use App\Models\Producto;
use App\Models\ProductoEmpaque;
use App\Models\UnidadMedida;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Producto $producto;

    public ?int $unidad_medida_id = null;
    public int $factor_conversion = 1;

    public function mount(Producto $producto): void
    {
        $this->producto = $producto;
    }

    #[Computed]
    public function unidadesMedida()
    {
        return UnidadMedida::where('activo', true)->orderBy('nombre')->get();
    }

    public function agregarEmpaque(): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $this->validate([
            'unidad_medida_id' => 'required|integer|exists:unidades_medida,id',
            'factor_conversion' => 'required|integer|min:1',
        ]);

        // Verificar duplicados
        $existe = ProductoEmpaque::where('producto_id', $this->producto->id)
            ->where('unidad_medida_id', $this->unidad_medida_id)
            ->exists();

        if ($existe) {
            Flux::toast(variant: 'danger', text: __('Esta presentación de empaque ya está configurada.'));
            return;
        }

        ProductoEmpaque::create([
            'producto_id' => $this->producto->id,
            'unidad_medida_id' => $this->unidad_medida_id,
            'factor_conversion' => $this->factor_conversion,
        ]);

        $this->unidad_medida_id = null;
        $this->factor_conversion = 1;

        Flux::toast(variant: 'success', text: __('Presentación asociada correctamente.'));
        $this->producto->load('empaques.unidadMedida');
    }

    public function eliminarEmpaque(int $id): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $empaque = ProductoEmpaque::findOrFail($id);
        $empaque->delete();

        Flux::toast(variant: 'success', text: __('Presentación eliminada.'));
        $this->producto->load('empaques.unidadMedida');
    }
}; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Formulario de Asociación de Empaque -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
        <div>
            <flux:heading size="lg">{{ __('Asociar Presentación') }}</flux:heading>
            <flux:subheading>{{ __('Define en qué empaques se puede mover o almacenar el producto.') }}</flux:subheading>
        </div>

        <form wire:submit.prevent="agregarEmpaque" class="space-y-4">
            <!-- Unidad de Medida -->
            <flux:field>
                <flux:label>{{ __('Empaque / Presentación') }}</flux:label>
                <flux:select wire:model="unidad_medida_id" required>
                    <flux:select.option value="" disabled>{{ __('Seleccionar empaque...') }}</flux:select.option>
                    @foreach($this->unidadesMedida as $um)
                        <flux:select.option :value="$um->id">{{ $um->nombre }} ({{ $um->abreviacion }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="unidad_medida_id" />
            </flux:field>

            <!-- Factor de Conversión -->
            <flux:field>
                <flux:label>{{ __('Factor de Conversión') }}</flux:label>
                <flux:input wire:model="factor_conversion" type="number" min="1" placeholder="Ej. 1 costal = 50 unidades base" required />
                <flux:error name="factor_conversion" />
            </flux:field>

            <flux:button variant="primary" type="submit" class="w-full" icon="plus">
                {{ __('Asociar Empaque') }}
            </flux:button>
        </form>
    </div>

    <!-- Tabla de Empaques Asociados -->
    <div class="md:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
        <flux:heading size="lg">{{ __('Presentaciones del Producto') }}</flux:heading>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-3">{{ __('Empaque') }}</th>
                        <th class="p-3 text-center">{{ __('Abreviación') }}</th>
                        <th class="p-3 text-center">{{ __('Factor de Conversión') }}</th>
                        <th class="p-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    <!-- Unidad Base Siempre Existe de forma implícita -->
                    <tr class="bg-zinc-50/30 dark:bg-zinc-800/20">
                        <td class="p-3 font-semibold text-zinc-900 dark:text-white">{{ __('Unidad (Unidad Base)') }}</td>
                        <td class="p-3 text-center text-zinc-500 font-mono">UND</td>
                        <td class="p-3 text-center font-bold text-zinc-700 dark:text-zinc-300">1</td>
                        <td class="p-3 text-right text-zinc-400 italic text-xs pr-4">{{ __('Por defecto') }}</td>
                    </tr>

                    @forelse($producto->empaques as $emp)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="p-3 font-semibold text-zinc-900 dark:text-white">{{ $emp->unidadMedida->nombre }}</td>
                            <td class="p-3 text-center text-zinc-500 font-mono">{{ $emp->unidadMedida->abreviacion }}</td>
                            <td class="p-3 text-center font-semibold text-zinc-700 dark:text-zinc-300">
                                {{ $emp->factor_conversion }} {{ __('unidades') }}
                            </td>
                            <td class="p-3 text-right">
                                <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600" wire:click.prevent="eliminarEmpaque({{ $emp->id }})" title="{{ __('Eliminar presentación') }}" />
                            </td>
                        </tr>
                    @empty
                        <!-- No extra packaging setups -->
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
