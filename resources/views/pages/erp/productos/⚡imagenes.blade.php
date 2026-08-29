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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Imagen Principal -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <flux:heading size="lg" class="mb-4">{{ __('Imagen Principal (Portada)') }}</flux:heading>

        <div class="mb-6">
            @if($producto->getFirstMediaUrl('imagen_principal'))
                <div class="relative group inline-block">
                    <img src="{{ $producto->getFirstMediaUrl('imagen_principal', 'card') }}" class="h-48 w-48 object-cover rounded-lg border border-zinc-200 shadow-sm">
                    <button type="button" wire:click="eliminarMedia({{ $producto->getFirstMedia('imagen_principal')->id }})" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition-opacity" title="Eliminar imagen">
                        <flux:icon.trash class="size-4" />
                    </button>
                </div>
            @else
                <div class="h-48 w-48 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex items-center justify-center border border-zinc-200 dark:border-zinc-700">
                    <span class="text-zinc-400">{{ __('Sin portada') }}</span>
                </div>
            @endif
        </div>

        <form wire:submit.prevent="guardarImagenPrincipal" class="space-y-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
            <flux:field>
                <flux:label>{{ __('Reemplazar / Subir Nueva') }}</flux:label>
                <input type="file" wire:model="nueva_imagen_principal" class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-900/30 dark:file:text-emerald-400" accept="image/jpeg,image/png,image/webp">
                <flux:error name="nueva_imagen_principal" />
            </flux:field>

            @if($nueva_imagen_principal)
                <div class="relative inline-block">
                    <img src="{{ $nueva_imagen_principal->temporaryUrl() }}" class="h-24 w-24 object-cover rounded-lg shadow-sm border border-emerald-500">
                    <button type="button" wire:click="$set('nueva_imagen_principal', null)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-100 shadow">
                        <flux:icon.x-mark class="size-3" />
                    </button>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit" size="sm" class="mt-2" :disabled="!$nueva_imagen_principal">
                    {{ __('Guardar Portada') }}
                </flux:button>
            </div>
        </form>
    </div>

    <!-- Galería de Imágenes -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <flux:heading size="lg" class="mb-4">{{ __('Galería de Imágenes') }}</flux:heading>

        @php
            $galeria_actual = $producto->getMedia('galeria');
        @endphp

        @if($galeria_actual->count() > 0)
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mb-6">
                @foreach($galeria_actual as $media)
                    <div class="relative group">
                        <img src="{{ $media->getUrl('thumb') }}" class="h-24 w-full object-cover rounded-lg border border-zinc-200 shadow-sm">
                        <button type="button" wire:click="eliminarMedia({{ $media->id }})" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity" title="Eliminar imagen">
                            <flux:icon.trash class="size-3" />
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="mb-6 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg text-center text-sm text-zinc-500">
                {{ __('La galería está vacía.') }}
            </div>
        @endif

        <form wire:submit.prevent="subirImagenesGaleria" class="space-y-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
            <flux:field>
                <flux:label>{{ __('Agregar Imágenes (Múltiple)') }}</flux:label>
                <input type="file" wire:model="nuevas_imagenes_galeria" multiple class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-400" accept="image/jpeg,image/png,image/webp">
                <flux:error name="nuevas_imagenes_galeria.*" />
            </flux:field>

            @if(count($nuevas_imagenes_galeria) > 0)
                <div class="grid grid-cols-4 gap-2">
                    @foreach($nuevas_imagenes_galeria as $index => $img)
                        <div class="relative">
                            <img src="{{ $img->temporaryUrl() }}" class="h-16 w-full object-cover rounded-md border border-blue-500 shadow-sm">
                            <button type="button" wire:click="removerNuevaImagenGaleria({{ $index }})" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-100 shadow">
                                <flux:icon.x-mark class="size-3" />
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit" size="sm" class="mt-2" :disabled="empty($nuevas_imagenes_galeria)">
                    {{ __('Subir a Galería') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
