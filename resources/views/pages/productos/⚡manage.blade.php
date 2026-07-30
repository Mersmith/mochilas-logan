<?php

use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\ProductoEmpaque;
use App\Models\Variacion;
use App\Models\VariacionPrecio;
use App\Models\ListaPrecio;
use App\Models\Atributo;
use App\Models\AtributoValor;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestionar Producto')] class extends Component {
    public Producto $producto;
    public string $activeTab = 'variaciones';

    // Empaques Properties
    public ?int $unidad_medida_id = null;
    public int $factor_conversion = 1;

    // Generador Properties
    public array $atributosSeleccionados = [];
    public array $valoresSeleccionados = [];
    
    // Variaciones Guardadas Edit Properties
    public array $variacionesData = [];

    /**
     * Mount the component.
     */
    public function mount(Producto $producto): void
    {
        $this->producto = $producto;
        $this->cargarVariacionesExistentes();
    }

    /**
     * Load existing variations and their prices into input arrays.
     */
    public function cargarVariacionesExistentes(): void
    {
        $this->variacionesData = [];
        
        $variaciones = Variacion::with(['valores.atributo', 'precios'])
            ->where('producto_id', $this->producto->id)
            ->get();
            
        foreach ($variaciones as $var) {
            $precios = [];
            foreach (ListaPrecio::all() as $lp) {
                $varPrecio = $var->precios->firstWhere('lista_precio_id', $lp->id);
                $precios[$lp->id] = $varPrecio ? $varPrecio->precio : 0.00;
            }
            
            $desc = $var->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
            
            $this->variacionesData[] = [
                'id' => $var->id,
                'descripcion' => $desc ?: 'Sin Atributos',
                'sku' => $var->sku,
                'codigo_barras' => $var->codigo_barras,
                'activo' => $var->activo,
                'precios' => $precios,
            ];
        }
    }

    /**
     * Add packaging setup.
     */
    public function agregarEmpaque(): void
    {
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

        Flux::toast(variant: 'success', text: __('Empaque agregado.'));
        $this->producto->load('empaques.unidadMedida');
    }

    /**
     * Remove packaging setup.
     */
    public function eliminarEmpaque(int $id): void
    {
        $empaque = ProductoEmpaque::findOrFail($id);
        $empaque->delete();
        
        Flux::toast(variant: 'success', text: __('Empaque eliminado.'));
        $this->producto->load('empaques.unidadMedida');
    }

    /**
     * Generate SKUs (cross-join combinations of selected values).
     */
    public function generarVariaciones(): void
    {
        if (empty($this->valoresSeleccionados)) {
            Flux::toast(variant: 'danger', text: __('Seleccione al menos un valor de atributo.'));
            return;
        }

        // Agrupar los valores seleccionados por su atributo
        $valoresPorAtributo = [];
        foreach ($this->valoresSeleccionados as $valorId => $seleccionado) {
            if ($seleccionado) {
                $valor = AtributoValor::find($valorId);
                if ($valor) {
                    $valoresPorAtributo[$valor->atributo_id][] = $valorId;
                }
            }
        }

        if (empty($valoresPorAtributo)) {
            Flux::toast(variant: 'danger', text: __('Seleccione al menos un valor de atributo.'));
            return;
        }

        // Obtener producto base
        $prod = $this->producto;

        DB::transaction(function () use ($valoresPorAtributo, $prod) {
            // Algoritmo para obtener el producto cartesiano de los grupos
            $combinaciones = [[]];
            foreach ($valoresPorAtributo as $grupoValores) {
                $temp = [];
                foreach ($combinaciones as $comb) {
                    foreach ($grupoValores as $valId) {
                        $temp[] = array_merge($comb, [$valId]);
                    }
                }
                $combinaciones = $temp;
            }

            // Crear las variaciones a partir de las combinaciones
            foreach ($combinaciones as $index => $comb) {
                // Verificar si ya existe una combinación igual para este producto
                $existeCombinacion = false;
                
                $variacionesExistentes = Variacion::with('valores')
                    ->where('producto_id', $prod->id)
                    ->get();
                    
                foreach ($variacionesExistentes as $vEx) {
                    $exIds = $vEx->valores->pluck('id')->toArray();
                    sort($exIds);
                    sort($comb);
                    if ($exIds === $comb) {
                        $existeCombinacion = true;
                        break;
                    }
                }

                if ($existeCombinacion) {
                    continue; // Saltar si ya existe
                }

                // Crear la variación
                $sku = strtoupper(Str::slug($prod->nombre) . '-' . Str::random(5));
                $var = Variacion::create([
                    'producto_id' => $prod->id,
                    'sku' => $sku,
                    'codigo_barras' => null,
                    'activo' => true,
                ]);

                // Asociar atributos
                $var->valores()->attach($comb);

                // Crear precios iniciales en 0.00
                foreach (ListaPrecio::all() as $lp) {
                    VariacionPrecio::create([
                        'variacion_id' => $var->id,
                        'lista_precio_id' => $lp->id,
                        'precio' => 0.00,
                        'simbolo' => 'S/',
                    ]);
                }
            }
        });

        $this->valoresSeleccionados = [];
        $this->cargarVariacionesExistentes();
        Flux::toast(variant: 'success', text: __('Combinaciones generadas correctamente.'));
    }

    /**
     * Save all variations details (SKU, barcode, prices).
     */
    public function guardarVariaciones(): void
    {
        DB::transaction(function () {
            foreach ($this->variacionesData as $data) {
                $var = Variacion::findOrFail($data['id']);
                $var->update([
                    'sku' => $data['sku'],
                    'codigo_barras' => $data['codigo_barras'] ?: null,
                    'activo' => $data['activo'],
                ]);

                foreach ($data['precios'] as $lpId => $precio) {
                    VariacionPrecio::updateOrCreate(
                        ['variacion_id' => $var->id, 'lista_precio_id' => $lpId],
                        ['precio' => $precio ?: 0.00]
                    );
                }
            }
        });

        Flux::toast(variant: 'success', text: __('Variaciones y precios actualizados correctamente.'));
        $this->cargarVariacionesExistentes();
    }

    /**
     * Delete a single variation.
     */
    public function eliminarVariacion(int $id): void
    {
        $var = Variacion::findOrFail($id);
        $var->delete();
        
        Flux::toast(variant: 'success', text: __('Variación eliminada.'));
        $this->cargarVariacionesExistentes();
    }

    /**
     * Computed properties.
     */
    #[Computed]
    public function listaPrecios()
    {
        return ListaPrecio::all();
    }

    #[Computed]
    public function unidadesMedida()
    {
        return UnidadMedida::all();
    }

    #[Computed]
    public function atributos()
    {
        return Atributo::with('valores')->get();
    }
}; ?>

