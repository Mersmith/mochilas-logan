<?php

use App\Models\SliderPrincipal;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Gestión de Sliders Principales')] class extends Component {
    use WithPagination;

    // ── Filtros ──────────────────────────────────────────
    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filtroEstado = 'todos'; // todos | activos | desactivados

    #[Url]
    public int $perPage = 10;

    public ?int $idEliminar = null;

    /**
     * Resetea la paginación cuando cambia algún filtro
     */
    public function updating($property): void
    {
        if (in_array($property, ['search', 'filtroEstado', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros(): void
    {
        $this->reset(['search', 'filtroEstado']);
        $this->perPage = 10;
        $this->resetPage();
    }

    /**
     * Devuelve el query builder base con los filtros aplicados.
     */
    protected function getBaseQuery()
    {
        $query = SliderPrincipal::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nombre', 'like', '%' . $this->search . '%')
                        ->orWhere('link', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('orden', 'asc')
            ->orderBy('id', 'asc');

        if ($this->filtroEstado === 'activos') {
            $query->where('activo', true);
        } elseif ($this->filtroEstado === 'desactivados') {
            $query->where('activo', false);
        }

        return $query;
    }

    #[Computed]
    public function sliders()
    {
        return $this->getBaseQuery()->paginate($this->perPage);
    }

    /**
     * Reordena los sliders mediante drag & drop (wire:sort)
     */
    public function reordenar(mixed $item, ?int $position = null): void
    {
        if (! auth()->user()->can('sliders.editar')) {
            abort(403, 'No tienes permiso para reordenar sliders.');
        }

        if (is_array($item)) {
            foreach ($item as $index => $id) {
                SliderPrincipal::where('id', $id)->update(['orden' => $index + 1]);
            }
        } elseif ($item !== null && $position !== null) {
            $sliders = SliderPrincipal::orderBy('orden', 'asc')->orderBy('id', 'asc')->pluck('id')->toArray();
            $ids = array_values(array_filter($sliders, fn ($id) => (string) $id !== (string) $item));
            array_splice($ids, $position, 0, [(int) $item]);

            foreach ($ids as $index => $id) {
                SliderPrincipal::where('id', $id)->update(['orden' => $index + 1]);
            }
        }

        Flux::toast(variant: 'success', text: __('Orden de los sliders actualizado correctamente.'));
    }

    /**
     * Mueve un slider una posición hacia arriba.
     */
    public function moverArriba(int $id): void
    {
        if (! auth()->user()->can('sliders.editar')) {
            abort(403);
        }

        $sliders = SliderPrincipal::orderBy('orden', 'asc')->orderBy('id', 'asc')->get();
        $index = $sliders->search(fn ($s) => $s->id === $id);

        if ($index !== false && $index > 0) {
            $current = $sliders[$index];
            $prev = $sliders[$index - 1];

            $tempOrden = $prev->orden;
            $prev->orden = $current->orden == $tempOrden ? $tempOrden + 1 : $current->orden;
            $current->orden = $tempOrden;

            $current->save();
            $prev->save();

            Flux::toast(variant: 'success', text: __('Orden actualizado.'));
        }
    }

    /**
     * Mueve un slider una posición hacia abajo.
     */
    public function moverAbajo(int $id): void
    {
        if (! auth()->user()->can('sliders.editar')) {
            abort(403);
        }

        $sliders = SliderPrincipal::orderBy('orden', 'asc')->orderBy('id', 'asc')->get();
        $index = $sliders->search(fn ($s) => $s->id === $id);

        if ($index !== false && $index < $sliders->count() - 1) {
            $current = $sliders[$index];
            $next = $sliders[$index + 1];

            $tempOrden = $next->orden;
            $next->orden = $current->orden == $tempOrden ? $tempOrden - 1 : $current->orden;
            $current->orden = $tempOrden;

            $current->save();
            $next->save();

            Flux::toast(variant: 'success', text: __('Orden actualizado.'));
        }
    }

    /**
     * Alterna el estado activo/inactivo de un slider.
     */
    public function toggleActivo(int $id): void
    {
        if (! auth()->user()->can('sliders.editar')) {
            abort(403, 'No tienes permiso para modificar sliders.');
        }

        $slider = SliderPrincipal::findOrFail($id);
        $slider->activo = ! $slider->activo;
        $slider->save();

        Flux::toast(
            variant: 'success',
            text: $slider->activo ? __('Slider activado.') : __('Slider desactivado.')
        );
    }

    public function confirmarEliminacion(int $id): void
    {
        $this->idEliminar = $id;
        $this->modal('modal-eliminar-slider')->show();
    }

    public function ejecutarEliminacion(): void
    {
        if (! auth()->user()->can('sliders.eliminar')) {
            abort(403, 'No tienes permiso para eliminar sliders.');
        }

        if ($this->idEliminar) {
            $slider = SliderPrincipal::findOrFail($this->idEliminar);

            // Eliminar archivos físicos si están guardados en el disco público
            if ($slider->img_computadora && ! str_starts_with($slider->img_computadora, 'http') && ! str_starts_with($slider->img_computadora, 'assets/')) {
                Storage::disk('public')->delete($slider->img_computadora);
            }
            if ($slider->img_movil && ! str_starts_with($slider->img_movil, 'http') && ! str_starts_with($slider->img_movil, 'assets/')) {
                Storage::disk('public')->delete($slider->img_movil);
            }

            $slider->delete();
            $this->idEliminar = null;

            Flux::toast(variant: 'success', text: __('Slider eliminado permanentemente.'));
        }

        $this->modal('modal-eliminar-slider')->close();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Sliders Principales') }}</flux:heading>
            <flux:subheading>{{ __('Administra los banners publicitarios e imágenes que se muestran en la página principal.') }}</flux:subheading>
        </div>
        @can('sliders.crear')
            <flux:button variant="primary" icon="plus" href="{{ route('admin.sliders.create') }}" wire:navigate>
                {{ __('Nuevo Slider') }}
            </flux:button>
        @endcan
    </div>

    <!-- Filtros -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                    placeholder="{{ __('Buscar por nombre o enlace...') }}" />
            </div>
            <flux:select wire:model.live="filtroEstado" class="sm:w-44">
                <option value="todos">{{ __('Todos los estados') }}</option>
                <option value="activos">{{ __('Solo Activos') }}</option>
                <option value="desactivados">{{ __('Solo Desactivados') }}</option>
            </flux:select>
            <flux:button size="sm" class="!bg-red-600 !text-white hover:!bg-red-700 border-none self-end sm:self-auto" wire:click="resetFiltros" icon="arrow-path">
                {{ __('Limpiar') }}
            </flux:button>
        </div>
    </div>

    <!-- Tabla con Soporte de Ordenamiento Drag & Drop -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden flex flex-col">
        <!-- Cabecera de tabla -->
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30">
            <div class="flex items-center gap-2 text-xs text-zinc-500">
                <flux:icon.arrows-up-down class="size-4 text-emerald-600" />
                <span>{{ __('Arrastra la fila o usa las flechas para ordenar la prioridad en la portada.') }}</span>
            </div>

            <div class="flex items-center gap-2 text-sm text-zinc-500 ml-auto">
                <span class="hidden sm:inline">{{ __('Mostrar') }}</span>
                <flux:select wire:model.live="perPage" class="w-20">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </flux:select>
            </div>
        </div>

        <!-- Contenido de tabla -->
        <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-3 w-28 text-center">{{ __('Orden') }}</th>
                        <th class="p-3 w-48">{{ __('Vistas Previas') }}</th>
                        <th class="p-3">{{ __('Nombre / Título') }}</th>
                        <th class="p-3">{{ __('Enlace') }}</th>
                        <th class="p-3 w-28 text-center">{{ __('Estado') }}</th>
                        @can('sliders.editar')
                            <th class="p-3 w-28 text-right">{{ __('Acciones') }}</th>
                        @endcan
                    </tr>
                </thead>
                <tbody wire:sort="reordenar" class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($this->sliders as $slider)
                        <tr wire:sort:item="{{ $slider->id }}" wire:key="slider-{{ $slider->id }}" class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/50 transition-colors group">
                            <!-- Control de Orden -->
                            <td class="p-3">
                                <div class="flex items-center justify-center gap-1.5">
                                    <div wire:sort:handle class="cursor-grab active:cursor-grabbing p-1 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 rounded" title="{{ __('Arrastrar para reordenar') }}">
                                        <flux:icon.bars-3 class="size-4" />
                                    </div>
                                    <span class="inline-flex items-center justify-center size-6 text-xs font-mono font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 rounded-md border border-zinc-200 dark:border-zinc-700">
                                        {{ $slider->orden }}
                                    </span>
                                    <div class="flex flex-col gap-0.5">
                                        <button type="button" wire:click="moverArriba({{ $slider->id }})" class="p-0.5 text-zinc-400 hover:text-emerald-600 rounded transition-colors" title="{{ __('Subir posición') }}">
                                            <flux:icon.chevron-up class="size-3.5" />
                                        </button>
                                        <button type="button" wire:click="moverAbajo({{ $slider->id }})" class="p-0.5 text-zinc-400 hover:text-emerald-600 rounded transition-colors" title="{{ __('Bajar posición') }}">
                                            <flux:icon.chevron-down class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </td>

                            <!-- Vistas Previas (Desktop + Móvil) -->
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="relative group/desk" title="{{ __('Vista Computadora (Desktop)') }}">
                                        <img src="{{ $slider->img_computadora_url }}" alt="Desktop preview" class="w-20 h-10 object-cover rounded-md border border-zinc-200 dark:border-zinc-700 shadow-xs" />
                                        <span class="absolute bottom-0 right-0 bg-black/70 text-[9px] text-white px-1 rounded-tl font-mono">PC</span>
                                    </div>
                                    <div class="relative group/mov" title="{{ __('Vista Teléfono Móvil') }}">
                                        <img src="{{ $slider->img_movil_url }}" alt="Mobile preview" class="w-8 h-10 object-cover rounded-md border border-zinc-200 dark:border-zinc-700 shadow-xs" />
                                        <span class="absolute bottom-0 right-0 bg-black/70 text-[9px] text-white px-1 rounded-tl font-mono">Móv</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Nombre -->
                            <td class="p-3">
                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $slider->nombre ?: __('(Sin título)') }}
                                </div>
                                <div class="text-xs text-zinc-400">
                                    {{ __('Creado:') }} {{ $slider->created_at->format('d/m/Y') }}
                                </div>
                            </td>

                            <!-- Enlace -->
                            <td class="p-3">
                                @if($slider->link)
                                    <a href="{{ $slider->link }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300 font-mono truncate max-w-xs underline">
                                        <flux:icon.link class="size-3.5 shrink-0" />
                                        <span class="truncate">{{ $slider->link }}</span>
                                    </a>
                                @else
                                    <span class="text-xs text-zinc-400 italic">{{ __('Sin enlace') }}</span>
                                @endif
                            </td>

                            <!-- Estado Activo -->
                            <td class="p-3 text-center">
                                <button type="button" wire:click="toggleActivo({{ $slider->id }})" class="inline-flex items-center gap-1 cursor-pointer" title="{{ __('Clic para alternar estado') }}">
                                    @if($slider->activo)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            <flux:icon.check-circle class="size-3.5 mr-1" />
                                            {{ __('Activo') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                            <flux:icon.pause-circle class="size-3.5 mr-1 text-amber-500" />
                                            {{ __('Inactivo') }}
                                        </span>
                                    @endif
                                </button>
                            </td>

                            <!-- Acciones -->
                            @can('sliders.editar')
                                <td class="p-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <flux:button variant="ghost" icon="pencil-square" size="sm"
                                            href="{{ route('admin.sliders.edit', $slider->id) }}" wire:navigate
                                            title="{{ __('Editar slider') }}" />
                                        @can('sliders.eliminar')
                                            <flux:button variant="ghost" icon="trash" size="sm" class="text-red-500 hover:text-red-600"
                                                wire:click="confirmarEliminacion({{ $slider->id }})"
                                                title="{{ __('Eliminar slider') }}" />
                                        @endcan
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->can('sliders.editar') ? 6 : 5 }}" class="text-center py-12 text-zinc-400">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon.photo class="size-10 text-zinc-300 dark:text-zinc-600" />
                                    <span class="text-base font-medium">{{ $search ? __('No se encontraron sliders para ":query"', ['query' => $search]) : __('No hay sliders registrados.') }}</span>
                                    @can('sliders.crear')
                                        <flux:button variant="primary" size="sm" icon="plus" href="{{ route('admin.sliders.create') }}" wire:navigate class="mt-2">
                                            {{ __('Crear primer slider') }}
                                        </flux:button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pie de tabla -->
        <div class="px-4 py-4 border-t border-zinc-200 dark:border-zinc-700">
            @if($this->sliders->hasPages())
                {{ $this->sliders->links() }}
            @else
                <p class="text-xs text-zinc-400">
                    {{ __(':total slider(s) en total', ['total' => $this->sliders->total()]) }}
                </p>
            @endif
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <x-modal-eliminar name="modal-eliminar-slider" title="¿Eliminar este slider?"
        description="Se eliminará permanentemente la imagen y la configuración de este banner del sistema."
        :isPermanent="true" action="ejecutarEliminacion" />
</div>
