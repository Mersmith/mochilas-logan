<?php

use App\Models\GuiaInventario;
use App\Models\Almacen;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;

new #[Title('Guías de Inventario')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $tipoMovimiento = '';
    public string $almacenId = '';

    /**
     * Reset pagination when filters change.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTipoMovimiento(): void
    {
        $this->resetPage();
    }

    public function updatedAlmacenId(): void
    {
        $this->resetPage();
    }

    /**
     * Get the filtered guias.
     */
    #[Computed]
    public function guias()
    {
        return GuiaInventario::with(['tipoDocumento', 'almacenOrigen', 'almacenDestino', 'creador'])
            ->when($this->search, function ($query) {
                $query->where('serie', 'like', '%' . $this->search . '%')
                      ->orWhere('correlativo', 'like', '%' . $this->search . '%');
            })
            ->when($this->tipoMovimiento, function ($query) {
                $query->where('tipo_movimiento', $this->tipoMovimiento);
            })
            ->when($this->almacenId, function ($query) {
                $query->where(function ($q) {
                    $q->where('almacen_origen_id', $this->almacenId)
                      ->orWhere('almacen_destino_id', $this->almacenId);
                });
            })
            ->orderBy('fecha_movimiento', 'desc')
            ->orderBy('correlativo', 'desc')
            ->paginate(10);
    }

    /**
     * Get warehouses for the filter.
     */
    #[Computed]
    public function almacenes()
    {
        return Almacen::where('activo', true)->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Guías de Inventario') }}</flux:heading>
            <flux:subheading>{{ __('Historial de movimientos de entrada, salida y transferencias.') }}</flux:subheading>
        </div>
        
        <flux:button variant="primary" icon="plus" :href="route('admin.guias.create')" wire:navigate>
            {{ __('Nueva Guía') }}
        </flux:button>
    </div>

    <!-- Filtros -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between bg-zinc-50 dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="flex flex-1 flex-col gap-4 sm:flex-row">
            <div class="w-full sm:w-64">
                <flux:input wire:model.live="search" placeholder="Buscar por serie o correlativo..." icon="magnifying-glass" />
            </div>

            <div class="w-full sm:w-48">
                <flux:select wire:model.live="tipoMovimiento" placeholder="Todos los movimientos">
                    <flux:select.option value="">{{ __('Todos los tipos') }}</flux:select.option>
                    <flux:select.option value="Entrada">{{ __('Entrada') }}</flux:select.option>
                    <flux:select.option value="Salida">{{ __('Salida') }}</flux:select.option>
                    <flux:select.option value="Transferencia">{{ __('Transferencia') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="w-full sm:w-64">
                <flux:select wire:model.live="almacenId" placeholder="Todos los almacenes">
                    <flux:select.option value="">{{ __('Todos los almacenes') }}</flux:option>
                    @foreach($this->almacenes as $almacen)
                        <flux:select.option value="{{ $almacen->id }}">{{ $almacen->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Tabla de Resultados -->
    <!-- Tabla de Resultados -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Documento') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Tipo') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Origen') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Destino') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Fecha') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Estado') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Registrado por') }}</th>
                        <th class="p-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->guias as $guia)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="p-4 font-medium text-zinc-900 dark:text-white">
                                {{ $guia->tipoDocumento->nombre }} ({{ $guia->serie }}-{{ str_pad($guia->correlativo, 6, '0', STR_PAD_LEFT) }})
                            </td>
                            <td class="p-4">
                                @if($guia->tipo_movimiento === 'Entrada')
                                    <flux:badge color="emerald">{{ __('Entrada') }}</flux:badge>
                                @elseif($guia->tipo_movimiento === 'Salida')
                                    <flux:badge color="rose">{{ __('Salida') }}</flux:badge>
                                @else
                                    <flux:badge color="blue">{{ __('Transferencia') }}</flux:badge>
                                @endif
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $guia->almacenOrigen?->nombre ?? '-' }}
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $guia->almacenDestino?->nombre ?? '-' }}
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $guia->fecha_movimiento->format('d/m/Y') }}
                            </td>
                            <td class="p-4">
                                @if($guia->estado === 'Borrador')
                                    <flux:badge color="zinc">{{ __('Borrador') }}</flux:badge>
                                @elseif($guia->estado === 'En Tránsito')
                                    <flux:badge color="warning">{{ __('En Tránsito') }}</flux:badge>
                                @elseif($guia->estado === 'Procesado')
                                    <flux:badge color="success">{{ __('Procesado') }}</flux:badge>
                                @else
                                    <flux:badge color="danger">{{ __('Anulado') }}</flux:badge>
                                @endif
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $guia->creador->name }}
                            </td>
                            <td class="p-4 text-right">
                                <flux:button variant="ghost" icon="eye" size="sm" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-zinc-500">
                                {{ __('No se encontraron guías de inventario.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->guias->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $this->guias->links() }}
            </div>
        @endif
    </div>
</div>
