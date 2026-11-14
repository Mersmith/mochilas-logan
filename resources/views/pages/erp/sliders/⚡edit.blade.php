<?php

use App\Models\SliderPrincipal;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Editar Slider')] class extends Component {
    use WithFileUploads;

    public SliderPrincipal $slider;
    public string $nombre = '';
    public string $link = '';
    public $img_computadora = null;
    public $img_movil = null;
    public int $orden = 0;
    public bool $activo = true;

    public function mount(SliderPrincipal $slider): void
    {
        $this->slider = $slider;
        $this->nombre = $slider->nombre ?? '';
        $this->link = $slider->link ?? '';
        $this->orden = $slider->orden;
        $this->activo = $slider->activo;
    }

    public function guardar()
    {
        if (! auth()->user()->can('sliders.editar')) {
            abort(403, 'No tienes permiso para editar sliders.');
        }

        $this->validate([
            'nombre' => 'nullable|string|max:255',
            'link' => 'nullable|string|max:500',
            'img_computadora' => 'nullable|image|max:10240',
            'img_movil' => 'nullable|image|max:10240',
            'orden' => 'required|integer|min:0',
            'activo' => 'boolean',
        ]);

        // Si se subió una nueva imagen para computadora
        if ($this->img_computadora) {
            if ($this->slider->img_computadora && ! str_starts_with($this->slider->img_computadora, 'http') && ! str_starts_with($this->slider->img_computadora, 'assets/')) {
                Storage::disk('public')->delete($this->slider->img_computadora);
            }
            $this->slider->img_computadora = $this->img_computadora->store('sliders', 'public');
        }

        // Si se subió una nueva imagen para móvil
        if ($this->img_movil) {
            if ($this->slider->img_movil && ! str_starts_with($this->slider->img_movil, 'http') && ! str_starts_with($this->slider->img_movil, 'assets/')) {
                Storage::disk('public')->delete($this->slider->img_movil);
            }
            $this->slider->img_movil = $this->img_movil->store('sliders', 'public');
        }

        $this->slider->nombre = filled($this->nombre) ? $this->nombre : null;
        $this->slider->link = filled($this->link) ? $this->link : null;
        $this->slider->orden = $this->orden;
        $this->slider->activo = $this->activo;
        $this->slider->save();

        Flux::toast(variant: 'success', text: __('Slider actualizado correctamente.'));

        return redirect()->route('admin.sliders.index');
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Slider') }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos y reemplaza las imágenes si lo requieres.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.sliders.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <!-- Formulario -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <form wire:submit.prevent="guardar" class="space-y-6">
            <!-- Datos Básicos -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Nombre o Título (Opcional)') }}</flux:label>
                    <flux:input wire:model="nombre" placeholder="Ej. Promoción de Temporada Escolar" />
                    <flux:description>{{ __('Identificador para administración y texto alternativo.') }}</flux:description>
                    <flux:error name="nombre" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Enlace de Destino (Opcional)') }}</flux:label>
                    <flux:input wire:model="link" placeholder="Ej. /catalogo o https://..." />
                    <flux:description>{{ __('URL a la que se dirigirá al hacer clic en el banner.') }}</flux:description>
                    <flux:error name="link" />
                </flux:field>
            </div>

            <!-- Imágenes: Desktop y Móvil -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <!-- Imagen Desktop -->
                <div class="space-y-3">
                    <flux:field>
                        <flux:label>{{ __('Imagen Desktop (Computadora)') }}</flux:label>
                        <flux:description>{{ __('Selecciona un archivo solo si deseas reemplazar la imagen actual.') }}</flux:description>
                        <input type="file" wire:model="img_computadora" accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-xs text-zinc-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-900/30 dark:file:text-emerald-400 mt-2" />
                        <flux:error name="img_computadora" />
                    </flux:field>

                    <div wire:loading wire:target="img_computadora" class="text-xs text-emerald-600 font-medium">
                        {{ __('Cargando nueva vista previa desktop...') }}
                    </div>

                    @if($img_computadora)
                        <div class="relative rounded-lg overflow-hidden border border-emerald-500 shadow-sm bg-zinc-100 dark:bg-zinc-800 p-2">
                            <span class="block text-xs font-semibold text-emerald-600 dark:text-emerald-400 mb-1">
                                {{ __('Nueva imagen a reemplazar:') }}
                            </span>
                            <img src="{{ $img_computadora->temporaryUrl() }}" class="w-full h-36 object-cover rounded-md" alt="Preview nuevo desktop">
                            <button type="button" wire:click="$set('img_computadora', null)"
                                class="absolute top-3 right-3 bg-red-600 hover:bg-red-700 text-white rounded-full p-1 shadow">
                                <flux:icon.x-mark class="size-4" />
                            </button>
                        </div>
                    @else
                        <div class="rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 p-2">
                            <span class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                                {{ __('Imagen Desktop actual:') }}
                            </span>
                            <img src="{{ $slider->img_computadora_url }}" class="w-full h-36 object-cover rounded-md" alt="Desktop actual">
                        </div>
                    @endif
                </div>

                <!-- Imagen Móvil -->
                <div class="space-y-3">
                    <flux:field>
                        <flux:label>{{ __('Imagen Móvil (Smartphone)') }}</flux:label>
                        <flux:description>{{ __('Selecciona un archivo solo si deseas reemplazar la imagen actual.') }}</flux:description>
                        <input type="file" wire:model="img_movil" accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-xs text-zinc-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-900/30 dark:file:text-emerald-400 mt-2" />
                        <flux:error name="img_movil" />
                    </flux:field>

                    <div wire:loading wire:target="img_movil" class="text-xs text-emerald-600 font-medium">
                        {{ __('Cargando nueva vista previa móvil...') }}
                    </div>

                    @if($img_movil)
                        <div class="relative rounded-lg overflow-hidden border border-emerald-500 shadow-sm bg-zinc-100 dark:bg-zinc-800 p-2">
                            <span class="block text-xs font-semibold text-emerald-600 dark:text-emerald-400 mb-1">
                                {{ __('Nueva imagen a reemplazar:') }}
                            </span>
                            <div class="flex justify-center">
                                <img src="{{ $img_movil->temporaryUrl() }}" class="h-36 w-28 object-cover rounded-md" alt="Preview nuevo movil">
                            </div>
                            <button type="button" wire:click="$set('img_movil', null)"
                                class="absolute top-3 right-3 bg-red-600 hover:bg-red-700 text-white rounded-full p-1 shadow">
                                <flux:icon.x-mark class="size-4" />
                            </button>
                        </div>
                    @else
                        <div class="rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/40 p-2">
                            <span class="block text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                                {{ __('Imagen Móvil actual:') }}
                            </span>
                            <div class="flex justify-center">
                                <img src="{{ $slider->img_movil_url }}" class="h-36 w-28 object-cover rounded-md" alt="Movil actual">
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Configuración: Orden y Estado -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <flux:field>
                    <flux:label>{{ __('Prioridad de Orden') }}</flux:label>
                    <flux:input type="number" wire:model="orden" min="0" />
                    <flux:description>{{ __('Posición numérica (1, 2, 3...).') }}</flux:description>
                    <flux:error name="orden" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Estado de Publicación') }}</flux:label>
                    <div class="flex items-center gap-3 h-10 mt-1">
                        <flux:switch wire:model="activo" />
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $activo ? __('Visible en la portada (Activo)') : __('Oculto (Inactivo)') }}
                        </span>
                    </div>
                    <flux:error name="activo" />
                </flux:field>
            </div>

            <!-- Botones de Acción -->
            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" href="{{ route('admin.sliders.index') }}" wire:navigate>
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button variant="primary" type="submit" icon="check" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="guardar">{{ __('Guardar Cambios') }}</span>
                    <span wire:loading wire:target="guardar">{{ __('Guardando...') }}</span>
                </flux:button>
            </div>
        </form>
    </div>
</div>
