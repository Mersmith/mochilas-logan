<?php

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Inventario;
use App\Models\Kardex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Historial de Ventas')] class extends Component {
    use WithPagination;

    public string $search = '';
    public ?int $selectedVentaId = null;
    public bool $showDetailModal = false;

    /**
     * Reset pagination on search change.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Get filtered sales.
     */
    #[Computed]
    public function ventas()
    {
        return Venta::with(['user', 'tipoDocumento', 'movimientosKardex.almacen'])
            ->when($this->search, function ($query) {
                $query->where('serie', 'like', '%' . $this->search . '%')
                      ->orWhere('correlativo', 'like', '%' . $this->search . '%')
                      ->orWhereHas('user', function ($q) {
                          $q->where('name', 'like', '%' . $this->search . '%');
                      });
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('correlativo', 'desc')
            ->paginate(10);
    }

    /**
     * Open details modal.
     */
    public function verDetalle(int $id): void
    {
        $this->selectedVentaId = $id;
        $this->showDetailModal = true;
    }

    /**
     * Get the selected sale details.
     */
    #[Computed]
    public function selectedVenta()
    {
        if (!$this->selectedVentaId) {
            return null;
        }

        return Venta::with(['user', 'tipoDocumento', 'detalles.variacion.producto', 'detalles.variacion.valores.atributo', 'detalles.unidadMedida', 'movimientosKardex.almacen'])
            ->find($this->selectedVentaId);
    }

    /**
     * Annul a sale and return stock to warehouse.
     */
    public function anularVenta(int $id): void
    {
        $venta = Venta::with(['detalles', 'movimientosKardex'])->findOrFail($id);

        if ($venta->estado_pago === 'cancelado') {
            Flux::toast(variant: 'danger', text: __('Esta venta ya está anulada.'));
            return;
        }

        $almacenId = $venta->movimientosKardex->first()?->almacen_id ?: Almacen::where('activo', true)->first()?->id;

        if (!$almacenId) {
            Flux::toast(variant: 'danger', text: __('No se pudo encontrar el almacén asociado para devolver el stock.'));
            return;
        }

        DB::transaction(function () use ($venta, $almacenId) {
            // Actualizar estado de la venta
            $venta->update(['estado_pago' => 'cancelado']);

            // Devolver stock y registrar en Kardex
            foreach ($venta->detalles as $det) {
                $inv = Inventario::firstOrCreate(
                    ['almacen_id' => $almacenId, 'variacion_id' => $det->variacion_id],
                    ['stock_base' => 0, 'stock_minimo' => 0]
                );

                $stockAnterior = $inv->stock_base;
                $inv->increment('stock_base', $det->cantidad_base);
                $stockPosterior = $inv->stock_base;

                // Registrar en Kardex
                Kardex::create([
                    'almacen_id' => $almacenId,
                    'variacion_id' => $det->variacion_id,
                    'tipo_transaccion' => 'Entrada',
                    'concepto' => 'Anulación de Venta ' . $venta->serie . '-' . str_pad($venta->correlativo, 6, '0', STR_PAD_LEFT),
                    'cantidad' => $det->cantidad_base,
                    'stock_anterior' => $stockAnterior,
                    'stock_posterior' => $stockPosterior,
                    'costo_unitario' => $det->precio_unitario, // Se devuelve al valor de la venta
                    'costo_total' => $det->total,
                    'valor_total_almacen' => $stockPosterior * $det->precio_unitario,
                    'origen_documento_id' => $venta->id,
                    'origen_documento_type' => Venta::class,
                    'creado_por_usuario_id' => Auth::id(),
                ]);
            }
        });

        Flux::toast(variant: 'success', text: __('Venta anulada e inventario restablecido.'));
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Ventas y Comprobantes') }}</flux:heading>
            <flux:subheading>{{ __('Listado de boletas, facturas y notas de venta emitidas.') }}</flux:subheading>
        </div>
        
        <flux:button variant="primary" icon="plus" :href="route('ventas.create')" wire:navigate>
            {{ __('Registrar Venta (POS)') }}
        </flux:button>
    </div>

    <!-- Filtros -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between bg-zinc-50 dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="w-full sm:w-80">
            <flux:input wire:model.live="search" placeholder="Buscar por cliente o comprobante..." icon="magnifying-glass" />
        </div>
    </div>

    <!-- Tabla (Tailwind para compatibilidad con Flux Free) -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Comprobante') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Cliente') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Almacén') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Fecha') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300 text-center">{{ __('Pago') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300 text-right">{{ __('Total') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300 text-center">{{ __('Estado') }}</th>
                        <th class="p-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->ventas as $venta)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="p-4 font-medium text-zinc-900 dark:text-white">
                                {{ $venta->tipoDocumento->nombre }} ({{ $venta->serie }}-{{ str_pad($venta->correlativo, 6, '0', STR_PAD_LEFT) }})
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $venta->user->name }}
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $venta->movimientosKardex->first()?->almacen?->nombre ?? '-' }}
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $venta->created_at->format('d/m/Y') }}
                            </td>
                            <td class="p-4 text-center text-zinc-600 dark:text-zinc-400">
                                <span class="capitalize">{{ $venta->metodo_pago }}</span>
                            </td>
                            <td class="p-4 text-right font-bold text-zinc-900 dark:text-white">
                                S/ {{ number_format($venta->total, 2) }}
                            </td>
                            <td class="p-4 text-center">
                                @if($venta->estado_pago === 'pagado')
                                    <flux:badge color="success">{{ __('Completado') }}</flux:badge>
                                @else
                                    <flux:badge color="danger">{{ __('Anulado') }}</flux:badge>
                                @endif
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <flux:button variant="ghost" icon="eye" size="sm" wire:click.prevent="verDetalle({{ $venta->id }})" title="Ver Recibo" />
                                @if($venta->estado_pago === 'pagado')
                                    <flux:button variant="ghost" icon="x-mark" size="sm" wire:click.prevent="anularVenta({{ $venta->id }})" wire:confirm="¿Está seguro de anular esta venta y devolver el stock?" title="Anular Venta" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-zinc-500">
                                {{ __('No se encontraron comprobantes de venta.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->ventas->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $this->ventas->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Detalle Venta (Recibo/Ticket style) -->
    @if($showDetailModal && $this->selectedVenta)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/40 backdrop-blur-sm">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 w-full max-w-lg rounded-xl overflow-hidden shadow-xl animate-in fade-in zoom-in-95 duration-200">
                <div class="p-6 space-y-6">
                    <!-- Cabecera -->
                    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-4">
                        <div>
                            <flux:heading size="lg">{{ $this->selectedVenta->tipoDocumento->nombre }}</flux:heading>
                            <flux:subheading>{{ $this->selectedVenta->serie }}-{{ str_pad($this->selectedVenta->correlativo, 6, '0', STR_PAD_LEFT) }}</flux:subheading>
                        </div>
                        <flux:button variant="ghost" icon="x-mark" wire:click="$set('showDetailModal', false)" />
                    </div>

                    <!-- Datos Venta -->
                    <div class="grid grid-cols-2 gap-4 text-xs">
                        <div>
                            <div class="text-zinc-400 font-semibold uppercase">{{ __('Cliente') }}</div>
                            <div class="font-medium text-zinc-900 dark:text-white mt-0.5">{{ $this->selectedVenta->user->name }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-400 font-semibold uppercase">{{ __('Almacén Venta') }}</div>
                            <div class="font-medium text-zinc-900 dark:text-white mt-0.5">{{ $this->selectedVenta->movimientosKardex->first()?->almacen?->nombre ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-400 font-semibold uppercase">{{ __('Fecha y Hora') }}</div>
                            <div class="font-medium text-zinc-900 dark:text-white mt-0.5">{{ $this->selectedVenta->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <div>
                            <div class="text-zinc-400 font-semibold uppercase">{{ __('Método de Pago') }}</div>
                            <div class="font-medium text-zinc-900 dark:text-white mt-0.5 capitalize">{{ $this->selectedVenta->metodo_pago }}</div>
                        </div>
                    </div>

                    <!-- Detalles del Comprobante -->
                    <div class="border-t border-b border-zinc-200 dark:border-zinc-700 py-4 max-h-60 overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-zinc-400 font-semibold uppercase border-b border-zinc-100 dark:border-zinc-800 pb-2">
                                    <th class="pb-2">{{ __('Descripción') }}</th>
                                    <th class="pb-2 text-center">{{ __('Cant.') }}</th>
                                    <th class="pb-2 text-right">{{ __('P. Unit.') }}</th>
                                    <th class="pb-2 text-right">{{ __('Importe') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->selectedVenta->detalles as $det)
                                    @php
                                        $descValores = $det->variacion->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
                                    @endphp
                                    <tr class="border-b border-zinc-50 dark:border-zinc-800/40 last:border-0">
                                        <td class="py-2">
                                            <div class="font-medium text-zinc-900 dark:text-white">{{ $det->variacion->producto->nombre }}</div>
                                            <div class="text-xxs text-zinc-500">{{ $descValores }} ({{ $det->unidadMedida->nombre }})</div>
                                        </td>
                                        <td class="py-2 text-center text-zinc-700 dark:text-zinc-300">
                                            {{ $det->cantidad }}
                                        </td>
                                        <td class="py-2 text-right text-zinc-700 dark:text-zinc-300">
                                            S/ {{ number_format($det->precio_unitario, 2) }}
                                        </td>
                                        <td class="py-2 text-right font-medium text-zinc-900 dark:text-white">
                                            S/ {{ number_format($det->total, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Totales -->
                    <div class="space-y-1.5 text-right text-xs">
                        <div class="flex justify-between text-zinc-500">
                            <span>{{ __('Subtotal:') }}</span>
                            <span>S/ {{ number_format($this->selectedVenta->subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-zinc-500">
                            <span>{{ __('IGV (18%):') }}</span>
                            <span>S/ {{ number_format($this->selectedVenta->impuesto, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-bold text-zinc-900 dark:text-white border-t border-zinc-100 dark:border-zinc-800 pt-2">
                            <span>{{ __('Total:') }}</span>
                            <span>S/ {{ number_format($this->selectedVenta->total, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
