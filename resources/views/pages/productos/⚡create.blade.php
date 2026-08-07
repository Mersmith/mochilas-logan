<?php

use App\Models\Producto;
use App\Models\TipoProducto;
use App\Models\Marca;
use App\Models\Categoria;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

new #[Title('Nuevo Producto')] class extends Component {
    use WithFileUploads;

    public ?int $tipo_producto_id = null;
    public ?int $marca_id = null;
    public ?int $categoria_id = null;
    public string $nombre = '';
    public string $slug = '';
    public string $descripcion = '';
    public bool $activo = true;

    public $imagen_principal;
    public $galeria = [];

    public function mount()
    {
        $this->tipo_producto_id = TipoProducto::where('activo', true)->first()?->id;
        $this->marca_id = Marca::where('activo', true)->first()?->id;
        $this->categoria_id = Categoria::where('activo', true)->whereNull('categoria_padre_id')->first()?->id;
    }

    public function updatedNombre($value)
    {
        $this->slug = Str::slug($value);
    }

    public function removerImagenGaleria($index)
    {
        if (isset($this->galeria[$index])) {
            unset($this->galeria[$index]);
            // Re-index array so it doesn't leave gaps
            $this->galeria = array_values($this->galeria);
        }
    }

    public function guardar()
    {
        if (! auth()->user()->can('productos.crear')) {
            abort(403);
        }

        $this->validate([
            'tipo_producto_id' => 'required|exists:tipo_productos,id',
            'marca_id' => 'required|exists:marcas,id',
            'categoria_id' => 'required|exists:categorias,id',
            'nombre' => 'required|string|max:255|unique:productos,nombre',
            'slug' => 'required|string|max:255|unique:productos,slug',
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
            'imagen_principal' => 'nullable|image|max:10240', // Max 10MB
            'galeria.*' => 'nullable|image|max:10240', // Max 10MB per image
        ]);

        DB::transaction(function () {
            $producto = Producto::create([
                'tipo_producto_id' => $this->tipo_producto_id,
                'marca_id' => $this->marca_id,
                'categoria_id' => $this->categoria_id,
                'nombre' => $this->nombre,
                'slug' => $this->slug,
                'descripcion' => $this->descripcion,
                'activo' => $this->activo,
            ]);

            // Guardar imagen principal
            if ($this->imagen_principal) {
                $producto->addMedia($this->imagen_principal)
                         ->toMediaCollection('imagen_principal');
            }

            // Guardar galería de imágenes
            if (!empty($this->galeria)) {
                foreach ($this->galeria as $imagen) {
                    $producto->addMedia($imagen)
                             ->toMediaCollection('galeria');
                }
            }
        });

        Flux::toast(variant: 'success', text: 'Producto creado correctamente. Puedes gestionar sus variaciones y empaques ahora.');
        
        // Redirigir a edición para continuar configurando variaciones y empaques
        $producto = Producto::where('slug', $this->slug)->first();
        return redirect()->route('admin.productos.edit', $producto->id);
    }
}; ?>

