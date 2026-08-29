<?php

use App\Models\Atributo;
use App\Models\AtributoValor;
use App\Models\ListaPrecio;
use App\Models\Producto;
use App\Models\Variacion;
use App\Models\VariacionPrecio;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Producto $producto;

    // Generador
    public array $valoresSeleccionados = [];

    // Variaciones Existentes
    public array $variacionesData = [];

    public function mount(Producto $producto): void
    {
        $this->producto = $producto;
        $this->cargarVariacionesExistentes();
    }

    #[Computed]
    public function listaPrecios()
    {
        return ListaPrecio::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function atributos()
    {
        return Atributo::with('valores')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    public function cargarVariacionesExistentes(): void
    {
        $this->variacionesData = [];

        $variaciones = Variacion::with(['valores.atributo', 'precios'])
            ->where('producto_id', $this->producto->id)
            ->get();

        foreach ($variaciones as $var) {
            $precios = [];
            foreach ($this->listaPrecios as $lp) {
                $varPrecio = $var->precios->firstWhere('lista_precio_id', $lp->id);
                $precios[$lp->id] = $varPrecio ? $varPrecio->precio : 0.00;
            }

            $desc = $var->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');

            $this->variacionesData[] = [
                'id' => $var->id,
                'descripcion' => $desc ?: __('Sin Atributos'),
                'sku' => $var->sku,
                'codigo_barras' => $var->codigo_barras,
                'activo' => (bool) $var->activo,
                'precios' => $precios,
            ];
        }
    }

    public function generarVariaciones(): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

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

        $prod = $this->producto;

        DB::transaction(function () use ($valoresPorAtributo, $prod) {
            // Producto cartesiano de los grupos
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

            // Crear variaciones
            foreach ($combinaciones as $comb) {
                $variacionesExistentes = Variacion::with('valores')
                    ->where('producto_id', $prod->id)
                    ->get();

                $existeCombinacion = false;
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
                    continue;
                }

                // Generar SKU único
                $sku = strtoupper(Str::slug($prod->nombre) . '-' . Str::random(5));
                $var = Variacion::create([
                    'producto_id' => $prod->id,
                    'sku' => $sku,
                    'codigo_barras' => null,
                    'activo' => true,
                ]);

                // Asociar atributos
                $var->valores()->attach($comb);

                // Crear precios iniciales
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

    public function guardarVariaciones(): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

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

    public function eliminarVariacion(int $id): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $var = Variacion::findOrFail($id);
        $var->delete();

        Flux::toast(variant: 'success', text: __('Variación eliminada.'));
        $this->cargarVariacionesExistentes();
    }
}; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Columna Izquierda: Generador de Variaciones -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
        <div>
            <flux:heading size="lg">{{ __('Generador de Combinaciones') }}</flux:heading>
            <flux:subheading>{{ __('Crea automáticamente los SKUs combinando colores, tallas y atributos.') }}</flux:subheading>
        </div>

        <div class="space-y-4">
            @forelse($this->atributos as $attr)
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
            @empty
                <p class="text-xs text-zinc-400">{{ __('No hay atributos registrados.') }}</p>
            @endforelse
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
                                <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600" wire:click.prevent="eliminarVariacion({{ $var['id'] }})" title="{{ __('Eliminar variación') }}" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- SKU -->
                                <flux:input wire:model="variacionesData.{{ $index }}.sku" :label="__('SKU')" placeholder="Código de inventario..." required />

                                <!-- Código de Barras -->
                                <flux:input wire:model="variacionesData.{{ $index }}.codigo_barras" :label="__('Código de Barras (EAN)')" placeholder="Opcional..." />
                            </div>

                            <!-- Precios Diferenciados -->
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-lg border border-zinc-100 dark:border-zinc-800/80">
                                @foreach($this->listaPrecios as $lp)
                                    <flux:input
                                        wire:model="variacionesData.{{ $index }}.precios.{{ $lp->id }}"
                                        type="number"
                                        step="0.01"
                                        :label="$lp->nombre"
                                        placeholder="0.00" />
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
