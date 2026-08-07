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

    public string $search = '';
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

    public function updatedSearch()
    {
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
        return Cupon::query()
            ->when($this->search, function ($query) {
                $query->where('codigo', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortBy, $this->sortDirection);
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
        <div class="flex gap-2">
            <flux:button variant="ghost" icon="document-arrow-down" wire:click="exportar">
                {{ __('Exportar') }}
            </flux:button>
            @can('promociones.crear')
                <flux:button variant="primary" icon="plus" href="{{ route('admin.cupones.create') }}" wire:navigate>
                    {{ __('Nuevo Cupón') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por código..." class="max-w-md" />
        </div>

        <div class="overflow-x-auto">
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
                            <td colspan="7" class="text-center py-12 text-zinc-500 dark:text-zinc-400">
                                {{ __('No se encontraron cupones.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cupones->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $cupones->links() }}
            </div>
        @endif
    </div>
</div>
