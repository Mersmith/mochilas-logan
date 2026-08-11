<?php

use App\Models\Cliente;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;

new #[Title('Gestión de Clientes')] class extends Component {
    use WithPagination;

    // ── Filtros ──────────────────────────────────────────
    #[Url(as: 'q')]
    public string $search = '';
    #[Url]
    public string $filtroTipoPersona = ''; // natural | juridica
    #[Url]
    public string $filtroTipoCliente = ''; // minorista | mayorista | emprendedor
    #[Url]
    public string $filtroEstado = 'activos'; // activos | desactivados | todos
    #[Url]
    public int $perPage = 10;

    /**
     * Resetea la paginación cuando cambia algún filtro
     */
    public function updating($property)
    {
        if (in_array($property, ['search', 'filtroTipoPersona', 'filtroTipoCliente', 'filtroEstado', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros()
    {
        $this->reset(['search', 'filtroTipoPersona', 'filtroTipoCliente']);
        $this->filtroEstado = 'todos';
        $this->perPage = 10;
        $this->resetPage();
    }

    /**
     * Devuelve el query builder base con los filtros aplicados.
     */
    protected function getBaseQuery()
    {
        $query = Cliente::with(['user', 'listaPrecio'])
            ->when($this->search, function (Builder $q) {
                $q->where(function ($q) {
                    $q->where('dni', 'like', '%'.$this->search.'%')
                        ->orWhere('ruc', 'like', '%'.$this->search.'%')
                        ->orWhere('razon_social', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', function ($qu) {
                            $qu->where('name', 'like', '%'.$this->search.'%')
                               ->orWhere('email', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->filtroTipoPersona, fn ($q) => $q->where('tipo_persona', $this->filtroTipoPersona))
            ->when($this->filtroTipoCliente, fn ($q) => $q->where('tipo_cliente', $this->filtroTipoCliente))
            ->orderBy('id', 'desc');

        // Filtro de estado de cuenta (Activo / Inactivo)
        if ($this->filtroEstado === 'activos') {
            $query->where('activo', true);
        } elseif ($this->filtroEstado === 'desactivados') {
            $query->where('activo', false);
        }

        return $query;
    }

    /**
     * Lista de clientes paginada.
     */
    #[Computed]
    public function clientes()
    {
        return $this->getBaseQuery()->paginate($this->perPage);
    }

    /**
     * Activar / Desactivar cliente.
     */
    public function toggleActivo(int $id): void
    {
        if (! auth()->user()->can('clientes.editar')) {
            abort(403);
        }

        $cliente = Cliente::findOrFail($id);
        $cliente->update(['activo' => ! $cliente->activo]);

        // Opcional: También desactivar/activar el User asociado?
        // $cliente->user->update(['activo' => $cliente->activo]);

        Flux::toast(
            variant: $cliente->activo ? 'success' : 'warning',
            text: $cliente->activo ? 'Cliente activado.' : 'Cliente desactivado.'
        );
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Clientes') }}</flux:heading>
            <flux:subheading>{{ __('Administra los clientes y sus perfiles comerciales.') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('clientes.crear')
                <flux:button variant="primary" icon="plus" href="{{ route('admin.clientes.create') }}" wire:navigate>
                    {{ __('Nuevo Cliente') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="{{ __('Buscar por nombre, documento o email...') }}"
                />
            </div>
            <flux:select wire:model.live="filtroTipoPersona" class="sm:w-48">
                <option value="">{{ __('Todas las personas') }}</option>
                <option value="natural">{{ __('Persona Natural') }}</option>
                <option value="juridica">{{ __('Persona Jurídica') }}</option>
            </flux:select>
            <flux:select wire:model.live="filtroTipoCliente" class="sm:w-48">
                <option value="">{{ __('Todos los tipos') }}</option>
                <option value="minorista">{{ __('Minorista') }}</option>
                <option value="mayorista">{{ __('Mayorista') }}</option>
                <option value="emprendedor">{{ __('Emprendedor') }}</option>
            </flux:select>
            <flux:select wire:model.live="filtroEstado" class="sm:w-44">
                <option value="todos">{{ __('Todos los estados') }}</option>
                <option value="activos">{{ __('Activos') }}</option>
                <option value="desactivados">{{ __('Desactivados') }}</option>
            </flux:select>
            <flux:button variant="ghost" wire:click="resetFiltros" icon="arrow-path" class="sm:w-auto">
                {{ __('Limpiar') }}
            </flux:button>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden flex flex-col">
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-3">{{ __('Cliente / Documento') }}</th>
                        <th class="p-3">{{ __('Email') }}</th>
                        <th class="p-3 text-center">{{ __('Clasificación') }}</th>
                        <th class="p-3 text-center">{{ __('Estado') }}</th>
                        @can('clientes.editar')
                            <th class="p-3"></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->clientes as $cliente)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-700 dark:text-indigo-300 font-semibold text-xs">
                                        {{ substr($cliente->nombreMostrar(), 0, 2) }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-zinc-900 dark:text-white">{{ $cliente->nombreMostrar() }}</span>
                                        <span class="text-xs text-zinc-500">
                                            @if($cliente->esEmpresa())
                                                RUC: {{ $cliente->ruc }}
                                            @else
                                                DNI: {{ $cliente->dni ?? 'N/A' }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                {{ $cliente->user->email }}
                                <br>
                                <span class="text-xs">{{ $cliente->telefono }}</span>
                            </td>
                            <td class="p-3 text-center">
                                <div class="flex flex-col gap-1 items-center">
                                    <flux:badge color="blue" size="sm">{{ ucfirst($cliente->tipo_cliente) }}</flux:badge>
                                    @if($cliente->listaPrecio)
                                        <span class="text-xs text-zinc-500">{{ $cliente->listaPrecio->nombre }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3 text-center">
                                @if($cliente->activo)
                                    <flux:badge color="green">{{ __('Activo') }}</flux:badge>
                                @else
                                    <flux:badge color="orange">{{ __('Inactivo') }}</flux:badge>
                                @endif
                            </td>
                            @can('clientes.editar')
                                <td class="p-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button
                                            variant="ghost"
                                            icon="pencil-square"
                                            size="sm"
                                            href="{{ route('admin.clientes.edit', $cliente->id) }}"
                                            wire:navigate
                                            title="{{ __('Editar') }}"
                                        />
                                        <flux:button
                                            variant="ghost"
                                            icon="{{ $cliente->activo ? 'pause-circle' : 'play-circle' }}"
                                            size="sm"
                                            class="{{ $cliente->activo ? 'text-orange-500 hover:text-orange-600' : 'text-green-500 hover:text-green-600' }}"
                                            wire:click="toggleActivo({{ $cliente->id }})"
                                            wire:confirm="{{ $cliente->activo ? '¿Desactivar este cliente?' : '¿Activar este cliente?' }}"
                                            title="{{ $cliente->activo ? __('Desactivar') : __('Activar') }}"
                                        />
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-12 text-zinc-500">
                                <flux:icon.users class="size-10 mx-auto mb-2 opacity-30" />
                                {{ __('No se encontraron clientes.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->clientes->hasPages())
            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
                <div class="w-full sm:w-auto">
                    {{ $this->clientes->links() }}
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
                <span>{{ $this->clientes->total() }} {{ __('cliente(s) encontrado(s)') }}</span>
                @if($this->clientes->total() > 0)
                <div class="hidden sm:flex items-center gap-2 text-sm text-zinc-500">
                    <span>{{ __('Mostrar') }}</span>
                    <flux:select wire:model.live="perPage" class="w-20">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </flux:select>
                </div>
                @endif
            </div>
        @endif
    </div>
</div>