<div class="space-y-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo Producto') }}</flux:heading>
            <flux:subheading>{{ __('Registra un nuevo producto, sube sus imágenes y luego configura sus variaciones.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.productos.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardar" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Columna Izquierda: Información General -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
                <flux:heading size="lg" class="mb-4">{{ __('Información General') }}</flux:heading>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>{{ __('Tipo de Producto') }}</flux:label>
                        <flux:select wire:model="tipo_producto_id" required>
                            <flux:select.option value="" disabled>{{ __('Seleccione un tipo...') }}</flux:select.option>
                            @foreach(\App\Models\TipoProducto::where('activo', true)->orderBy('nombre')->get() as $tipo)
                                <flux:select.option :value="$tipo->id">{{ $tipo->nombre }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="tipo_producto_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Marca') }}</flux:label>
                        <flux:select wire:model="marca_id" required>
                            <flux:select.option value="" disabled>{{ __('Seleccione una marca...') }}</flux:select.option>
                            @foreach(\App\Models\Marca::where('activo', true)->orderBy('nombre')->get() as $marca)
                                <flux:select.option :value="$marca->id">{{ $marca->nombre }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="marca_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Categoría') }}</flux:label>
                        <flux:select wire:model="categoria_id" required>
                            <flux:select.option value="" disabled>{{ __('Seleccione una categoría...') }}</flux:select.option>
                            @foreach(\App\Models\Categoria::where('activo', true)->orderBy('nombre')->get() as $cat)
                                <flux:select.option :value="$cat->id">
                                    {{ $cat->categoriaPadre ? $cat->categoriaPadre->nombre . ' > ' : '' }}{{ $cat->nombre }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="categoria_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Estado') }}</flux:label>
                        <div class="flex items-center gap-3 h-10 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3">
                            <flux:switch wire:model="activo" />
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $activo ? __('Activo (Visible)') : __('Inactivo (Oculto)') }}
                            </span>
                        </div>
                    </flux:field>

                    <div class="md:col-span-2">
                        <flux:field>
                            <flux:label>{{ __('Nombre del Producto') }}</flux:label>
                            <flux:input wire:model.live="nombre" placeholder="Ej. Mochila Logan Classic..." required />
                            <flux:error name="nombre" />
                        </flux:field>
                    </div>

                    <div class="md:col-span-2">
                        <flux:field>
                            <flux:label>{{ __('Slug (URL amigable)') }}</flux:label>
                            <flux:input wire:model="slug" required />
                            <flux:error name="slug" />
                        </flux:field>
                    </div>

                    <div class="md:col-span-2">
                        <flux:field>
                            <flux:label>{{ __('Descripción Detallada') }}</flux:label>
                            <flux:textarea wire:model="descripcion" rows="4" placeholder="Describe las características principales del producto..." />
                            <flux:error name="descripcion" />
                        </flux:field>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Imágenes -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
                <flux:heading size="lg" class="mb-4">{{ __('Imágenes') }}</flux:heading>
                
                <div class="space-y-6">
                    <!-- Imagen Principal -->
                    <flux:field>
                        <flux:label>{{ __('Imagen Principal (Portada)') }}</flux:label>
                        
                        <div class="mt-2 flex justify-center rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 px-6 py-8">
                            <div class="text-center">
                                @if ($imagen_principal)
                                    <img src="{{ $imagen_principal->temporaryUrl() }}" class="mx-auto h-32 w-32 object-cover rounded-md mb-4 shadow-sm">
                                    <button type="button" wire:click="$set('imagen_principal', null)" class="text-xs text-red-500 hover:text-red-700">Quitar imagen</button>
                                @else
                                    <flux:icon.photo class="mx-auto size-12 text-zinc-300 dark:text-zinc-600" />
                                    <div class="mt-4 flex justify-center text-sm/6 text-zinc-600 dark:text-zinc-400">
                                        <label for="file-upload-principal" class="relative cursor-pointer rounded-md bg-white dark:bg-zinc-900 font-semibold text-emerald-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-emerald-600 focus-within:ring-offset-2 hover:text-emerald-500">
                                            <span>Sube un archivo</span>
                                            <input id="file-upload-principal" wire:model="imagen_principal" type="file" class="sr-only" accept="image/jpeg,image/png,image/webp">
                                        </label>
                                    </div>
                                    <p class="text-xs/5 text-zinc-500">PNG, JPG, WEBP hasta 2MB</p>
                                @endif
                            </div>
                        </div>
                        <flux:error name="imagen_principal" />
                    </flux:field>

                    <hr class="border-zinc-200 dark:border-zinc-700">

                    <!-- Galería -->
                    <flux:field>
                        <flux:label>{{ __('Galería de Imágenes (Múltiples)') }}</flux:label>
                        
                        <div class="mt-2">
                            <label for="file-upload-galeria" class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 px-6 py-4 text-sm font-medium text-emerald-600 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <flux:icon.plus class="size-5" />
                                <span>Añadir imágenes a la galería</span>
                                <input id="file-upload-galeria" wire:model="galeria" type="file" multiple class="sr-only" accept="image/jpeg,image/png,image/webp">
                            </label>
                        </div>
                        
                        @if (count($galeria) > 0)
                            <div class="mt-4 grid grid-cols-3 gap-2">
                                @foreach($galeria as $index => $imagen)
                                    <div class="relative group">
                                        <img src="{{ $imagen->temporaryUrl() }}" class="h-16 w-full object-cover rounded-md border border-zinc-200 dark:border-zinc-700 shadow-sm">
                                        <button type="button" wire:click="removerImagenGaleria({{ $index }})" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <flux:icon.x-mark class="size-3" />
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        
                        <flux:error name="galeria.*" />
                    </flux:field>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm flex justify-end gap-3">
                <flux:button variant="ghost" href="{{ route('admin.productos.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    {{ __('Guardar y Continuar') }}
                </flux:button>
            </div>
        </div>
    </form>
</div>
