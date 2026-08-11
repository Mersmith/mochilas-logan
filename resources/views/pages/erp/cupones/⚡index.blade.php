<?php

use App\Models\Cupon;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Flux\Flux;
use App\Exports\CuponesExport;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Cupones de Descuento')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filtroEstado = 'todos';

    #[Url]
    public string $filtroTipo = 'todos';

    #[Url]
    public string $filtroVigencia = 'todos';

    public string $sortBy = 'id';
    public string $sortDirection = 'desc';

    public function sort($column)
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function updating($property)
    {
        if (in_array($property, ['search', 'filtroEstado', 'filtroTipo', 'filtroVigencia'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'filtroEstado', 'filtroTipo', 'filtroVigencia']);
        $this->resetPage();
    }

    public function ejecutarEliminacion($id)
    {
        if (! auth()->user()->can('promociones.crear')) { // Asumiendo que el permiso se llama promociones.crear o cupones.editar, usaré promociones.crear porque es lo que había
            abort(403);
        }
        $cupon = Cupon::findOrFail($id);
        $cupon->delete();
        $this->modal('modal-eliminar-cupon-' . $id)->close();
        Flux::toast(variant: 'success', text: 'Cupón movido a la papelera.');
    }

    public function exportar()
    {
        if (! auth()->user()->can('promociones.ver')) {
            abort(403);
        }
        return Excel::download(new CuponesExport($this->buildQuery()), 'cupones.xlsx');
    }

    private function buildQuery()
    {
        $query = Cupon::query()
            ->when($this->search, function ($q) {
                $q->where('codigo', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortBy, $this->sortDirection);

        if ($this->filtroEstado === 'activos') {
            $query->where('activo', true);
        } elseif ($this->filtroEstado === 'desactivados') {
            $query->where('activo', false);
        }

        if ($this->filtroTipo !== 'todos') {
            $query->where('tipo_descuento', $this->filtroTipo);
        }

        $hoy = now()->toDateString();
        if ($this->filtroVigencia === 'vigentes') {
            $query->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $hoy);
            })->where(function ($q) use ($hoy) {
                $q->whereNull('fecha_expiracion')->orWhere('fecha_expiracion', '>=', $hoy);
            });
        } elseif ($this->filtroVigencia === 'expirados') {
            $query->whereNotNull('fecha_expiracion')->where('fecha_expiracion', '<', $hoy);
        } elseif ($this->filtroVigencia === 'programados') {
            $query->whereNotNull('fecha_inicio')->where('fecha_inicio', '>', $hoy);
        }

        return $query;
    }

    public function with(): array
    {
        return [
            'cupones' => $this->buildQuery()->paginate(10)
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Cupones de Descuento') }}</flux:heading>
            <flux:subheading>{{ __('Gestión de códigos promocionales para los clientes.') }}</flux:subheading>
        </div>
        @can('promociones.crear')
            <flux:button variant="primary" icon="plus" href="{{ route('admin.cupones.create') }}" wire:navigate>
                {{ __('Nuevo Cupón') }}
            </flux:button>
        @endcan
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4 mb-6">
        <div class="flex flex-col sm:flex-row flex-wrap gap-3">
            <div class="flex-1 min-w-[200px]">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar por código...') }}" />
            </div>
            
            <flux:select wire:model.live="filtroTipo" class="sm:w-40">
                <option value="todos">{{ __('Tipo Dcto.') }}</option>
                <option value="fijo">{{ __('Fijo') }}</option>
                <option value="porcentaje">{{ __('Porcentaje') }}</option>
            </flux:select>
            
            <flux:select wire:model.live="filtroVigencia" class="sm:w-44">
                <option value="todos">{{ __('Vigencia') }}</option>
                <option value="vigentes">{{ __('Vigentes Hoy') }}</option>
                <option value="expirados">{{ __('Expirados') }}</option>
                <option value="programados">{{ __('Programados') }}</option>
            </flux:select>

            <flux:select wire:model.live="filtroEstado" class="sm:w-40">
                <option value="todos">{{ __('Estado') }}</option>
                <option value="activos">{{ __('Activos') }}</option>
                <option value="desactivados">{{ __('Desactivados') }}</option>
            </flux:select>

        </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden flex flex-col">

        <!-- Cabecera de tabla: Acciones -->
        <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30">
            <flux:button class="!bg-emerald-600 !text-white hover:!bg-emerald-700 border-none" size="sm" icon="arrow-down-tray" wire:click="exportar">
                {{ __('Exportar') }}
            </flux:button>

            <flux:button size="sm" class="!bg-red-600 !text-white hover:!bg-red-700 border-none" wire:click="resetFiltros" icon="arrow-path">
                {{ __('Limpiar') }}
            </flux:button>
        </div>

        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-4 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition" wire:click="sort('id')">
                            {{ __('ID') }} @if($sortBy === 'id') <flux:icon :name="'chevron-' . ($sortDirection === 'asc' ? 'up' : 'down')" class="w-4 h-4 inline" /> @endif
                        </th>
                        <th class="p-4 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition" wire:click="sort('codigo')">
                            {{ __('Código') }} @if($sortBy === 'codigo') <flux:icon :name="'chevron-' . ($sortDirection === 'asc' ? 'up' : 'down')" class="w-4 h-4 inline" /> @endif
                        </th>
                        <th class="p-4 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition" wire:click="sort('valor_descuento')">
                            {{ __('Valor') }} @if($sortBy === 'valor_descuento') <flux:icon :name="'chevron-' . ($sortDirection === 'asc' ? 'up' : 'down')" class="w-4 h-4 inline" /> @endif
                        </th>
                        <th class="p-4">{{ __('Usos') }}</th>
                        <th class="p-4 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition" wire:click="sort('fecha_expiracion')">
                            {{ __('Expiración') }} @if($sortBy === 'fecha_expiracion') <flux:icon :name="'chevron-' . ($sortDirection === 'asc' ? 'up' : 'down')" class="w-4 h-4 inline" /> @endif
                        </th>
                        <th class="p-4">{{ __('Estado') }}</th>
                        <th class="p-4 text-right">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($cupones as $cupon)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="p-4 font-medium text-zinc-900 dark:text-white">{{ $cupon->id }}</td>
                            <td class="p-4 font-mono font-bold text-blue-600 dark:text-blue-400">{{ $cupon->codigo }}</td>
                            <td class="p-4 text-emerald-600 dark:text-emerald-400 font-bold">
                                {{ $cupon->tipo_descuento === 'porcentaje' ? round($cupon->valor_descuento, 0) . '%' : '$' . $cupon->valor_descuento }}
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $cupon->usos_totales - $cupon->usos_restantes }} / {{ $cupon->usos_totales }}
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                @if($cupon->fecha_expiracion)
                                    {{ $cupon->fecha_expiracion->format('d/m/Y') }}
                                    @if($cupon->fecha_expiracion->isPast())
                                        <span class="text-red-500 text-xs ml-1">(Expirado)</span>
                                    @endif
                                @else
                                    Permanente
                                @endif
                            </td>
                            <td class="p-4">
                                <flux:badge variant="{{ $cupon->activo ? 'success' : 'danger' }}">
                                    {{ $cupon->activo ? 'Activo' : 'Inactivo' }}
                                </flux:badge>
                            </td>
                            <td class="p-4">
                                <div class="flex justify-end gap-2">
                                    @can('promociones.crear')
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" href="{{ route('admin.cupones.edit', $cupon->id) }}" wire:navigate title="Editar" />
                                        
                                        <flux:modal.trigger name="modal-eliminar-cupon-{{ $cupon->id }}">
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600" title="Eliminar" />
                                        </flux:modal.trigger>
                                        
                                        <x-modal-eliminar name="modal-eliminar-cupon-{{ $cupon->id }}" action="ejecutarEliminacion({{ $cupon->id }})" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-12 text-zinc-400">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.face-smile class="size-8 text-zinc-300" />
                                    <span>{{ $search ? __('No se encontraron resultados para ":query"', ['query' => $search]) : __('No hay cupones registrados.') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pie de tabla: Paginación + Info -->
        <div class="px-4 py-4 border-t border-zinc-200 dark:border-zinc-700">
            @if($cupones->hasPages())
                {{ $cupones->links() }}
            @else
                <p class="text-xs text-zinc-400">
                    {{ __(':total registro(s)', ['total' => $cupones->total()]) }}
                </p>
            @endif
        </div>
    </div>
</div>
