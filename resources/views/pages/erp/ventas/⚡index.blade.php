<?php

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Inventario;
use App\Models\Kardex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Flux\Flux;
use App\Exports\VentasExport;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Historial de Ventas')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $estado_pago = 'todos';

    #[Url]
    public string $estado_despacho = 'todos';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public int $perPage = 20;

    public ?int $selectedVentaId = null;
    public bool $showDetailModal = false;

    /**
     * Reset pagination on search change.
     */
    public function updating($property)
    {
        if (in_array($property, ['search', 'estado_pago', 'estado_despacho', 'desde', 'hasta', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'estado_pago', 'estado_despacho', 'desde', 'hasta']);
        $this->perPage = 20;
        $this->resetPage();
    }

    /**
     * Get filtered sales.
     */
    private function buildQuery()
    {
        return Venta::with(['user', 'tipoDocumento', 'movimientosKardex.almacen'])
            ->when($this->search, function ($query) {
                $query->where('serie', 'like', '%' . $this->search . '%')
                      ->orWhere('correlativo', 'like', '%' . $this->search . '%')
                      ->orWhereHas('user', function ($q) {
                          $q->where('name', 'like', '%' . $this->search . '%');
                      });
            })
            ->when($this->estado_pago !== 'todos', fn($q) => $q->where('estado_pago', $this->estado_pago))
            ->when($this->estado_despacho !== 'todos', fn($q) => $q->where('estado_despacho', $this->estado_despacho))
            ->when($this->desde, fn($q) => $q->whereDate('created_at', '>=', $this->desde))
            ->when($this->hasta, fn($q) => $q->whereDate('created_at', '<=', $this->hasta))
            ->orderBy('created_at', 'desc')
            ->orderBy('correlativo', 'desc');
    }

    #[Computed]
    public function ventas()
    {
        return $this->buildQuery()->paginate($this->perPage);
    }

    public function exportarTodos()
    {
        $query = Venta::with(['user', 'tipoDocumento', 'movimientosKardex.almacen'])->orderBy('created_at', 'desc');
        return Excel::download(new VentasExport($query), 'todas_las_ventas.xlsx');
    }

    public function exportarFiltrados()
    {
        $query = $this->buildQuery();
        return Excel::download(new VentasExport($query), 'ventas_filtradas.xlsx');
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

    /**
     * Download receipt PDF.
     */
    public function descargarPdf(int $id): StreamedResponse
    {
        $venta = Venta::with(['user', 'tipoDocumento', 'detalles.variacion.producto', 'detalles.variacion.valores.atributo', 'detalles.unidadMedida', 'movimientosKardex.almacen'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.comprobante', compact('venta'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'comprobante-' . $venta->serie . '-' . $venta->correlativo . '.pdf');
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Ventas y Comprobantes') }}</flux:heading>
            <flux:subheading>{{ __('Listado de boletas, facturas y notas de venta emitidas.') }}</flux:subheading>
        </div>
        
        <flux:button variant="primary" icon="plus" :href="route('admin.ventas.create')" wire:navigate>
            {{ __('Registrar Venta (POS)') }}
        </flux:button>
    </div>

    <!-- Filtros -->
    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm mb-6 space-y-4">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-[200px]">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar serie, correlativo o cliente...') }}" />
            </div>
            
            <flux:select wire:model.live="estado_pago" class="sm:w-44">
                <option value="todos">{{ __('Estado Pago') }}</option>
                <option value="pendiente">{{ __('Pendiente') }}</option>
                <option value="pagado">{{ __('Pagado') }}</option>
                <option value="reembolsado">{{ __('Reembolsado') }}</option>
                <option value="cancelado">{{ __('Cancelado') }}</option>
            </flux:select>
            
            <flux:select wire:model.live="estado_despacho" class="sm:w-44">
                <option value="todos">{{ __('Estado Despacho') }}</option>
                <option value="pendiente">{{ __('Pendiente') }}</option>
                <option value="preparado">{{ __('Preparado') }}</option>
                <option value="despachado">{{ __('Despachado') }}</option>
                <option value="entregado">{{ __('Entregado') }}</option>
            </flux:select>
        </div>

        <div class="flex flex-col sm:flex-row items-end gap-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live="desde" type="date" label="{{ __('Desde') }}" class="w-full sm:w-40" />
                <flux:input wire:model.live="hasta" type="date" label="{{ __('Hasta') }}" class="w-full sm:w-40" />
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden flex flex-col">

        <!-- Cabecera de tabla: Acciones + PerPage -->
        <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30">
            <flux:dropdown>
                <flux:button class="!bg-emerald-600 !text-white hover:!bg-emerald-700 border-none" size="sm" icon="arrow-down-tray">{{ __('Exportar') }}</flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportarFiltrados" icon="funnel">{{ __('Resultados filtrados') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportarTodos" icon="document-text">{{ __('Todos los registros') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            <flux:button size="sm" class="!bg-red-600 !text-white hover:!bg-red-700 border-none" wire:click="resetFiltros" icon="arrow-path">
                {{ __('Limpiar') }}
            </flux:button>

            <div class="flex items-center gap-2 text-sm text-zinc-500 ml-auto">
                <span class="hidden sm:inline">{{ __('Mostrar') }}</span>
                <flux:select wire:model.live="perPage" class="w-20">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </flux:select>
            </div>
        </div>

        <div class="overflow-x-auto flex-1">
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
                            <td colspan="8" class="text-center py-12 text-zinc-400">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.face-smile class="size-8 text-zinc-300" />
                                    <span>{{ $search ? __('No se encontraron resultados para ":query"', ['query' => $search]) : __('No hay comprobantes de venta registrados.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pie de tabla: Paginación + Info -->
        <div class="px-4 py-4 border-t border-zinc-200 dark:border-zinc-700">
            @if($this->ventas->hasPages())
                {{ $this->ventas->links() }}
            @else
                <p class="text-xs text-zinc-400">
                    {{ __(':total registro(s)', ['total' => $this->ventas->total()]) }}
                </p>
            @endif
        </div>
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
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="primary" icon="arrow-down-tray" wire:click="descargarPdf({{ $this->selectedVenta->id }})" wire:loading.attr="disabled" wire:target="descargarPdf">
                                <span wire:loading.remove wire:target="descargarPdf">{{ __('Descargar PDF') }}</span>
                                <span wire:loading wire:target="descargarPdf">{{ __('Generando...') }}</span>
                            </flux:button>
                            <flux:button variant="ghost" icon="x-mark" wire:click="$set('showDetailModal', false)" />
                        </div>
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
