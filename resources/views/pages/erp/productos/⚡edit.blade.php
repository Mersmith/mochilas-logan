<?php

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\TipoProducto;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Producto')] class extends Component {
    public Producto $producto;
    public string $activeTab = 'general';

    // General Properties
    public ?int $tipo_producto_id = null;
    public ?int $marca_id = null;
    public ?int $categoria_id = null;
    public string $nombre = '';
    public string $slug = '';
    public string $descripcion = '';
    public bool $activo = true;
    public array $especificaciones = [['clave' => '', 'valor' => '']];
    public string $politica_garantia = '';

    public function mount(Producto $producto): void
    {
        $this->producto = $producto;

        // Cargar datos generales
        $this->tipo_producto_id = $producto->tipo_producto_id;
        $this->marca_id = $producto->marca_id;
        $this->categoria_id = $producto->categoria_id;
        $this->nombre = $producto->nombre;
        $this->slug = $producto->slug;
        $this->descripcion = $producto->descripcion ?? '';
        $this->activo = $producto->activo;

        $specs = $producto->especificaciones ?? [];
        $this->especificaciones = [];
        foreach ($specs as $key => $val) {
            $this->especificaciones[] = ['clave' => $key, 'valor' => $val];
        }
        if (empty($this->especificaciones)) {
            $this->especificaciones[] = ['clave' => '', 'valor' => ''];
        }
        $this->politica_garantia = $producto->politica_garantia ?? '';
    }

    public function updatedNombre(string $value): void
    {
        $this->slug = Str::slug($value);
    }

    public function agregarEspecificacion(): void
    {
        $this->especificaciones[] = ['clave' => '', 'valor' => ''];
    }

    public function removerEspecificacion(int $index): void
    {
        unset($this->especificaciones[$index]);
        $this->especificaciones = array_values($this->especificaciones);
    }

    public function guardarGeneral(): void
    {
        if (! auth()->user()->can('productos.editar')) {
            abort(403);
        }

        $this->validate([
            'tipo_producto_id' => 'required|exists:tipo_productos,id',
            'marca_id' => 'required|exists:marcas,id',
            'categoria_id' => 'required|exists:categorias,id',
            'nombre' => 'required|string|max:255|unique:productos,nombre,' . $this->producto->id,
            'slug' => 'required|string|max:255|unique:productos,slug,' . $this->producto->id,
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
            'especificaciones' => 'array',
            'politica_garantia' => 'nullable|string',
        ]);

        $this->producto->update([
            'tipo_producto_id' => $this->tipo_producto_id,
            'marca_id' => $this->marca_id,
            'categoria_id' => $this->categoria_id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'activo' => $this->activo,
            'especificaciones' => collect($this->especificaciones)->filter(fn($e) => $e['clave'] !== '')->pluck('valor', 'clave')->toArray(),
            'politica_garantia' => $this->politica_garantia,
        ]);

        Flux::toast(variant: 'success', text: __('Información general actualizada.'));
    }

    #[Computed]
    public function tiposProducto()
    {
        return TipoProducto::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function marcas()
    {
        return Marca::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function categorias()
    {
        return Categoria::where('activo', true)->orderBy('nombre')->get();
    }
}; ?>

<div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Editar Producto:') }} {{ $producto->nombre }}</flux:heading>
            <flux:subheading>{{ __('Gestión completa del producto, imágenes, variaciones y empaques.') }}</flux:subheading>
        </div>

        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.productos.index') }}" wire:navigate>
            {{ __('Volver al catálogo') }}
        </flux:button>
    </div>

    <!-- Pestañas (Tabs) -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-700 overflow-x-auto no-scrollbar">
        <button wire:click.prevent="$set('activeTab', 'general')" class="px-6 py-3 font-semibold text-sm border-b-2 whitespace-nowrap transition-colors {{ $activeTab === 'general' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-500 dark:text-emerald-500' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            {{ __('Información General') }}
        </button>
        <button wire:click.prevent="$set('activeTab', 'imagenes')" class="px-6 py-3 font-semibold text-sm border-b-2 whitespace-nowrap transition-colors {{ $activeTab === 'imagenes' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-500 dark:text-emerald-500' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            {{ __('Imágenes') }}
        </button>
        <button wire:click.prevent="$set('activeTab', 'variaciones')" class="px-6 py-3 font-semibold text-sm border-b-2 whitespace-nowrap transition-colors {{ $activeTab === 'variaciones' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-500 dark:text-emerald-500' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            {{ __('Variaciones y Precios') }}
        </button>
        <button wire:click.prevent="$set('activeTab', 'empaques')" class="px-6 py-3 font-semibold text-sm border-b-2 whitespace-nowrap transition-colors {{ $activeTab === 'empaques' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-500 dark:text-emerald-500' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">
            {{ __('Presentaciones') }}
        </button>
    </div>

    <!-- Pestaña 1: Información General -->
    @if($activeTab === 'general')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
            <form wire:submit.prevent="guardarGeneral" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Tipo de Producto') }}</flux:label>
                    <flux:select wire:model="tipo_producto_id" required>
                        <flux:select.option value="" disabled>{{ __('Seleccione un tipo...') }}</flux:select.option>
                        @foreach($this->tiposProducto as $tipo)
                            <flux:select.option :value="$tipo->id">{{ $tipo->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="tipo_producto_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Marca') }}</flux:label>
                    <flux:select wire:model="marca_id" required>
                        <flux:select.option value="" disabled>{{ __('Seleccione una marca...') }}</flux:select.option>
                        @foreach($this->marcas as $marca)
                            <flux:select.option :value="$marca->id">{{ $marca->nombre }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="marca_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Categoría') }}</flux:label>
                    <flux:select wire:model="categoria_id" required>
                        <flux:select.option value="" disabled>{{ __('Seleccione una categoría...') }}</flux:select.option>
                        @foreach($this->categorias as $cat)
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

                <div class="md:col-span-2">
                    <hr class="border-zinc-200 dark:border-zinc-700 my-4" />
                    <div class="flex items-center justify-between mb-2">
                        <flux:label class="text-lg">{{ __('Especificaciones Adicionales') }}</flux:label>
                        <flux:button size="sm" wire:click.prevent="agregarEspecificacion" icon="plus">{{ __('Añadir') }}</flux:button>
                    </div>
                    <div class="space-y-3">
                        @foreach($especificaciones as $index => $especificacion)
                            <div class="flex items-center gap-2">
                                <div class="flex-1">
                                    <flux:input wire:model="especificaciones.{{ $index }}.clave" placeholder="Ej. Material" />
                                </div>
                                <div class="flex-1">
                                    <flux:input wire:model="especificaciones.{{ $index }}.valor" placeholder="Ej. Poliéster" />
                                </div>
                                <flux:button variant="danger" size="sm" wire:click.prevent="removerEspecificacion({{ $index }})" icon="trash" />
                            </div>
                        @endforeach
                        <flux:error name="especificaciones" />
                    </div>
                </div>

                <div class="md:col-span-2">
                    <flux:field>
                        <flux:label>{{ __('Política de Garantía (Opcional)') }}</flux:label>
                        <flux:textarea wire:model="politica_garantia" rows="3" placeholder="Si se deja vacío, se mostrará la política estándar de 30 días." />
                        <flux:error name="politica_garantia" />
                    </flux:field>
                </div>

                <div class="md:col-span-2 flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button variant="primary" type="submit" icon="check">
                        {{ __('Actualizar Información') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    <!-- Pestaña 2: Imágenes (Componente) -->
    @if($activeTab === 'imagenes')
        <livewire:pages::erp.productos.imagenes :producto="$producto" :key="'imagenes-'.$producto->id" />
    @endif

    <!-- Pestaña 3: Variaciones y Precios (Componente) -->
    @if($activeTab === 'variaciones')
        <livewire:pages::erp.productos.variaciones :producto="$producto" :key="'variaciones-'.$producto->id" />
    @endif

    <!-- Pestaña 4: Presentaciones / Empaques (Componente) -->
    @if($activeTab === 'empaques')
        <livewire:pages::erp.productos.presentaciones :producto="$producto" :key="'presentaciones-'.$producto->id" />
    @endif
</div>
