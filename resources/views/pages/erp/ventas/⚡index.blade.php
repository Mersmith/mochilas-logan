<?php

use App\Exports\VentasExport;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Barryvdh\DomPDF\Facade\Pdf;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    // Modal de Nota de Crédito / Anulación / Devolución
    public bool $showAnulacionModal = false;
    public ?int $anulacionVentaId = null;
    public string $tipo_ajuste = 'total'; // 'total' (Anulación Total) o 'parcial' (Devolución Parcial)
    public string $motivo_anulacion = 'Anulación de la operación';
    public string $descripcion_motivo = '';
    public array $itemsDevolucion = [];

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
            ->when($this->estado_pago !== 'todos', fn ($q) => $q->where('estado_pago', $this->estado_pago))
            ->when($this->estado_despacho !== 'todos', fn ($q) => $q->where('estado_despacho', $this->estado_despacho))
            ->when($this->desde, fn ($q) => $q->whereDate('created_at', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('created_at', '<=', $this->hasta))
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
        if (! $this->selectedVentaId) {
            return null;
        }

        return Venta::with(['user', 'tipoDocumento', 'detalles.variacion.producto', 'detalles.variacion.valores.atributo', 'detalles.unidadMedida', 'movimientosKardex.almacen'])
            ->find($this->selectedVentaId);
    }

    /**
     * Abrir modal de Nota de Crédito / Devolución / Anulación
     */
    public function abrirModalAnulacion(int $id): void
    {
        $venta = Venta::with(['detalles.variacion.producto', 'detalles.variacion.valores.atributo', 'detalles.unidadMedida', 'movimientosKardex.almacen'])->findOrFail($id);

        if ($venta->estado_pago === 'cancelado') {
            Flux::toast(variant: 'danger', text: __('Esta venta ya se encuentra totalmente anulada.'));
            return;
        }

        $this->anulacionVentaId = $id;
        $this->tipo_ajuste = 'total';
        $this->motivo_anulacion = '01 - Anulación de la operación';
        $this->descripcion_motivo = '';

        $this->itemsDevolucion = [];
        foreach ($venta->detalles as $det) {
            $desc = $det->variacion->valores->map(fn ($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
            $this->itemsDevolucion[$det->id] = [
                'detalle_id' => $det->id,
                'variacion_id' => $det->variacion_id,
                'nombre' => $det->variacion->producto->nombre . ' (' . $desc . ')',
                'unidad' => $det->unidadMedida->nombre,
                'factor' => (int) $det->factor_conversion,
                'cantidad_original' => (int) $det->cantidad,
                'cantidad_devolver' => (int) $det->cantidad,
                'precio_unitario' => (float) $det->precio_unitario,
            ];
        }

        $this->showAnulacionModal = true;
    }

    /**
     * Procesar Nota de Crédito (Total o Parcial)
     */
    public function procesarNotaCredito(): void
    {
        $venta = Venta::with(['detalles', 'movimientosKardex'])->findOrFail($this->anulacionVentaId);

        if ($venta->estado_pago === 'cancelado') {
            Flux::toast(variant: 'danger', text: __('Esta venta ya está anulada.'));
            return;
        }

        $almacenId = $venta->movimientosKardex->first()?->almacen_id ?: Almacen::where('activo', true)->first()?->id;

        if (! $almacenId) {
            Flux::toast(variant: 'danger', text: __('No se pudo encontrar el almacén asociado para reintegrar el stock.'));
            return;
        }

        $motivoTexto = $this->motivo_anulacion . ($this->descripcion_motivo ? ' - ' . $this->descripcion_motivo : '');

        DB::transaction(function () use ($venta, $almacenId, $motivoTexto) {
            if ($this->tipo_ajuste === 'total') {
                // ANULACIÓN TOTAL
                $venta->update([
                    'estado_pago' => 'cancelado',
                    'comentarios' => trim($venta->comentarios . "\n[NOTA DE CRÉDITO TOTAL]: " . $motivoTexto),
                ]);

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
                        'concepto' => 'Nota de Crédito (Anulación) Venta ' . $venta->serie . '-' . str_pad($venta->correlativo, 6, '0', STR_PAD_LEFT) . ': ' . $motivoTexto,
                        'cantidad' => $det->cantidad_base,
                        'stock_anterior' => $stockAnterior,
                        'stock_posterior' => $stockPosterior,
                        'costo_unitario' => $det->precio_unitario,
                        'costo_total' => $det->total,
                        'valor_total_almacen' => $stockPosterior * $det->precio_unitario,
                        'origen_documento_id' => $venta->id,
                        'origen_documento_type' => Venta::class,
                        'creado_por_usuario_id' => Auth::id(),
                    ]);
                }
            } else {
                // DEVOLUCIÓN PARCIAL
                $montoDevueltoTotal = 0.00;
                $todosDevueltos = true;

                foreach ($this->itemsDevolucion as $item) {
                    $cantDevolver = max(0, min((int) $item['cantidad_devolver'], (int) $item['cantidad_original']));
                    
                    if ($cantDevolver < (int) $item['cantidad_original']) {
                        $todosDevueltos = false;
                    }

                    if ($cantDevolver > 0) {
                        $cantBaseDevolver = $cantDevolver * $item['factor'];
                        $montoItemDevuelto = round($cantDevolver * $item['precio_unitario'], 2);
                        $montoDevueltoTotal += $montoItemDevuelto;

                        // Reintegrar stock al inventario
                        $inv = Inventario::firstOrCreate(
                            ['almacen_id' => $almacenId, 'variacion_id' => $item['variacion_id']],
                            ['stock_base' => 0, 'stock_minimo' => 0]
                        );

                        $stockAnterior = $inv->stock_base;
                        $inv->increment('stock_base', $cantBaseDevolver);
                        $stockPosterior = $inv->stock_base;

                        // Registrar en Kardex
                        Kardex::create([
                            'almacen_id' => $almacenId,
                            'variacion_id' => $item['variacion_id'],
                            'tipo_transaccion' => 'Entrada',
                            'concepto' => 'Nota de Crédito (Devolución Parcial ' . $cantDevolver . ' ' . $item['unidad'] . ') Venta ' . $venta->serie . '-' . str_pad($venta->correlativo, 6, '0', STR_PAD_LEFT) . ': ' . $motivoTexto,
                            'cantidad' => $cantBaseDevolver,
                            'stock_anterior' => $stockAnterior,
                            'stock_posterior' => $stockPosterior,
                            'costo_unitario' => $item['precio_unitario'],
                            'costo_total' => $montoItemDevuelto,
                            'valor_total_almacen' => $stockPosterior * $item['precio_unitario'],
                            'origen_documento_id' => $venta->id,
                            'origen_documento_type' => Venta::class,
                            'creado_por_usuario_id' => Auth::id(),
                        ]);

                        // Actualizar o marcar en el detalle de la venta
                        $detModel = VentaDetalle::find($item['detalle_id']);
                        if ($detModel) {
                            $nuevaCant = max(0, $detModel->cantidad - $cantDevolver);
                            $nuevaCantBase = $nuevaCant * $item['factor'];
                            $nuevoTotal = round($nuevaCant * $detModel->precio_unitario, 2);
                            $detModel->update([
                                'cantidad' => $nuevaCant,
                                'cantidad_base' => $nuevaCantBase,
                                'subtotal' => $nuevoTotal,
                                'total' => $nuevoTotal,
                            ]);
                        }
                    }
                }

                // Si se devolvieron todos los productos, anular la venta; si no, ajustar montos
                if ($todosDevueltos) {
                    $venta->update([
                        'estado_pago' => 'cancelado',
                        'total' => 0.00,
                        'subtotal' => 0.00,
                        'comentarios' => trim($venta->comentarios . "\n[NC DEVOLUCIÓN TOTAL]: " . $motivoTexto . " (Monto devuelto: S/ " . number_format($montoDevueltoTotal, 2) . ")"),
                    ]);
                } else {
                    $nuevoTotalVenta = max(0, $venta->total - $montoDevueltoTotal);
                    $nuevoSubtotalVenta = max(0, $venta->subtotal - $montoDevueltoTotal);
                    $venta->update([
                        'total' => $nuevoTotalVenta,
                        'subtotal' => $nuevoSubtotalVenta,
                        'comentarios' => trim($venta->comentarios . "\n[NC DEVOLUCIÓN PARCIAL]: " . $motivoTexto . " (Monto devuelto: S/ " . number_format($montoDevueltoTotal, 2) . ")"),
                    ]);
                }
            }
        });

        $this->showAnulacionModal = false;
        Flux::toast(variant: 'success', text: __('Nota de Crédito procesada, inventario y Kardex actualizados correctamente.'));
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
                        <th class="p-4 text-right">{{ __('Acciones') }}</th>
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
                            <td class="p-4 text-right space-x-1">
                                <flux:button variant="ghost" icon="eye" size="sm" wire:click.prevent="verDetalle({{ $venta->id }})" title="{{ __('Ver Recibo') }}" />
                                @if($venta->estado_pago === 'pagado')
                                    <flux:button variant="ghost" icon="arrow-uturn-left" size="sm" class="text-rose-600 hover:text-rose-700" wire:click.prevent="abrirModalAnulacion({{ $venta->id }})" title="{{ __('Nota de Crédito / Devolución / Anulación') }}" />
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
                        <div class="flex justify-between text-base font-bold text-zinc-900 dark:text-white border-t border-zinc-100 dark:border-zinc-800 pt-2">
                            <span>{{ __('Total:') }}</span>
                            <span>S/ {{ number_format($this->selectedVenta->total, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Nota de Crédito / Devolución / Anulación de Venta -->
    @if($showAnulacionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/40 backdrop-blur-sm">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 w-full max-w-2xl rounded-xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <form wire:submit.prevent="procesarNotaCredito">
                    <div class="p-6 space-y-6">
                        <!-- Cabecera -->
                        <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-4">
                            <div>
                                <flux:heading size="lg">{{ __('Emitir Nota de Crédito / Ajuste de Venta') }}</flux:heading>
                                <flux:subheading>{{ __('Reintegra el stock al almacén y genera los movimientos oficiales de Kardex.') }}</flux:subheading>
                            </div>
                            <flux:button variant="ghost" icon="x-mark" wire:click="$set('showAnulacionModal', false)" />
                        </div>

                        <!-- Tipo de Operación -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>{{ __('Tipo de Ajuste') }}</flux:label>
                                <flux:select wire:model.live="tipo_ajuste">
                                    <option value="total">{{ __('Anulación Total de la Operación (100%)') }}</option>
                                    <option value="parcial">{{ __('Devolución Parcial de Productos') }}</option>
                                </flux:select>
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Motivo (Catálogo SUNAT)') }}</flux:label>
                                <flux:select wire:model="motivo_anulacion">
                                    <option value="01 - Anulación de la operación">{{ __('01 - Anulación de la operación') }}</option>
                                    <option value="02 - Anulación por error en el RUC/DNI">{{ __('02 - Anulación por error en el RUC/DNI') }}</option>
                                    <option value="03 - Corrección por error en la descripción">{{ __('03 - Corrección por error en la descripción') }}</option>
                                    <option value="06 - Devolución total">{{ __('06 - Devolución total') }}</option>
                                    <option value="07 - Devolución por ítem / parcial">{{ __('07 - Devolución por ítem / parcial') }}</option>
                                </flux:select>
                            </flux:field>
                        </div>

                        <!-- Detalle de Items para Devolución Parcial -->
                        @if($tipo_ajuste === 'parcial')
                            <div class="space-y-2 border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 bg-zinc-50/50 dark:bg-zinc-800/30">
                                <div class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                    {{ __('Selecciona la cantidad de unidades que el cliente devuelve al almacén:') }}
                                </div>
                                <div class="max-h-56 overflow-y-auto divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach($itemsDevolucion as $k => $item)
                                        <div class="py-2 flex items-center justify-between gap-4 text-xs">
                                            <div class="flex-1">
                                                <div class="font-medium text-zinc-900 dark:text-white">{{ $item['nombre'] }}</div>
                                                <div class="text-xxs text-zinc-500">
                                                    {{ __('Comprado: ') }}{{ $item['cantidad_original'] }} {{ $item['unidad'] }} x S/ {{ number_format($item['precio_unitario'], 2) }}
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xxs text-zinc-500">{{ __('Devolver:') }}</span>
                                                <input type="number" min="0" max="{{ $item['cantidad_original'] }}" wire:model="itemsDevolucion.{{ $k }}.cantidad_devolver" class="w-16 text-center text-xs py-1 px-2 border rounded-md dark:bg-zinc-800 dark:border-zinc-700 font-bold" />
                                                <span class="text-xxs text-zinc-500">{{ $item['unidad'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="p-4 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 rounded-lg text-xs text-rose-700 dark:text-rose-300 space-y-1">
                                <p class="font-bold">⚠️ {{ __('Atención: Anulación Total de la Venta') }}</p>
                                <p>{{ __('Se reintegrará el 100% de los productos al almacén de origen, se registrará el contra-asiento en el Kardex y la venta quedará en estado ANULADA.') }}</p>
                            </div>
                        @endif

                        <flux:field>
                            <flux:label>{{ __('Observación / Detalle Adicional') }}</flux:label>
                            <flux:textarea wire:model="descripcion_motivo" rows="2" placeholder="Ej. Cliente solicitó cambio por defecto de costura..." />
                        </flux:field>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:button variant="ghost" type="button" wire:click="$set('showAnulacionModal', false)">
                                {{ __('Cancelar') }}
                            </flux:button>
                            <flux:button variant="danger" type="submit">
                                {{ __('Confirmar y Procesar Nota de Crédito') }}
                            </flux:button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
