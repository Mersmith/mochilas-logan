<?php

use App\Exports\GuiasInventarioExport;
use App\Models\Almacen;
use App\Models\GuiaInventario;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\TipoDocumento;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Guías de Inventario')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filtroTipo = 'todos';

    #[Url]
    public string $filtroEstado = 'todos';

    #[Url]
    public string $filtroPapelera = 'admitidos';

    #[Url]
    public string $almacen_id = '';

    #[Url]
    public string $tipo_documento_id = '';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public int $perPage = 10;

    public function updating($property)
    {
        if (in_array($property, ['search', 'filtroTipo', 'filtroEstado', 'filtroPapelera', 'almacen_id', 'tipo_documento_id', 'desde', 'hasta', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'filtroTipo', 'filtroEstado', 'filtroPapelera', 'almacen_id', 'tipo_documento_id', 'desde', 'hasta']);
        $this->perPage = 10;
        $this->resetPage();
    }

    protected function getBaseQuery()
    {
        $query = GuiaInventario::query()
            ->with(['proveedor', 'almacenOrigen.sede', 'almacenDestino.sede', 'tipoDocumento'])
            ->when($this->search, function ($q) {
                $q->where('serie', 'like', '%' . $this->search . '%')
                    ->orWhere('correlativo', 'like', '%' . $this->search . '%')
                    ->orWhereHas('proveedor', function ($p) {
                        $p->where('razon_social', 'like', '%' . $this->search . '%')
                            ->orWhere('ruc', 'like', '%' . $this->search . '%');
                    });
            })
            ->orderBy('fecha_movimiento', 'desc')
            ->orderBy('id', 'desc');

        if ($this->filtroTipo !== 'todos') {
            $query->where('tipo_movimiento', $this->filtroTipo);
        }

        if ($this->filtroEstado !== 'todos') {
            $query->where('estado', $this->filtroEstado);
        }

        if ($this->filtroPapelera === 'eliminados') {
            $query->onlyTrashed();
        } elseif ($this->filtroPapelera === 'todos') {
            $query->withTrashed();
        }

        $query->when($this->almacen_id, function ($q) {
            $q->where(function ($sub) {
                $sub->where('almacen_origen_id', $this->almacen_id)
                    ->orWhere('almacen_destino_id', $this->almacen_id);
            });
        });

        $query->when($this->tipo_documento_id, fn ($q) => $q->where('tipo_documento_id', $this->tipo_documento_id));

        $query->when($this->desde, fn ($q) => $q->whereDate('fecha_movimiento', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha_movimiento', '<=', $this->hasta));

        return $query;
    }

    #[Computed]
    public function guias()
    {
        return $this->getBaseQuery()->paginate($this->perPage);
    }

    #[Computed]
    public function almacenes()
    {
        return Almacen::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function tiposDocumento()
    {
        return TipoDocumento::where('activo', true)->orderBy('nombre')->get();
    }

    public ?int $idEliminar = null;
    public ?int $idRestaurar = null;
    public ?int $idAnular = null;

    public function confirmarEliminacion(int $id, bool $esPermanente = false): void
    {
        $this->idEliminar = $id;
        $this->modal($esPermanente ? 'modal-eliminar-force' : 'modal-eliminar-soft')->show();
    }

    public function ejecutarEliminacion(): void
    {
        $guia = GuiaInventario::findOrFail($this->idEliminar);

        if ($guia->estado !== 'Borrador') {
            Flux::toast(variant: 'danger', text: __('Solo se pueden eliminar guías en estado Borrador.'));
            $this->modal('modal-eliminar-soft')->close();
            return;
        }

        $guia->delete();
        Flux::toast(variant: 'success', text: __('Enviado a la papelera.'));
        $this->modal('modal-eliminar-soft')->close();
    }

    public function ejecutarEliminacionPermanente(): void
    {
        $guia = GuiaInventario::withTrashed()->findOrFail($this->idEliminar);

        if ($guia->estado !== 'Borrador') {
            Flux::toast(variant: 'danger', text: __('Solo se pueden eliminar permanentemente guías en estado Borrador.'));
            $this->modal('modal-eliminar-force')->close();
            return;
        }

        $guia->forceDelete();
        Flux::toast(variant: 'success', text: __('Eliminado permanentemente.'));
        $this->modal('modal-eliminar-force')->close();
    }

    public function confirmarRestauracion(int $id): void
    {
        $this->idRestaurar = $id;
        $this->modal('modal-restaurar')->show();
    }

    public function ejecutarRestauracion(): void
    {
        $guia = GuiaInventario::withTrashed()->findOrFail($this->idRestaurar);
        $guia->restore();

        Flux::toast(variant: 'success', text: __('Restaurado correctamente.'));
        $this->modal('modal-restaurar')->close();
    }

    public function confirmarAnulacion(int $id): void
    {
        $this->idAnular = $id;
        $this->modal('modal-anular')->show();
    }

    public function ejecutarAnulacion(): void
    {
        $guia = GuiaInventario::with(['detalles.variacion'])->findOrFail($this->idAnular);

        if ($guia->estado !== 'Procesado') {
            Flux::toast(variant: 'danger', text: __('Solo se pueden anular guías procesadas.'));
            $this->modal('modal-anular')->close();
            return;
        }

        try {
            DB::beginTransaction();

            foreach ($guia->detalles as $detalle) {
                if ($guia->tipo_movimiento === 'Entrada') {
                    // Revertir Entrada: Restar del almacén de destino
                    $inv = Inventario::where('almacen_id', $guia->almacen_destino_id)
                        ->where('variacion_id', $detalle->variacion_id)
                        ->first();

                    if (! $inv || $inv->stock_base < $detalle->cantidad_base) {
                        $sku = $detalle->variacion->sku ?? 'SKU #' . $detalle->variacion_id;
                        $stockActual = $inv->stock_base ?? 0;
                        throw new \Exception("Stock insuficiente para revertir la entrada del producto {$sku}. Stock actual: {$stockActual}, cantidad a revertir: {$detalle->cantidad_base}.");
                    }

                    $stockAnterior = $inv->stock_base;
                    $inv->stock_base -= $detalle->cantidad_base;
                    $inv->save();

                    Kardex::create([
                        'almacen_id' => $guia->almacen_destino_id,
                        'variacion_id' => $detalle->variacion_id,
                        'tipo_transaccion' => 'Salida',
                        'concepto' => 'Anulación Guía ' . $guia->serie . '-' . str_pad($guia->correlativo, 6, '0', STR_PAD_LEFT),
                        'cantidad' => $detalle->cantidad_base,
                        'stock_anterior' => $stockAnterior,
                        'stock_posterior' => $inv->stock_base,
                        'costo_unitario' => $detalle->costo_unitario,
                        'costo_total' => $detalle->costo_total,
                        'origen_documento_id' => $guia->id,
                        'origen_documento_type' => GuiaInventario::class,
                        'creado_por_usuario_id' => auth()->id(),
                    ]);

                } elseif ($guia->tipo_movimiento === 'Salida') {
                    // Revertir Salida: Devolver al almacén de origen
                    $inv = Inventario::firstOrCreate(
                        ['almacen_id' => $guia->almacen_origen_id, 'variacion_id' => $detalle->variacion_id],
                        ['stock_base' => 0, 'stock_minimo' => 0]
                    );

                    $stockAnterior = $inv->stock_base;
                    $inv->stock_base += $detalle->cantidad_base;
                    $inv->save();

                    Kardex::create([
                        'almacen_id' => $guia->almacen_origen_id,
                        'variacion_id' => $detalle->variacion_id,
                        'tipo_transaccion' => 'Entrada',
                        'concepto' => 'Anulación Guía ' . $guia->serie . '-' . str_pad($guia->correlativo, 6, '0', STR_PAD_LEFT),
                        'cantidad' => $detalle->cantidad_base,
                        'stock_anterior' => $stockAnterior,
                        'stock_posterior' => $inv->stock_base,
                        'costo_unitario' => $detalle->costo_unitario,
                        'costo_total' => $detalle->costo_total,
                        'origen_documento_id' => $guia->id,
                        'origen_documento_type' => GuiaInventario::class,
                        'creado_por_usuario_id' => auth()->id(),
                    ]);

                } elseif ($guia->tipo_movimiento === 'Transferencia') {
                    // Revertir Transferencia: Restar de Destino y devolver a Origen
                    $invDestino = Inventario::where('almacen_id', $guia->almacen_destino_id)
                        ->where('variacion_id', $detalle->variacion_id)
                        ->first();

                    if (! $invDestino || $invDestino->stock_base < $detalle->cantidad_base) {
                        throw new \Exception("Stock insuficiente en almacén de destino para revertir la transferencia.");
                    }

                    $stockAnteriorDestino = $invDestino->stock_base;
                    $invDestino->stock_base -= $detalle->cantidad_base;
                    $invDestino->save();

                    Kardex::create([
                        'almacen_id' => $guia->almacen_destino_id,
                        'variacion_id' => $detalle->variacion_id,
                        'tipo_transaccion' => 'Salida',
                        'concepto' => 'Anulación Transferencia (Reversión Destino) ' . $guia->serie . '-' . str_pad($guia->correlativo, 6, '0', STR_PAD_LEFT),
                        'cantidad' => $detalle->cantidad_base,
                        'stock_anterior' => $stockAnteriorDestino,
                        'stock_posterior' => $invDestino->stock_base,
                        'origen_documento_id' => $guia->id,
                        'origen_documento_type' => GuiaInventario::class,
                        'creado_por_usuario_id' => auth()->id(),
                    ]);

                    $invOrigen = Inventario::firstOrCreate(
                        ['almacen_id' => $guia->almacen_origen_id, 'variacion_id' => $detalle->variacion_id],
                        ['stock_base' => 0, 'stock_minimo' => 0]
                    );

                    $stockAnteriorOrigen = $invOrigen->stock_base;
                    $invOrigen->stock_base += $detalle->cantidad_base;
                    $invOrigen->save();

                    Kardex::create([
                        'almacen_id' => $guia->almacen_origen_id,
                        'variacion_id' => $detalle->variacion_id,
                        'tipo_transaccion' => 'Entrada',
                        'concepto' => 'Anulación Transferencia (Reversión Origen) ' . $guia->serie . '-' . str_pad($guia->correlativo, 6, '0', STR_PAD_LEFT),
                        'cantidad' => $detalle->cantidad_base,
                        'stock_anterior' => $stockAnteriorOrigen,
                        'stock_posterior' => $invOrigen->stock_base,
                        'origen_documento_id' => $guia->id,
                        'origen_documento_type' => GuiaInventario::class,
                        'creado_por_usuario_id' => auth()->id(),
                    ]);
                }
            }

            $guia->update(['estado' => 'Anulado']);

            DB::commit();
            Flux::toast(variant: 'success', text: __('Guía anulada correctamente. El stock y el Kardex han sido revertidos.'));
            $this->modal('modal-anular')->close();
        } catch (\Exception $e) {
            DB::rollBack();
            Flux::toast(variant: 'danger', text: __('Error al anular: ') . $e->getMessage());
            $this->modal('modal-anular')->close();
        }
    }

    public function exportarTodos()
    {
        $query = GuiaInventario::query()->orderBy('fecha_movimiento', 'desc');
        return Excel::download(new GuiasInventarioExport($query), 'todas_las_guias.xlsx');
    }

    public function exportarFiltrados()
    {
        $query = $this->getBaseQuery();
        return Excel::download(new GuiasInventarioExport($query), 'guias_filtradas.xlsx');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Guías de Inventario') }}</flux:heading>
            <flux:subheading>{{ __('Gestión de entradas, salidas y transferencias de productos.') }}</flux:subheading>
        </div>
        @can('guias.crear')
            <flux:button variant="primary" icon="plus" href="{{ route('admin.guias.create') }}" wire:navigate>
                {{ __('Nueva Guía') }}
            </flux:button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar por serie, correlativo o proveedor...') }}" />
            </div>

            <flux:select wire:model.live="filtroTipo" class="sm:w-44">
                <option value="todos">{{ __('Tipo: Todos') }}</option>
                <option value="Entrada">{{ __('Entrada') }}</option>
                <option value="Salida">{{ __('Salida') }}</option>
                <option value="Transferencia">{{ __('Transferencia') }}</option>
            </flux:select>

            <flux:select wire:model.live="filtroEstado" class="sm:w-44">
                <option value="todos">{{ __('Estado: Todos') }}</option>
                <option value="Borrador">{{ __('Borrador') }}</option>
                <option value="Procesado">{{ __('Procesado') }}</option>
                <option value="Anulado">{{ __('Anulado') }}</option>
            </flux:select>

            <flux:select wire:model.live="filtroPapelera" class="sm:w-44">
                <option value="admitidos">{{ __('Admitidos') }}</option>
                <option value="eliminados">{{ __('Eliminados') }}</option>
                <option value="todos">{{ __('Papelera + Admitidos') }}</option>
            </flux:select>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <flux:select wire:model.live="almacen_id" class="flex-1">
                <option value="">{{ __('Todos los Almacenes (Origen/Destino)') }}</option>
                @foreach($this->almacenes as $a)
                    <option value="{{ $a->id }}">{{ $a->nombre }}</option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="tipo_documento_id" class="flex-1">
                <option value="">{{ __('Todos los Tipos de Documento') }}</option>
                @foreach($this->tiposDocumento as $td)
                    <option value="{{ $td->id }}">{{ $td->nombre }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex flex-col sm:flex-row items-end gap-3 justify-between">
            <div class="flex flex-col md:flex-row gap-3 flex-1 w-full">
                <flux:input wire:model.live="desde" type="date" label="{{ __('Desde') }}" class="w-full md:w-40" />
                <flux:input wire:model.live="hasta" type="date" label="{{ __('Hasta') }}" class="w-full md:w-40" />
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
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-3">{{ __('Documento') }}</th>
                        <th class="p-3">{{ __('Tipo') }}</th>
                        <th class="p-3">{{ __('Detalle (Origen/Destino/Prov.)') }}</th>
                        <th class="p-3">{{ __('Fecha') }}</th>
                        <th class="p-3 text-center">{{ __('Estado') }}</th>
                        <th class="p-3 text-center">{{ __('Creado') }}</th>
                        <th class="p-3 text-center">{{ __('Registro') }}</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->guias as $guia)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors {{ $guia->trashed() ? 'opacity-50' : '' }}">
                            <td class="p-3">
                                <div class="font-medium text-zinc-900 dark:text-white">
                                    {{ $guia->serie }}-{{ str_pad($guia->correlativo, 6, '0', STR_PAD_LEFT) }}
                                </div>
                                <div class="text-xs text-zinc-500">{{ $guia->tipoDocumento->nombre ?? '' }}</div>
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center gap-1.5 py-1 px-2 rounded-md text-xs font-medium 
                                    {{ $guia->tipo_movimiento === 'Entrada' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' : '' }}
                                    {{ $guia->tipo_movimiento === 'Salida' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                    {{ $guia->tipo_movimiento === 'Transferencia' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                                ">
                                    {{ $guia->tipo_movimiento }}
                                </span>
                            </td>
                            <td class="p-3 text-zinc-600 dark:text-zinc-400 text-xs space-y-1">
                                @if($guia->tipo_movimiento === 'Entrada')
                                    <div><strong>Prov:</strong> {{ $guia->proveedor->razon_social ?? 'N/A' }}</div>
                                    <div><strong>Dest:</strong> {{ $guia->almacenDestino->nombre ?? '' }}</div>
                                @elseif($guia->tipo_movimiento === 'Salida')
                                    <div><strong>Orig:</strong> {{ $guia->almacenOrigen->nombre ?? '' }}</div>
                                @else
                                    <div><strong>Orig:</strong> {{ $guia->almacenOrigen->nombre ?? '' }}</div>
                                    <div><strong>Dest:</strong> {{ $guia->almacenDestino->nombre ?? '' }}</div>
                                @endif
                            </td>
                            <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                {{ $guia->fecha_movimiento->format('d/m/Y') }}
                            </td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center gap-1.5 py-1 px-2 rounded-full text-xs font-medium border
                                    {{ $guia->estado === 'Borrador' ? 'bg-zinc-100 text-zinc-700 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700' : '' }}
                                    {{ $guia->estado === 'Procesado' ? 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800/50' : '' }}
                                    {{ $guia->estado === 'En Tránsito' ? 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-900/30 dark:text-purple-400 dark:border-purple-800/50' : '' }}
                                    {{ $guia->estado === 'Anulado' ? 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800/50' : '' }}
                                ">
                                    {{ $guia->estado }}
                                </span>
                            </td>
                            <td class="p-3 text-center text-zinc-600 dark:text-zinc-400 text-xs">
                                {{ $guia->created_at->format('d/m/Y') }}
                            </td>
                            <td class="p-3 text-center">
                                @if($guia->trashed())
                                    <div class="flex justify-center" title="{{ __('Eliminado') }}">
                                        <flux:icon.trash class="size-5 text-red-500" />
                                    </div>
                                @else
                                    <div class="flex justify-center" title="{{ __('Admitido') }}">
                                        <flux:icon.check-badge class="size-5 text-blue-500" />
                                    </div>
                                @endif
                            </td>
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button variant="ghost" icon="eye" size="sm"
                                        href="{{ route('admin.guias.show', $guia->id) }}" wire:navigate title="{{ __('Ver Detalles') }}" />

                                    @if($guia->trashed())
                                        <flux:button variant="ghost" icon="arrow-path" size="sm"
                                            wire:click.prevent="confirmarRestauracion({{ $guia->id }})" title="{{ __('Restaurar') }}" />
                                        @if($guia->estado === 'Borrador')
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarEliminacion({{ $guia->id }}, true)" title="{{ __('Eliminar permanentemente') }}" />
                                        @endif
                                    @else
                                        @if($guia->estado === 'Borrador')
                                            @can('guias.crear')
                                                <flux:button variant="ghost" icon="pencil-square" size="sm"
                                                    href="{{ route('admin.guias.edit', $guia->id) }}" wire:navigate title="{{ __('Editar Borrador') }}" />
                                                <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                    wire:click.prevent="confirmarEliminacion({{ $guia->id }})" title="{{ __('Eliminar Borrador') }}" />
                                            @endcan
                                        @endif

                                        @if($guia->estado === 'Procesado')
                                            @can('guias.crear')
                                                <flux:button variant="ghost" icon="x-circle" size="sm" class="text-red-500 hover:text-red-600"
                                                    wire:click.prevent="confirmarAnulacion({{ $guia->id }})" title="{{ __('Anular Guía') }}" />
                                            @endcan
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-12 text-zinc-400">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.face-smile class="size-8 text-zinc-300" />
                                    <span>{{ $search ? __('No se encontraron resultados para ":query"', ['query' => $search]) : __('No hay registros encontrados.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pie de tabla: Paginación + Info -->
        <div class="px-4 py-4 border-t border-zinc-200 dark:border-zinc-700">
            @if($this->guias->hasPages())
                {{ $this->guias->links() }}
            @else
                <p class="text-xs text-zinc-400">
                    {{ __(':total registro(s)', ['total' => $this->guias->total()]) }}
                </p>
            @endif
        </div>
    </div>

    <x-modal-eliminar name="modal-eliminar-soft" title="¿Eliminar Borrador?"
        description="Esta acción moverá el borrador de la guía de inventario a la papelera."
        action="ejecutarEliminacion" />

    <x-modal-eliminar name="modal-eliminar-force" title="¿Eliminar permanentemente?"
        description="Esta acción es irreversible y eliminará el borrador de la guía de la base de datos de forma permanente."
        :isPermanent="true" action="ejecutarEliminacionPermanente" />

    <x-modal-restaurar />

    <x-modal-eliminar name="modal-anular" title="¿Anular Guía Procesada?"
        description="Esta acción es delicada. Revertirá el movimiento en el Kardex y afectará el stock actual. Solo proceda si está seguro de que esto no generará stock negativo."
        action="ejecutarAnulacion"
        buttonText="Sí, Anular Guía" />
</div>
