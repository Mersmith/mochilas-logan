<?php

use App\Models\SliderPrincipal;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Nuevo Slider')] class extends Component {
    use WithFileUploads;

    public string $nombre = '';
    public string $link = '';
    public $img_computadora = null;
    public $img_movil = null;
    public int $orden = 1;
    public bool $activo = true;

    public function mount(): void
    {
        $this->orden = (SliderPrincipal::max('orden') ?? 0) + 1;
    }

    public function guardar()
    {
        if (! auth()->user()->can('sliders.crear')) {
            abort(403, 'No tienes permiso para crear sliders.');
        }

        $this->validate([
            'nombre' => 'nullable|string|max:255',
            'link' => 'nullable|string|max:500',
            'img_computadora' => 'required|image|max:10240',
            'img_movil' => 'required|image|max:10240',
            'orden' => 'required|integer|min:0',
            'activo' => 'boolean',
        ], [
            'img_computadora.required' => 'La imagen para computadora (desktop) es obligatoria.',
            'img_movil.required' => 'La imagen para móvil (smartphone) es obligatoria.',
        ]);

        $pathDesktop = $this->img_computadora->store('sliders', 'public');
        $pathMovil = $this->img_movil->store('sliders', 'public');

        SliderPrincipal::create([
            'nombre' => filled($this->nombre) ? $this->nombre : null,
            'link' => filled($this->link) ? $this->link : null,
            'img_computadora' => $pathDesktop,
            'img_movil' => $pathMovil,
            'orden' => $this->orden,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: __('Slider creado correctamente.'));

        return redirect()->route('admin.sliders.index');
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo Slider Principal') }}</flux:heading>
            <flux:subheading>{{ __('Sube los banners para computadoras y móviles y define su enlace de destino.') }}</flux:subheading>
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

            <!-- Subida de Imágenes: Desktop y Móvil -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <!-- Imagen Desktop -->
                <div class="space-y-3">
                    <flux:field>
                        <flux:label>{{ __('Imagen Desktop (Computadora)') }} <span class="text-red-500">*</span></flux:label>
                        <flux:description>{{ __('Recomendado: 1920x600 o proporción panorámica (JPG, PNG, WebP).') }}</flux:description>
                        <input type="file" wire:model="img_computadora" accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-xs text-zinc-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-900/30 dark:file:text-emerald-400 mt-2" />
                        <flux:error name="img_computadora" />
                    </flux:field>

                    <div wire:loading wire:target="img_computadora" class="text-xs text-emerald-600 font-medium">
                        {{ __('Cargando vista previa desktop...') }}
                    </div>

                    @if($img_computadora)
                        <div class="relative rounded-lg overflow-hidden border border-emerald-500 shadow-sm bg-zinc-100 dark:bg-zinc-800 p-2">
                            <span class="block text-xs font-semibold text-emerald-600 dark:text-emerald-400 mb-1">
                                {{ __('Vista previa Computadora:') }}
                            </span>
                            <img src="{{ $img_computadora->temporaryUrl() }}" class="w-full h-36 object-cover rounded-md" alt="Preview desktop">
                            <button type="button" wire:click="$set('img_computadora', null)"
                                class="absolute top-3 right-3 bg-red-600 hover:bg-red-700 text-white rounded-full p-1 shadow">
                                <flux:icon.x-mark class="size-4" />
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Imagen Móvil -->
                <div class="space-y-3">
                    <flux:field>
                        <flux:label>{{ __('Imagen Móvil (Smartphone)') }} <span class="text-red-500">*</span></flux:label>
                        <flux:description>{{ __('Recomendado: 800x800 o vertical 9:16 (JPG, PNG, WebP).') }}</flux:description>
                        <input type="file" wire:model="img_movil" accept="image/jpeg,image/png,image/webp"
                            class="block w-full text-xs text-zinc-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-900/30 dark:file:text-emerald-400 mt-2" />
                        <flux:error name="img_movil" />
                    </flux:field>

                    <div wire:loading wire:target="img_movil" class="text-xs text-emerald-600 font-medium">
                        {{ __('Cargando vista previa móvil...') }}
                    </div>

                    @if($img_movil)
                        <div class="relative rounded-lg overflow-hidden border border-emerald-500 shadow-sm bg-zinc-100 dark:bg-zinc-800 p-2">
                            <span class="block text-xs font-semibold text-emerald-600 dark:text-emerald-400 mb-1">
                                {{ __('Vista previa Móvil:') }}
                            </span>
                            <div class="flex justify-center">
                                <img src="{{ $img_movil->temporaryUrl() }}" class="h-36 w-28 object-cover rounded-md" alt="Preview movil">
                            </div>
                            <button type="button" wire:click="$set('img_movil', null)"
                                class="absolute top-3 right-3 bg-red-600 hover:bg-red-700 text-white rounded-full p-1 shadow">
                                <flux:icon.x-mark class="size-4" />
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Configuración: Orden y Estado -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <flux:field>
                    <flux:label>{{ __('Prioridad de Orden') }}</flux:label>
                    <flux:input type="number" wire:model="orden" min="0" />
                    <flux:description>{{ __('Menor número aparece primero en el carrusel (1, 2, 3...).') }}</flux:description>
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
                    <span wire:loading.remove wire:target="guardar">{{ __('Crear Slider') }}</span>
                    <span wire:loading wire:target="guardar">{{ __('Guardando...') }}</span>
                </flux:button>
            </div>
        </form>
    </div>
</div>