<div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $producto->nombre }}</flux:heading>
            <flux:subheading>{{ __('Gestión de variaciones, precios y presentaciones de empaque para el producto.') }}</flux:subheading>
        </div>
        
        <flux:button variant="ghost" icon="arrow-left" :href="route('admin.productos.index')" wire:navigate>
            {{ __('Volver al catálogo') }}
        </flux:button>
    </div>

    <!-- Pestañas (Tabs) -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-700">
        <button wire:click.prevent="$set('activeTab', 'variaciones')" class="px-6 py-3 font-semibold text-sm border-b-2 transition-colors {{ $activeTab === 'variaciones' ? 'border-black text-black dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
            {{ __('Variaciones y Precios') }}
        </button>
        <button wire:click.prevent="$set('activeTab', 'empaques')" class="px-6 py-3 font-semibold text-sm border-b-2 transition-colors {{ $activeTab === 'empaques' ? 'border-black text-black dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
            {{ __('Presentaciones de Empaque') }}
        </button>
    </div>

    <!-- Contenido Pestaña A: Variaciones -->
    @if($activeTab === 'variaciones')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Columna Izquierda: Generador de Variaciones -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <div>
                    <flux:heading size="lg">{{ __('Generador de Combinaciones') }}</flux:heading>
                    <flux:subheading>{{ __('Crea automáticamente los SKUs combinando colores, tallas y atributos.') }}</flux:subheading>
                </div>

                <div class="space-y-4">
                    @foreach($this->atributos as $attr)
                        <div class="space-y-2 border-b border-zinc-100 dark:border-zinc-800 pb-3 last:border-b-0">
                            <span class="text-xs font-semibold uppercase text-zinc-500">{{ $attr->nombre }}</span>
                            <div class="grid grid-cols-2 gap-2 mt-1">
                                @foreach($attr->valores as $val)
                                    <div class="flex items-center gap-2">
                                        <flux:checkbox wire:model="valoresSeleccionados.{{ $val->id }}" :label="$val->valor" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <flux:button variant="primary" class="w-full" icon="sparkles" wire:click.prevent="generarVariaciones">
                    {{ __('Generar Variaciones') }}
                </flux:button>
            </div>

            <!-- Columna Derecha: Configuración de SKUs y Precios -->
            <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm">
                <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3">
                    <div>
                        <flux:heading size="lg">{{ __('Variaciones y Precios Activos') }}</flux:heading>
                        <flux:subheading>{{ __('Edita los SKUs, códigos de barra y los precios para cada lista.') }}</flux:subheading>
                    </div>
                </div>

                @if(empty($variacionesData))
                    <div class="text-center py-12 text-zinc-500">
                        {{ __('No hay variaciones generadas. Utiliza el generador de la izquierda.') }}
                    </div>
                @else
                    <form wire:submit.prevent="guardarVariaciones" class="space-y-6">
                        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach($variacionesData as $index => $var)
                                <div class="py-4 first:pt-0 last:pb-0 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $var['descripcion'] }}</span>
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarVariacion({{ $var['id'] }})" />
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- SKU -->
                                        <flux:input wire:model="variacionesData.{{ $index }}.sku" :label="__('SKU')" placeholder="Código de inventario..." />
                                        
                                        <!-- Código de Barras -->
                                        <flux:input wire:model="variacionesData.{{ $index }}.codigo_barras" :label="__('Código de Barras (EAN)')" placeholder="Opcional..." />
                                    </div>

                                    <!-- Precios Diferenciados -->
                                    <div class="grid grid-cols-2 gap-4 bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-lg border border-zinc-100 dark:border-zinc-800/80">
                                        @foreach($this->listaPrecios as $lp)
                                            <flux:input 
                                                wire:model="variacionesData.{{ $index }}.precios.{{ $lp->id }}" 
                                                type="number" 
                                                step="0.01" 
                                                :label="$lp->nombre" 
                                                placeholder="S/ 0.00" />
                                        @endforeach
                                    </div>

                                    <!-- Activo -->
                                    <div class="flex items-center gap-2">
                                        <flux:checkbox wire:model="variacionesData.{{ $index }}.activo" :label="__('Variación disponible para venta')" />
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex justify-end gap-3 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                            <flux:button variant="primary" type="submit" icon="check">
                                {{ __('Guardar SKUs y Precios') }}
                            </flux:button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <!-- Contenido Pestaña B: Empaques -->
    @if($activeTab === 'empaques')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Formulario de Asociación de Empaque -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <div>
                    <flux:heading size="lg">{{ __('Asociar Presentación') }}</flux:heading>
                    <flux:subheading>{{ __('Define en qué empaques se puede mover o almacenar el producto.') }}</flux:subheading>
                </div>

                <form wire:submit.prevent="agregarEmpaque" class="space-y-4">
                    <!-- Unidad de Medida -->
                    <flux:select wire:model="unidad_medida_id" :label="__('Empaque / Presentación')" placeholder="Seleccionar empaque...">
                        @foreach($this->unidadesMedida as $um)
                            <flux:select.option value="{{ $um->id }}">{{ $um->nombre }} ({{ $um->abreviacion }})</flux:select.option>
                        @endforeach
                    </flux:select>

                    <!-- Factor de Conversión -->
                    <flux:input wire:model="factor_conversion" type="number" min="1" :label="__('Factor de Conversión')" placeholder="Ej. 1 costal = 50 unidades base" />

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
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold">
                                <th class="pb-3">{{ __('Empaque') }}</th>
                                <th class="pb-3 text-center">{{ __('Abreviación') }}</th>
                                <th class="pb-3 text-center">{{ __('Factor de Conversión') }}</th>
                                <th class="pb-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <!-- Unidad Base Siempre Existe de forma implícita -->
                            <tr>
                                <td class="py-3 font-semibold text-zinc-900 dark:text-white">Unidad (Unidad Base)</td>
                                <td class="py-3 text-center text-zinc-500">UND</td>
                                <td class="py-3 text-center font-bold text-zinc-700 dark:text-zinc-300">1</td>
                                <td class="py-3 text-right text-zinc-400 italic text-xs pr-4">{{ __('Por defecto') }}</td>
                            </tr>
                            
                            @forelse($producto->empaques as $emp)
                                <tr>
                                    <td class="py-3 font-semibold text-zinc-900 dark:text-white">{{ $emp->unidadMedida->nombre }}</td>
                                    <td class="py-3 text-center text-zinc-500">{{ $emp->unidadMedida->abreviacion }}</td>
                                    <td class="py-3 text-center font-semibold text-zinc-700 dark:text-zinc-300">
                                        {{ $emp->factor_conversion }} {{ __('unidades') }}
                                    </td>
                                    <td class="py-3 text-right">
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarEmpaque({{ $emp->id }})" />
                                    </td>
                                </tr>
                            @empty
                                <!-- No extra packaging setups, standard unit only -->
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
