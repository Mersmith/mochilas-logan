<?php

use App\Models\Producto;
use App\Models\TipoProducto;
use App\Models\Marca;
use App\Models\Categoria;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Nuevo Producto')] class extends Component {
    public string $nombre = '';
    public ?int $tipo_producto_id = null;
    public ?int $marca_id = null;
    public ?int $categoria_id = null;
    public string $descripcion = '';
    public bool $activo = true;

    /**
     * Save the product and redirect to management panel.
     */
    public function guardar(): void
    {
        $this->validate([
            'nombre' => 'required|string|unique:productos,nombre|max:255',
            'tipo_producto_id' => 'required|integer|exists:tipo_productos,id',
            'marca_id' => 'required|integer|exists:marcas,id',
            'categoria_id' => 'required|integer|exists:categorias,id',
            'descripcion' => 'nullable|string',
            'activo' => 'required|boolean',
        ]);

        $producto = Producto::create([
            'nombre' => $this->nombre,
            'slug' => Str::slug($this->nombre),
            'tipo_producto_id' => $this->tipo_producto_id,
            'marca_id' => $this->marca_id,
            'categoria_id' => $this->categoria_id,
            'descripcion' => $this->descripcion,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: __('Producto creado. Defina ahora sus variaciones y empaques.'));
        $this->redirect(route('productos.manage', $producto), navigate: true);
    }

    /**
     * Get types of products.
     */
    #[Computed]
    public function tipos()
    {
        return TipoProducto::where('activo', true)->get();
    }

    /**
     * Get brands.
     */
    #[Computed]
    public function marcas()
    {
        return Marca::where('activo', true)->get();
    }

    /**
     * Get categories.
     */
    #[Computed]
    public function categorias()
    {
        return Categoria::where('activo', true)->get();
    }
}; ?>

<div class="space-y-6 max-w-2xl mx-auto">
    <div>
        <flux:heading size="xl">{{ __('Registrar Nuevo Producto') }}</flux:heading>
        <flux:subheading>{{ __('Crea la ficha base del producto. Luego definirás sus tallas, colores y precios.') }}</flux:subheading>
    </div>

    <form wire:submit.prevent="guardar" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm">
        <!-- Nombre -->
        <flux:input wire:model="nombre" :label="__('Nombre del Producto')" placeholder="Ej. Mochila Logan Classic" required autofocus />

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Tipo de Producto -->
            <flux:select wire:model="tipo_producto_id" :label="__('Línea/Tipo')" placeholder="Seleccionar...">
                @foreach($this->tipos as $tipo)
                    <flux:select.option value="{{ $tipo->id }}">{{ $tipo->nombre }}</flux:select.option>
                @endforeach
            </flux:select>

            <!-- Marca -->
            <flux:select wire:model="marca_id" :label="__('Marca')" placeholder="Seleccionar...">
                @foreach($this->marcas as $m)
                    <flux:select.option value="{{ $m->id }}">{{ $m->nombre }}</flux:select.option>
                @endforeach
            </flux:select>

            <!-- Categoría -->
            <flux:select wire:model="categoria_id" :label="__('Categoría')" placeholder="Seleccionar...">
                @foreach($this->categorias as $cat)
                    <flux:select.option value="{{ $cat->id }}">{{ $cat->nombre }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <!-- Descripción -->
        <flux:textarea wire:model="descripcion" :label="__('Descripción')" placeholder="Detalles o especificaciones del producto..." rows="4" />

        <!-- Activo -->
        <div class="flex items-center gap-3">
            <flux:checkbox wire:model="activo" :label="__('Producto activo y visible en el catálogo')" />
        </div>

        <!-- Botones -->
        <div class="flex items-center justify-end gap-4 border-t border-zinc-200 dark:border-zinc-700 pt-4">
            <flux:button variant="ghost" :href="route('productos.index')" wire:navigate>
                {{ __('Cancelar') }}
            </flux:button>
            <flux:button variant="primary" type="submit">
                {{ __('Guardar y Continuar') }}
            </flux:button>
        </div>
    </form>
</div>
