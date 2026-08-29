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

new #[Title('Nuevo Producto')] class extends Component {
    public ?int $tipo_producto_id = null;
    public ?int $marca_id = null;
    public ?int $categoria_id = null;
    public string $nombre = '';
    public string $slug = '';
    public string $descripcion = '';
    public bool $activo = true;
    public array $especificaciones = [['clave' => '', 'valor' => '']];
    public string $politica_garantia = '';

    public function mount(): void
    {
        $this->tipo_producto_id = TipoProducto::where('activo', true)->first()?->id;
        $this->marca_id = Marca::where('activo', true)->first()?->id;
        $this->categoria_id = Categoria::where('activo', true)->whereNull('categoria_padre_id')->first()?->id;
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
            'especificaciones' => 'array',
            'politica_garantia' => 'nullable|string',
        ]);

        $producto = Producto::create([
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

        Flux::toast(variant: 'success', text: __('Producto creado. Ahora puedes subir imágenes, generar variaciones y definir empaques.'));

        return redirect()->route('admin.productos.edit', $producto->id);
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

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo Producto') }}</flux:heading>
            <flux:subheading>{{ __('Registra los datos básicos del producto para continuar con sus imágenes, variaciones y empaques.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.productos.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <form wire:submit.prevent="guardar" class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                <hr class="border-zinc-200 dark:border-zinc-700 my-2" />
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

            <div class="md:col-span-2 flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" href="{{ route('admin.productos.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    {{ __('Crear Producto y Continuar') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
