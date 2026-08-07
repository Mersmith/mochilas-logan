<?php

use App\Models\Descuento;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Flux\Flux;
use App\Exports\DescuentosExport;
use Maatwebsite\Excel\Facades\Excel;

new #[Title('Descuentos')] class extends Component {
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
        if (! auth()->user()->can('descuentos.editar')) {
            abort(403);
        }
        $descuento = Descuento::findOrFail($id);
        $descuento->delete();
        $this->modal('modal-eliminar-descuento-' . $id)->close();
        Flux::toast(variant: 'success', text: 'Descuento movido a la papelera.');
    }

    public function exportar()
    {
        if (! auth()->user()->can('descuentos.ver')) {
            abort(403);
        }
        return Excel::download(new DescuentosExport($this->buildQuery()), 'descuentos.xlsx');
    }

    private function buildQuery()
    {
        return Descuento::query()
            ->when($this->search, function ($query) {
                $query->where('nombre', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortBy, $this->sortDirection);
    }

    public function with(): array
    {
        return [
            'descuentos' => $this->buildQuery()->paginate(10)
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Descuentos') }}</flux:heading>
            <flux:subheading>{{ __('Gestión de descuentos generales de la tienda.') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button variant="ghost" icon="document-arrow-down" wire:click="exportar">
                {{ __('Exportar') }}
            </flux:button>
            @can('descuentos.editar')
                <flux:button variant="primary" icon="plus" href="{{ route('admin.descuentos.create') }}" wire:navigate>
                    {{ __('Nuevo Descuento') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Buscar por nombre..." class="max-w-md" />
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-4 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition" wire:click="sort('id')">
                            {{ __('ID') }} @if($sortBy === 'id') <flux:icon :name="'chevron-' . ($sortDirection === 'asc' ? 'up' : 'down')" class="w-4 h-4 inline" /> @endif
                        </th>
                        <th class="p-4 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition" wire:click="sort('nombre')">
                            {{ __('Nombre') }} @if($sortBy === 'nombre') <flux:icon :name="'chevron-' . ($sortDirection === 'asc' ? 'up' : 'down')" class="w-4 h-4 inline" /> @endif
                        </th>
                        <th class="p-4 cursor-pointer hover:bg-zinc-100 dark:hover:bg-zinc-800 transition" wire:click="sort('porcentaje_descuento')">
                            {{ __('Porcentaje') }} @if($sortBy === 'porcentaje_descuento') <flux:icon :name="'chevron-' . ($sortDirection === 'asc' ? 'up' : 'down')" class="w-4 h-4 inline" /> @endif
                        </th>
                        <th class="p-4">{{ __('Vigencia') }}</th>
                        <th class="p-4">{{ __('Estado') }}</th>
                        <th class="p-4 text-right">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($descuentos as $descuento)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="p-4 font-medium text-zinc-900 dark:text-white">{{ $descuento->id }}</td>
                            <td class="p-4 font-medium">{{ $descuento->nombre }}</td>
                            <td class="p-4 text-emerald-600 dark:text-emerald-400 font-bold text-lg">{{ $descuento->porcentaje_descuento }}%</td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                @if($descuento->fecha_inicio && $descuento->fecha_fin)
                                    Del {{ $descuento->fecha_inicio->format('d/m/Y') }}<br>al {{ $descuento->fecha_fin->format('d/m/Y') }}
                                @else
                                    Permanente
                                @endif
                            </td>
                            <td class="p-4">
                                <flux:badge variant="{{ $descuento->activo ? 'success' : 'danger' }}">
                                    {{ $descuento->activo ? 'Activo' : 'Inactivo' }}
                                </flux:badge>
                            </td>
                            <td class="p-4">
                                <div class="flex justify-end gap-2">
                                    @can('descuentos.editar')
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" href="{{ route('admin.descuentos.edit', $descuento->id) }}" wire:navigate title="Editar" />
                                        
                                        <flux:modal.trigger name="modal-eliminar-descuento-{{ $descuento->id }}">
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600" title="Eliminar" />
                                        </flux:modal.trigger>
                                        
                                        <x-modal-eliminar name="modal-eliminar-descuento-{{ $descuento->id }}" action="ejecutarEliminacion({{ $descuento->id }})" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-zinc-500 dark:text-zinc-400">
                                {{ __('No se encontraron descuentos.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($descuentos->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $descuentos->links() }}
            </div>
        @endif
    </div>
</div>
