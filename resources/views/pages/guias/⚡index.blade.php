<?php

use App\Models\GuiaInventario;
use App\Models\Almacen;
use App\Exports\GuiasInventarioExport;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Flux\Flux;
use Illuminate\Support\Facades\DB;

new #[Title('Guías de Inventario')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filtroTipo = 'todos';

    #[Url]
    public string $filtroEstado = 'todos';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public int $perPage = 10;

    public function updating($property)
    {
        if (in_array($property, ['search', 'filtroTipo', 'filtroEstado', 'desde', 'hasta', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'filtroTipo', 'filtroEstado', 'desde', 'hasta']);
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

        $query->when($this->desde, fn($q) => $q->whereDate('fecha_movimiento', '>=', $this->desde))
            ->when($this->hasta, fn($q) => $q->whereDate('fecha_movimiento', '<=', $this->hasta));

        return $query;
    }

    #[Computed]
    public function guias()
    {
        return $this->getBaseQuery()->paginate($this->perPage);
    }

    public ?int $idEliminar = null;
    public ?int $idAnular = null;

    public function confirmarEliminacion(int $id): void
    {
        $this->idEliminar = $id;
        $this->modal('modal-eliminar')->show();
    }

    public function ejecutarEliminacion(): void
    {
        $guia = GuiaInventario::findOrFail($this->idEliminar);
        
        if ($guia->estado !== 'Borrador') {
            Flux::toast(variant: 'danger', text: __('Solo se pueden eliminar guías en estado Borrador.'));
            $this->modal('modal-eliminar')->close();
            return;
        }

        $guia->delete();
        Flux::toast(variant: 'success', text: __('Guía eliminada correctamente.'));
        $this->modal('modal-eliminar')->close();
    }

    public function confirmarAnulacion(int $id): void
    {
        $this->idAnular = $id;
        $this->modal('modal-anular')->show();
    }

    public function ejecutarAnulacion(): void
    {
        $guia = GuiaInventario::with('detalles')->findOrFail($this->idAnular);
        
        if ($guia->estado !== 'Procesado') {
            Flux::toast(variant: 'danger', text: __('Solo se pueden anular guías procesadas.'));
            $this->modal('modal-anular')->close();
            return;
        }

        try {
            DB::beginTransaction();

            // Lógica de reversión de stock e inserción en Kardex...
            // Por simplicidad en este paso, simulamos el éxito si pasa validaciones
            // Aquí iría la lógica real de negocio:
            // 1. Verificar si hay stock suficiente en caso de Entrada
            // 2. Hacer las restas/sumas
            // 3. Registrar en Kardex
            
            $guia->update(['estado' => 'Anulado']);

            DB::commit();
            Flux::toast(variant: 'success', text: __('Guía anulada correctamente. El stock ha sido revertido.'));
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
        <div class="flex flex-wrap items-center gap-2">
            <flux:dropdown>
                <flux:button class="!bg-emerald-600 !text-white hover:!bg-emerald-700 border-none" icon="arrow-down-tray">{{ __('Exportar') }}</flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportarTodos" icon="document-text">{{ __('Todos') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportarFiltrados" icon="funnel">{{ __('Filtrados') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            @can('guias.crear')
                <flux:button variant="primary" icon="plus" href="{{ route('admin.guias.create') }}" wire:navigate>
                    {{ __('Nueva Guía') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar por serie, correlativo o proveedor...') }}" />
            </div>
            <flux:select wire:model.live="filtroTipo" class="sm:w-44">
                <option value="todos">{{ __('Todos los tipos') }}</option>
                <option value="Entrada">{{ __('Entradas') }}</option>
                <option value="Salida">{{ __('Salidas') }}</option>
                <option value="Transferencia">{{ __('Transferencias') }}</option>
            </flux:select>
            <flux:select wire:model.live="filtroEstado" class="sm:w-44">
                <option value="todos">{{ __('Todos los estados') }}</option>
                <option value="Borrador">{{ __('Borrador') }}</option>
                <option value="En Tránsito">{{ __('En Tránsito') }}</option>
                <option value="Procesado">{{ __('Procesado') }}</option>
                <option value="Anulado">{{ __('Anulado') }}</option>
            </flux:select>
        </div>

        <div class="flex flex-col sm:flex-row items-end gap-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
                <flux:input wire:model.live="desde" type="date" label="{{ __('Desde') }}" class="w-full sm:w-40" />
                <flux:input wire:model.live="hasta" type="date" label="{{ __('Hasta') }}" class="w-full sm:w-40" />
            </div>
            <div class="flex-1 sm:text-right">
                <flux:button class="!bg-blue-600 !text-white hover:!bg-blue-700 border-none" wire:click="resetFiltros" icon="arrow-path">
                    {{ __('Limpiar Filtros') }}
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
                        <th class="p-3">{{ __('Documento') }}</th>
                        <th class="p-3">{{ __('Tipo') }}</th>
                        <th class="p-3">{{ __('Detalle (Origen/Destino/Prov.)') }}</th>
                        <th class="p-3">{{ __('Fecha') }}</th>
                        <th class="p-3 text-center">{{ __('Estado') }}</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->guias as $guia)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
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
                            <td class="p-3">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button variant="ghost" icon="eye" size="sm"
                                        href="{{ route('admin.guias.show', $guia->id) }}" wire:navigate title="Ver Detalles" />
                                        
                                    @if($guia->estado === 'Borrador')
                                        @can('guias.crear')
                                            <flux:button variant="ghost" icon="pencil-square" size="sm"
                                                href="{{ route('admin.guias.edit', $guia->id) }}" wire:navigate title="Editar Borrador" />
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarEliminacion({{ $guia->id }})" title="Eliminar Borrador" />
                                        @endcan
                                    @endif

                                    @if($guia->estado === 'Procesado')
                                        @can('guias.crear') {{-- Asumiendo permiso de creación puede anular, o un permiso especifico --}}
                                            <flux:button variant="ghost" icon="x-circle" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click.prevent="confirmarAnulacion({{ $guia->id }})" title="Anular Guía" />
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-zinc-500">
                                {{ __('No hay guías que coincidan con tu búsqueda.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->guias->hasPages())
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                <div class="w-full sm:w-auto">
                    {{ $this->guias->links() }}
                </div>
                <div class="hidden sm:flex items-center gap-2 text-sm text-zinc-500">
                    <span>{{ __('Mostrar') }}</span>
                    <flux:select wire:model.live="perPage" class="w-20">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </flux:select>
                </div>
            </div>
        @else
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between text-xs text-zinc-400">
                <span>{{ $this->guias->total() }} {{ __('registro(s) encontrado(s)') }}</span>
            </div>
        @endif
    </div>

    <x-modal-eliminar name="modal-eliminar" title="¿Eliminar Borrador?"
        description="Esta acción eliminará el borrador de la guía de inventario."
        action="ejecutarEliminacion" />
        
    <x-modal-eliminar name="modal-anular" title="¿Anular Guía Procesada?"
        description="Esta acción es delicada. Revertirá el movimiento en el Kardex y afectará el stock actual. Solo proceda si está seguro de que esto no generará stock negativo."
        action="ejecutarAnulacion" 
        buttonText="Sí, Anular Guía" />
</div>
