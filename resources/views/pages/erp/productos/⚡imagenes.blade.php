<?php

use App\Models\Producto;
use Flux\Flux;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

new class extends Component {
    use WithFileUploads;

    public Producto $producto;

    public $nueva_imagen_principal;
    public $nuevas_imagenes_galeria = [];

    public function mount(Producto $producto): void
    {
        $this->producto = $producto;
    }

    public function guardarImagenPrincipal(): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $this->validate([
            'nueva_imagen_principal' => 'required|image|max:10240',
        ]);

        // Eliminar la anterior si existe
        $this->producto->clearMediaCollection('imagen_principal');

        // Agregar nueva
        $this->producto->addMedia($this->nueva_imagen_principal)->toMediaCollection('imagen_principal');

        $this->nueva_imagen_principal = null;
        $this->producto->refresh();
        Flux::toast(variant: 'success', text: __('Imagen principal actualizada correctamente.'));
    }

    public function subirImagenesGaleria(): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $this->validate([
            'nuevas_imagenes_galeria.*' => 'required|image|max:10240',
        ]);

        foreach ($this->nuevas_imagenes_galeria as $imagen) {
            $this->producto->addMedia($imagen)->toMediaCollection('galeria');
        }

        $this->nuevas_imagenes_galeria = [];
        $this->producto->refresh();
        Flux::toast(variant: 'success', text: __('Imágenes agregadas a la galería.'));
    }

    public function removerNuevaImagenGaleria(int $index): void
    {
        if (isset($this->nuevas_imagenes_galeria[$index])) {
            unset($this->nuevas_imagenes_galeria[$index]);
            $this->nuevas_imagenes_galeria = array_values($this->nuevas_imagenes_galeria);
        }
    }

    public function reordenarGaleria(mixed $item, ?int $position = null): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

        if (is_array($item)) {
            Media::setNewOrder($item);
        } elseif ($item !== null && $position !== null) {
            $galeria = $this->producto->getMedia('galeria');
            $ids = $galeria->pluck('id')->filter(fn ($id) => (string) $id !== (string) $item)->values()->toArray();
            array_splice($ids, $position, 0, [(int) $item]);
            Media::setNewOrder($ids);
        }

        $this->producto->refresh();
        Flux::toast(variant: 'success', text: __('Orden de la galería actualizado.'));
    }

    public function moverIzquierda(int $mediaId): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $galeria = $this->producto->getMedia('galeria');
        $ids = $galeria->pluck('id')->toArray();
        $index = array_search($mediaId, $ids);

        if ($index !== false && $index > 0) {
            $prev = $ids[$index - 1];
            $ids[$index - 1] = $ids[$index];
            $ids[$index] = $prev;

            Media::setNewOrder($ids);
            $this->producto->refresh();
            Flux::toast(variant: 'success', text: __('Posición actualizada.'));
        }
    }

    public function moverDerecha(int $mediaId): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $galeria = $this->producto->getMedia('galeria');
        $ids = $galeria->pluck('id')->toArray();
        $index = array_search($mediaId, $ids);

        if ($index !== false && $index < count($ids) - 1) {
            $next = $ids[$index + 1];
            $ids[$index + 1] = $ids[$index];
            $ids[$index] = $next;

            Media::setNewOrder($ids);
            $this->producto->refresh();
            Flux::toast(variant: 'success', text: __('Posición actualizada.'));
        }
    }

    public function hacerPortada(int $mediaId): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $media = Media::findOrFail($mediaId);
        $portadaActual = $this->producto->getFirstMedia('imagen_principal');

        // Mover la seleccionada a portada
        $media->collection_name = 'imagen_principal';
        $media->save();

        // Mover la portada anterior a la galería
        if ($portadaActual && $portadaActual->id !== $media->id) {
            $portadaActual->collection_name = 'galeria';
            $portadaActual->save();
        }

        $this->producto->refresh();
        Flux::toast(variant: 'success', text: __('Imagen establecida como portada principal.'));
    }

    public function eliminarMedia(int $mediaId): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $media = Media::findOrFail($mediaId);
        $media->delete();
        $this->producto->refresh();
        Flux::toast(variant: 'success', text: __('Imagen eliminada.'));
    }
}; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Columna 1: Imagen Principal -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm h-fit">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="lg">{{ __('Portada Principal') }}</flux:heading>
            <span class="text-xs bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-semibold px-2 py-0.5 rounded-full">
                {{ __('Principal') }}
            </span>
        </div>

        <div class="mb-6 flex justify-center">
            @if($producto->getFirstMediaUrl('imagen_principal'))
                <div class="relative group w-full">
                    <img src="{{ $producto->getFirstMediaUrl('imagen_principal', 'card') }}" class="h-64 w-full object-cover rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm">
                    <button type="button" wire:click="eliminarMedia({{ $producto->getFirstMedia('imagen_principal')->id }})" class="absolute top-2 right-2 bg-red-600 hover:bg-red-700 text-white rounded-full p-2 opacity-0 group-hover:opacity-100 transition-opacity shadow-md" title="{{ __('Eliminar portada') }}">
                        <flux:icon.trash class="size-4" />
                    </button>
                </div>
            @else
                <div class="h-64 w-full bg-zinc-100 dark:bg-zinc-800/60 rounded-lg flex flex-col items-center justify-center border border-dashed border-zinc-300 dark:border-zinc-700 text-zinc-400 gap-2">
                    <flux:icon.photo class="size-12 stroke-1" />
                    <span class="text-xs">{{ __('Sin portada asignada') }}</span>
                </div>
            @endif
        </div>

        <form wire:submit.prevent="guardarImagenPrincipal" class="space-y-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
            <flux:field>
                <flux:label>{{ __('Subir o Reemplazar') }}</flux:label>
                <input type="file" wire:model="nueva_imagen_principal" class="block w-full text-xs text-zinc-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-900/30 dark:file:text-emerald-400" accept="image/jpeg,image/png,image/webp">
                <flux:error name="nueva_imagen_principal" />
            </flux:field>

            @if($nueva_imagen_principal)
                <div class="relative inline-block mt-2">
                    <img src="{{ $nueva_imagen_principal->temporaryUrl() }}" class="h-20 w-20 object-cover rounded-lg shadow-sm border-2 border-emerald-500">
                    <button type="button" wire:click="$set('nueva_imagen_principal', null)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-100 shadow">
                        <flux:icon.x-mark class="size-3" />
                    </button>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit" size="sm" class="w-full" icon="check" :disabled="!$nueva_imagen_principal">
                    {{ __('Guardar Portada') }}
                </flux:button>
            </div>
        </form>
    </div>

    <!-- Columna 2 y 3: Galería de Imágenes con Reordenamiento -->
    <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-zinc-200 dark:border-zinc-700 pb-4">
            <div>
                <flux:heading size="lg">{{ __('Galería de Imágenes') }}</flux:heading>
                <flux:subheading>{{ __('Usa las flechas para mover su posición o el botón estrella para definirla como portada.') }}</flux:subheading>
            </div>
            @php $galeria_actual = $producto->getMedia('galeria'); @endphp
            <span class="text-xs text-zinc-500 font-medium self-start sm:self-auto">
                {{ __(':count imagen(es)', ['count' => $galeria_actual->count()]) }}
            </span>
        </div>

        @if($galeria_actual->count() > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4" wire:sort="reordenarGaleria">
                @foreach($galeria_actual as $index => $media)
                    <div wire:sort:item="{{ $media->id }}" wire:key="media-{{ $media->id }}" class="relative group bg-zinc-50 dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm flex flex-col">
                        <!-- Preview Imagen -->
                        <div class="relative h-32 w-full overflow-hidden bg-zinc-100 dark:bg-zinc-900">
                            <img src="{{ $media->getUrl('thumb') }}" class="h-full w-full object-cover">

                            <!-- Badge de Posición -->
                            <span class="absolute top-2 left-2 bg-black/70 backdrop-blur-sm text-white font-mono text-[10px] px-1.5 py-0.5 rounded shadow">
                                #{{ $index + 1 }}
                            </span>

                            <!-- Botón Eliminar Rápido -->
                            <button type="button" wire:click="eliminarMedia({{ $media->id }})" class="absolute top-2 right-2 bg-red-600 hover:bg-red-700 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity shadow" title="{{ __('Eliminar imagen') }}">
                                <flux:icon.trash class="size-3.5" />
                            </button>
                        </div>

                        <!-- Barra de Herramientas / Orden -->
                        <div class="p-2 bg-white dark:bg-zinc-800 flex items-center justify-between gap-1 border-t border-zinc-100 dark:border-zinc-700/60">
                            <!-- Mover Izquierda / Anterior -->
                            <button type="button" wire:click="moverIzquierda({{ $media->id }})" class="p-1 rounded text-zinc-500 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-700 disabled:opacity-30" @if($loop->first) disabled @endif title="{{ __('Mover a la izquierda') }}">
                                <flux:icon.arrow-left class="size-4" />
                            </button>

                            <!-- Hacer Portada -->
                            <button type="button" wire:click="hacerPortada({{ $media->id }})" class="px-2 py-1 text-[11px] font-medium text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 rounded flex items-center gap-1" title="{{ __('Convertir en portada principal') }}">
                                <flux:icon.star class="size-3.5 fill-amber-500 text-amber-500" />
                                <span class="hidden sm:inline">{{ __('Portada') }}</span>
                            </button>

                            <!-- Mover Derecha / Siguiente -->
                            <button type="button" wire:click="moverDerecha({{ $media->id }})" class="p-1 rounded text-zinc-500 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-700 disabled:opacity-30" @if($loop->last) disabled @endif title="{{ __('Mover a la derecha') }}">
                                <flux:icon.arrow-right class="size-4" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-8 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl text-center text-zinc-400 border border-dashed border-zinc-200 dark:border-zinc-700 flex flex-col items-center gap-2">
                <flux:icon.photo class="size-10 stroke-1 text-zinc-300 dark:text-zinc-600" />
                <span class="text-sm font-medium">{{ __('No hay imágenes en la galería.') }}</span>
                <span class="text-xs text-zinc-500">{{ __('Usa el formulario de abajo para subir múltiples imágenes a la vez.') }}</span>
            </div>
        @endif

        <!-- Formulario de Carga Múltiple -->
        <form wire:submit.prevent="subirImagenesGaleria" class="space-y-4 border-t border-zinc-200 dark:border-zinc-700 pt-5">
            <flux:field>
                <flux:label>{{ __('Subir nuevas fotos a la galería') }}</flux:label>
                <input type="file" wire:model="nuevas_imagenes_galeria" multiple class="block w-full text-xs text-zinc-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-400" accept="image/jpeg,image/png,image/webp">
                <flux:error name="nuevas_imagenes_galeria.*" />
            </flux:field>

            @if(count($nuevas_imagenes_galeria) > 0)
                <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                    @foreach($nuevas_imagenes_galeria as $index => $img)
                        <div class="relative group">
                            <img src="{{ $img->temporaryUrl() }}" class="h-16 w-full object-cover rounded-lg border-2 border-blue-500 shadow-sm">
                            <button type="button" wire:click="removerNuevaImagenGaleria({{ $index }})" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white rounded-full p-0.5 shadow">
                                <flux:icon.x-mark class="size-3" />
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit" size="sm" icon="arrow-up-tray" :disabled="empty($nuevas_imagenes_galeria)">
                    {{ __('Subir a Galería') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
