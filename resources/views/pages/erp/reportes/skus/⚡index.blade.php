<?php

use App\Models\Variacion;
use App\Models\ListaPrecio;
use App\Models\UnidadMedida;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Flux\Flux;

new #[Title('Catálogo de SKUs')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $lista_precio_id = '';

    #[Url]
    public string $unidad_medida_id = '';

    #[Url]
    public string $con_descuento = '';

    #[Url]
    public int $perPage = 20;

    public function updating($property)
    {
        if (in_array($property, ['search', 'lista_precio_id', 'unidad_medida_id', 'con_descuento', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'lista_precio_id', 'unidad_medida_id', 'con_descuento']);
        $this->perPage = 20;
        $this->resetPage();
    }

    #[Computed]
    public function skus()
    {
        $query = Variacion::with(['producto.media', 'valores.atributo', 'precios.listaPrecio', 'producto.empaques.unidadMedida', 'producto.descuentos'])
            ->where('activo', true)
            ->when($this->search, function ($q) {
                $q->where('sku', 'like', '%' . $this->search . '%')
                  ->orWhereHas('producto', function ($pq) {
                      $pq->where('nombre', 'like', '%' . $this->search . '%');
                  });
            });

        if ($this->lista_precio_id) {
            $query->whereHas('precios', function ($q) {
                $q->where('lista_precio_id', $this->lista_precio_id);
            });
        }

        if ($this->unidad_medida_id) {
            $query->whereHas('producto.empaques', function ($q) {
                $q->where('unidad_medida_id', $this->unidad_medida_id);
            });
        }

        if ($this->con_descuento === 'si') {
            $query->whereHas('producto.descuentos');
        } elseif ($this->con_descuento === 'no') {
            $query->doesntHave('producto.descuentos');
        }

        return $query->paginate($this->perPage);
    }

    #[Computed]
    public function listasPrecio()
    {
        return ListaPrecio::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function unidadesMedida()
    {
        return UnidadMedida::where('activo', true)->orderBy('nombre')->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Catálogo Detallado de SKUs') }}</flux:heading>
            <flux:subheading>{{ __('Explora cada SKU con sus precios comerciales y descuentos aplicables.') }}</flux:subheading>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar por producto o SKU...') }}" />
            </div>
            <flux:select wire:model.live="lista_precio_id" class="sm:w-44" placeholder="{{ __('Lista de Precio') }}">
                <option value="">{{ __('Cualquier Lista') }}</option>
                @foreach($this->listasPrecio as $lp)
                    <option value="{{ $lp->id }}">{{ $lp->nombre }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="unidad_medida_id" class="sm:w-44" placeholder="{{ __('Unidad Medida') }}">
                <option value="">{{ __('Cualquier Unidad') }}</option>
                @foreach($this->unidadesMedida as $um)
                    <option value="{{ $um->id }}">{{ $um->nombre }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="con_descuento" class="sm:w-44" placeholder="{{ __('Descuentos') }}">
                <option value="">{{ __('Todos') }}</option>
                <option value="si">{{ __('Con Descuento') }}</option>
                <option value="no">{{ __('Sin Descuento') }}</option>
            </flux:select>
            <div>
                <flux:button class="!bg-blue-600 !text-white hover:!bg-blue-700 border-none w-full" wire:click="resetFiltros" icon="arrow-path">
                    {{ __('Limpiar') }}
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden flex flex-col">
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-3">{{ __('Imagen') }}</th>
                        <th class="p-3">{{ __('Producto / SKU') }}</th>
                        <th class="p-3">{{ __('Atributos') }}</th>
                        <th class="p-3">{{ __('Precios por Lista') }}</th>
                        <th class="p-3 text-center">{{ __('Descuentos Activos') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->skus as $sku)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="p-3 align-top">
                                @if($sku->producto->getFirstMediaUrl('imagen_principal', 'thumb'))
                                    <img src="{{ $sku->producto->getFirstMediaUrl('imagen_principal', 'thumb') }}" alt="{{ $sku->producto->nombre }}" class="w-12 h-12 object-cover rounded-md border border-zinc-200 dark:border-zinc-700">
                                @else
                                    <div class="w-12 h-12 bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center rounded-md border border-zinc-200 dark:border-zinc-700">
                                        <flux:icon.photo class="size-6 text-zinc-400" />
                                    </div>
                                @endif
                            </td>
                            <td class="p-3 align-top">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $sku->producto->nombre }}</div>
                                <div class="text-xs text-zinc-500 font-mono">{{ $sku->sku }}</div>
                                
                                @if($sku->producto->empaques->count() > 0)
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach($sku->producto->empaques as $emp)
                                            <span class="text-[10px] bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 px-1 rounded">
                                                {{ $emp->unidadMedida->nombre }} (x{{ $emp->cantidad }})
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="p-3 align-top text-zinc-600 dark:text-zinc-400">
                                <div class="flex flex-col gap-0.5">
                                    @forelse($sku->valores as $val)
                                        <span class="text-xs">
                                            <strong>{{ $val->atributo->nombre }}:</strong> {{ $val->valor }}
                                        </span>
                                    @empty
                                        <span class="text-zinc-400 text-xs">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="p-3 align-top">
                                <div class="flex flex-col gap-1">
                                    @forelse($sku->precios as $precio)
                                        <div class="flex items-center justify-between text-xs bg-zinc-50 dark:bg-zinc-800 px-2 py-1 rounded">
                                            <span class="text-zinc-600 dark:text-zinc-400">{{ $precio->listaPrecio->nombre }}:</span>
                                            <span class="font-semibold text-zinc-900 dark:text-white">
                                                {{ $precio->listaPrecio->moneda === 'PEN' ? 'S/' : '$' }} {{ number_format($precio->precio, 2) }}
                                            </span>
                                        </div>
                                    @empty
                                        <span class="text-zinc-400 text-xs italic">{{ __('Sin precios asignados') }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="p-3 align-top text-center">
                                @if($sku->producto->descuentos->count() > 0)
                                    <div class="flex flex-col items-center gap-1">
                                        @foreach($sku->producto->descuentos as $desc)
                                            <flux:badge color="emerald" size="sm">
                                                {{ $desc->tipo === 'porcentaje' ? $desc->valor . '%' : 'S/ ' . $desc->valor }}
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-zinc-400 text-xs">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-zinc-500">
                                {{ __('No hay SKUs que coincidan con tu búsqueda.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->skus->hasPages())
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800">
                {{ $this->skus->links() }}
            </div>
        @endif
    </div>
</div>
