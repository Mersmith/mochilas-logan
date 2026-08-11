<?php

use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\ProductoEmpaque;
use App\Models\Variacion;
use App\Models\VariacionPrecio;
use App\Models\ListaPrecio;
use App\Models\Atributo;
use App\Models\AtributoValor;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Illuminate\Support\Str;
use Flux\Flux;

new #[Title('Editar Producto')] class extends Component {
    use WithFileUploads;

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

    // Image Properties
    public $nueva_imagen_principal;
    public $nuevas_imagenes_galeria = [];

    // Empaques Properties
    public ?int $unidad_medida_id = null;
    public int $factor_conversion = 1;

    // Generador Properties
    public array $atributosSeleccionados = [];
    public array $valoresSeleccionados = [];
    
    // Variaciones Guardadas Edit Properties
    public array $variacionesData = [];

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
        foreach($specs as $key => $val) {
            $this->especificaciones[] = ['clave' => $key, 'valor' => $val];
        }
        if (empty($this->especificaciones)) {
            $this->especificaciones[] = ['clave' => '', 'valor' => ''];
        }
        $this->politica_garantia = $producto->politica_garantia ?? '';

        // Cargar variaciones
        $this->cargarVariacionesExistentes();
    }

    public function updatedNombre($value)
    {
        $this->slug = Str::slug($value);
    }

    // ==========================================
    // LÓGICA DE INFORMACIÓN GENERAL E IMÁGENES
    // ==========================================

    public function agregarEspecificacion()
    {
        $this->especificaciones[] = ['clave' => '', 'valor' => ''];
    }

    public function removerEspecificacion($index)
    {
        unset($this->especificaciones[$index]);
        $this->especificaciones = array_values($this->especificaciones);
    }

    public function guardarGeneral()
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

        Flux::toast(variant: 'success', text: 'Información general actualizada.');
    }

    public function guardarImagenPrincipal()
    {
        $this->validate([
            'nueva_imagen_principal' => 'required|image|max:10240',
        ]);

        // Eliminar la anterior si existe
        $this->producto->clearMediaCollection('imagen_principal');
        
        // Agregar nueva
        $this->producto->addMedia($this->nueva_imagen_principal)->toMediaCollection('imagen_principal');
        
        $this->nueva_imagen_principal = null;
        Flux::toast(variant: 'success', text: 'Imagen principal actualizada.');
    }

    public function subirImagenesGaleria()
    {
        $this->validate([
            'nuevas_imagenes_galeria.*' => 'required|image|max:10240',
        ]);

        foreach ($this->nuevas_imagenes_galeria as $imagen) {
            $this->producto->addMedia($imagen)->toMediaCollection('galeria');
        }

        $this->nuevas_imagenes_galeria = [];
        Flux::toast(variant: 'success', text: 'Imágenes agregadas a la galería.');
    }

    public function removerNuevaImagenGaleria($index)
    {
        if (isset($this->nuevas_imagenes_galeria[$index])) {
            unset($this->nuevas_imagenes_galeria[$index]);
            $this->nuevas_imagenes_galeria = array_values($this->nuevas_imagenes_galeria);
        }
    }

    public function eliminarMedia(int $mediaId)
    {
        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::findOrFail($mediaId);
        $media->delete();
        Flux::toast(variant: 'success', text: 'Imagen eliminada.');
    }

    // ==========================================
    // LÓGICA DE VARIACIONES
    // ==========================================

    public function cargarVariacionesExistentes(): void
    {
        $this->variacionesData = [];
        
        $variaciones = Variacion::with(['valores.atributo', 'precios'])
            ->where('producto_id', $this->producto->id)
            ->get();
            
        foreach ($variaciones as $var) {
            $precios = [];
            foreach (ListaPrecio::all() as $lp) {
                $varPrecio = $var->precios->firstWhere('lista_precio_id', $lp->id);
                $precios[$lp->id] = $varPrecio ? $varPrecio->precio : 0.00;
            }
            
            $desc = $var->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
            
            $this->variacionesData[] = [
                'id' => $var->id,
                'descripcion' => $desc ?: 'Sin Atributos',
                'sku' => $var->sku,
                'codigo_barras' => $var->codigo_barras,
                'activo' => $var->activo,
                'precios' => $precios,
            ];
        }
    }

    public function generarVariaciones(): void
    {
        if (empty($this->valoresSeleccionados)) {
            Flux::toast(variant: 'danger', text: __('Seleccione al menos un valor de atributo.'));
            return;
        }

        // Agrupar los valores seleccionados por su atributo
        $valoresPorAtributo = [];
        foreach ($this->valoresSeleccionados as $valorId => $seleccionado) {
            if ($seleccionado) {
                $valor = AtributoValor::find($valorId);
                if ($valor) {
                    $valoresPorAtributo[$valor->atributo_id][] = $valorId;
                }
            }
        }

        if (empty($valoresPorAtributo)) {
            Flux::toast(variant: 'danger', text: __('Seleccione al menos un valor de atributo.'));
            return;
        }

        // Obtener producto base
        $prod = $this->producto;

        DB::transaction(function () use ($valoresPorAtributo, $prod) {
            // Algoritmo para obtener el producto cartesiano de los grupos
            $combinaciones = [[]];
            foreach ($valoresPorAtributo as $grupoValores) {
                $temp = [];
                foreach ($combinaciones as $comb) {
                    foreach ($grupoValores as $valId) {
                        $temp[] = array_merge($comb, [$valId]);
                    }
                }
                $combinaciones = $temp;
            }

            // Crear las variaciones a partir de las combinaciones
            foreach ($combinaciones as $index => $comb) {
                // Verificar si ya existe una combinación igual para este producto
                $existeCombinacion = false;
                
                $variacionesExistentes = Variacion::with('valores')
                    ->where('producto_id', $prod->id)
                    ->get();
                    
                foreach ($variacionesExistentes as $vEx) {
                    $exIds = $vEx->valores->pluck('id')->toArray();
                    sort($exIds);
                    sort($comb);
                    if ($exIds === $comb) {
                        $existeCombinacion = true;
                        break;
                    }
                }

                if ($existeCombinacion) {
                    continue; // Saltar si ya existe
                }

                // Crear la variación
                $sku = strtoupper(Str::slug($prod->nombre) . '-' . Str::random(5));
                $var = Variacion::create([
                    'producto_id' => $prod->id,
                    'sku' => $sku,
                    'codigo_barras' => null,
                    'activo' => true,
                ]);

                // Asociar atributos
                $var->valores()->attach($comb);

                // Crear precios iniciales en 0.00
                foreach (ListaPrecio::all() as $lp) {
                    VariacionPrecio::create([
                        'variacion_id' => $var->id,
                        'lista_precio_id' => $lp->id,
                        'precio' => 0.00,
                        'simbolo' => 'S/',
                    ]);
                }
            }
        });

        $this->valoresSeleccionados = [];
        $this->cargarVariacionesExistentes();
        Flux::toast(variant: 'success', text: __('Combinaciones generadas correctamente.'));
    }

    public function guardarVariaciones(): void
    {
        DB::transaction(function () {
            foreach ($this->variacionesData as $data) {
                $var = Variacion::findOrFail($data['id']);
                $var->update([
                    'sku' => $data['sku'],
                    'codigo_barras' => $data['codigo_barras'] ?: null,
                    'activo' => $data['activo'],
                ]);

                foreach ($data['precios'] as $lpId => $precio) {
                    VariacionPrecio::updateOrCreate(
                        ['variacion_id' => $var->id, 'lista_precio_id' => $lpId],
                        ['precio' => $precio ?: 0.00]
                    );
                }
            }
        });

        Flux::toast(variant: 'success', text: __('Variaciones y precios actualizados correctamente.'));
        $this->cargarVariacionesExistentes();
    }

    public function eliminarVariacion(int $id): void
    {
        $var = Variacion::findOrFail($id);
        $var->delete();
        
        Flux::toast(variant: 'success', text: __('Variación eliminada.'));
        $this->cargarVariacionesExistentes();
    }

    // ==========================================
    // LÓGICA DE EMPAQUES
    // ==========================================

    public function agregarEmpaque(): void
    {
        $this->validate([
            'unidad_medida_id' => 'required|integer|exists:unidades_medida,id',
            'factor_conversion' => 'required|integer|min:1',
        ]);

        // Verificar duplicados
        $existe = ProductoEmpaque::where('producto_id', $this->producto->id)
            ->where('unidad_medida_id', $this->unidad_medida_id)
            ->exists();

        if ($existe) {
            Flux::toast(variant: 'danger', text: __('Esta presentación de empaque ya está configurada.'));
            return;
        }

        ProductoEmpaque::create([
            'producto_id' => $this->producto->id,
            'unidad_medida_id' => $this->unidad_medida_id,
            'factor_conversion' => $this->factor_conversion,
        ]);

        $this->unidad_medida_id = null;
        $this->factor_conversion = 1;

        Flux::toast(variant: 'success', text: __('Empaque agregado.'));
        $this->producto->load('empaques.unidadMedida');
    }

    public function eliminarEmpaque(int $id): void
    {
        $empaque = ProductoEmpaque::findOrFail($id);
        $empaque->delete();
        
        Flux::toast(variant: 'success', text: __('Empaque eliminado.'));
        $this->producto->load('empaques.unidadMedida');
    }

    // ==========================================
    // PROPIEDADES COMPUTADAS
    // ==========================================

    #[Computed]
    public function listaPrecios()
    {
        return ListaPrecio::all();
    }

    #[Computed]
    public function unidadesMedida()
    {
        return UnidadMedida::all();
    }

    #[Computed]
    public function atributos()
    {
        return Atributo::with('valores')->get();
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

    <!-- Pestaña 2: Imágenes -->
    @if($activeTab === 'imagenes')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Imagen Principal -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
                <flux:heading size="lg" class="mb-4">{{ __('Imagen Principal (Portada)') }}</flux:heading>
                
                <div class="mb-6">
                    @if($producto->getFirstMediaUrl('imagen_principal'))
                        <div class="relative group inline-block">
                            <img src="{{ $producto->getFirstMediaUrl('imagen_principal', 'card') }}" class="h-48 w-48 object-cover rounded-lg border border-zinc-200 shadow-sm">
                            <button wire:click="eliminarMedia({{ $producto->getFirstMedia('imagen_principal')->id }})" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition-opacity" title="Eliminar imagen">
                                <flux:icon.trash class="size-4" />
                            </button>
                        </div>
                    @else
                        <div class="h-48 w-48 bg-zinc-100 dark:bg-zinc-800 rounded-lg flex items-center justify-center border border-zinc-200 dark:border-zinc-700">
                            <span class="text-zinc-400">{{ __('Sin portada') }}</span>
                        </div>
                    @endif
                </div>

                <form wire:submit.prevent="guardarImagenPrincipal" class="space-y-4 border-t border-zinc-200 pt-4">
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
                                <button wire:click="eliminarMedia({{ $media->id }})" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity" title="Eliminar imagen">
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

                <form wire:submit.prevent="subirImagenesGaleria" class="space-y-4 border-t border-zinc-200 pt-4">
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
    @endif

    <!-- Contenido Pestaña 3: Variaciones -->
    @if($activeTab === 'variaciones')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Columna Izquierda: Generador de Variaciones -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <div>
                    <flux:heading size="lg">{{ __('Generador de Combinaciones') }}</flux:heading>
                    <flux:subheading>{{ __('Crea automáticamente los SKUs combinando colores, tallas y atributos.') }}</flux:subheading>
                </div>

                <div class="space-y-4">
                    @foreach($this->atributos as $attr)
                        <div class="space-y-2 border-b border-zinc-100 dark:border-zinc-800 pb-3 last:border-b-0">
                            <span class="text-xs font-semibold uppercase text-zinc-500">{{ $attr->nombre }}</span>
                            <div class="grid grid-cols-2 gap-2 mt-1">
                                @foreach($attr->valores as $val)
                                    <div class="flex items-center gap-2">
                                        <flux:checkbox wire:model="valoresSeleccionados.{{ $val->id }}" :label="$val->valor" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <flux:button variant="primary" class="w-full" icon="sparkles" wire:click.prevent="generarVariaciones">
                    {{ __('Generar Variaciones') }}
                </flux:button>
            </div>

            <!-- Columna Derecha: Configuración de SKUs y Precios -->
            <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm">
                <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3">
                    <div>
                        <flux:heading size="lg">{{ __('Variaciones y Precios Activos') }}</flux:heading>
                        <flux:subheading>{{ __('Edita los SKUs, códigos de barra y los precios para cada lista.') }}</flux:subheading>
                    </div>
                </div>

                @if(empty($variacionesData))
                    <div class="text-center py-12 text-zinc-500">
                        {{ __('No hay variaciones generadas. Utiliza el generador de la izquierda.') }}
                    </div>
                @else
                    <form wire:submit.prevent="guardarVariaciones" class="space-y-6">
                        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach($variacionesData as $index => $var)
                                <div class="py-4 first:pt-0 last:pb-0 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $var['descripcion'] }}</span>
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarVariacion({{ $var['id'] }})" />
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- SKU -->
                                        <flux:input wire:model="variacionesData.{{ $index }}.sku" :label="__('SKU')" placeholder="Código de inventario..." />
                                        
                                        <!-- Código de Barras -->
                                        <flux:input wire:model="variacionesData.{{ $index }}.codigo_barras" :label="__('Código de Barras (EAN)')" placeholder="Opcional..." />
                                    </div>

                                    <!-- Precios Diferenciados -->
                                    <div class="grid grid-cols-2 gap-4 bg-zinc-50 dark:bg-zinc-800/40 p-3 rounded-lg border border-zinc-100 dark:border-zinc-800/80">
                                        @foreach($this->listaPrecios as $lp)
                                            <flux:input 
                                                wire:model="variacionesData.{{ $index }}.precios.{{ $lp->id }}" 
                                                type="number" 
                                                step="0.01" 
                                                :label="$lp->nombre" 
                                                placeholder="S/ 0.00" />
                                        @endforeach
                                    </div>

                                    <!-- Activo -->
                                    <div class="flex items-center gap-2">
                                        <flux:checkbox wire:model="variacionesData.{{ $index }}.activo" :label="__('Variación disponible para venta')" />
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex justify-end gap-3 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                            <flux:button variant="primary" type="submit" icon="check">
                                {{ __('Guardar SKUs y Precios') }}
                            </flux:button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <!-- Contenido Pestaña 4: Empaques -->
    @if($activeTab === 'empaques')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Formulario de Asociación de Empaque -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <div>
                    <flux:heading size="lg">{{ __('Asociar Presentación') }}</flux:heading>
                    <flux:subheading>{{ __('Define en qué empaques se puede mover o almacenar el producto.') }}</flux:subheading>
                </div>

                <form wire:submit.prevent="agregarEmpaque" class="space-y-4">
                    <!-- Unidad de Medida -->
                    <flux:select wire:model="unidad_medida_id" :label="__('Empaque / Presentación')" placeholder="Seleccionar empaque...">
                        @foreach($this->unidadesMedida as $um)
                            <flux:select.option value="{{ $um->id }}">{{ $um->nombre }} ({{ $um->abreviacion }})</flux:select.option>
                        @endforeach
                    </flux:select>

                    <!-- Factor de Conversión -->
                    <flux:input wire:model="factor_conversion" type="number" min="1" :label="__('Factor de Conversión')" placeholder="Ej. 1 costal = 50 unidades base" />

                    <flux:button variant="primary" type="submit" class="w-full" icon="plus">
                        {{ __('Asociar Empaque') }}
                    </flux:button>
                </form>
            </div>

            <!-- Tabla de Empaques Asociados -->
            <div class="md:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
                <flux:heading size="lg">{{ __('Presentaciones del Producto') }}</flux:heading>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold">
                                <th class="pb-3">{{ __('Empaque') }}</th>
                                <th class="pb-3 text-center">{{ __('Abreviación') }}</th>
                                <th class="pb-3 text-center">{{ __('Factor de Conversión') }}</th>
                                <th class="pb-3 text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            <!-- Unidad Base Siempre Existe de forma implícita -->
                            <tr>
                                <td class="py-3 font-semibold text-zinc-900 dark:text-white">Unidad (Unidad Base)</td>
                                <td class="py-3 text-center text-zinc-500">UND</td>
                                <td class="py-3 text-center font-bold text-zinc-700 dark:text-zinc-300">1</td>
                                <td class="py-3 text-right text-zinc-400 italic text-xs pr-4">{{ __('Por defecto') }}</td>
                            </tr>
                            
                            @forelse($producto->empaques as $emp)
                                <tr>
                                    <td class="py-3 font-semibold text-zinc-900 dark:text-white">{{ $emp->unidadMedida->nombre }}</td>
                                    <td class="py-3 text-center text-zinc-500">{{ $emp->unidadMedida->abreviacion }}</td>
                                    <td class="py-3 text-center font-semibold text-zinc-700 dark:text-zinc-300">
                                        {{ $emp->factor_conversion }} {{ __('unidades') }}
                                    </td>
                                    <td class="py-3 text-right">
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarEmpaque({{ $emp->id }})" />
                                    </td>
                                </tr>
                            @empty
                                <!-- No extra packaging setups, standard unit only -->
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
