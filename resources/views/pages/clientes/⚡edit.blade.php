<?php

use App\Models\Cliente;
use App\Models\Direccion;
use App\Models\ListaPrecio;
use App\Models\Pais;
use App\Models\Ubigeo;
use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Cliente')] class extends Component {
    public Cliente $cliente;

    // ── Datos de Cuenta (User) ─────────────────────────
    public string $nombre = '';
    public string $email = '';

    // ── Datos del Perfil (Cliente) ──────────────────────
    public string $tipo_persona = 'natural';
    public string $tipo_cliente = 'minorista';
    public ?int $lista_precio_id = null;
    public ?string $dni = null;
    public ?string $ruc = null;
    public ?string $razon_social = null;
    public ?string $telefono = null;
    public bool $activo = true;

    // ── Estado Modal de Direcciones ─────────────────────
    public ?int $direccion_id = null;
    public ?int $pais_id = null;
    public ?int $departamento_id = null;
    public ?int $provincia_id = null;
    public ?int $distrito_id = null;
    public string $direccion = '';
    public string $referencia = '';
    public string $alias = '';

    // ── Opciones para Dropdowns (Caché local de Livewire)
    public $departamentos = [];
    public $provincias = [];
    public $distritos = [];

    public function mount(Cliente $cliente)
    {
        $this->cliente = $cliente->load(['user', 'direcciones.distrito', 'direcciones.provincia', 'direcciones.departamento', 'direcciones.pais']);

        $this->nombre = $cliente->user->name;
        $this->email = $cliente->user->email;

        $this->tipo_persona = $cliente->tipo_persona;
        $this->tipo_cliente = $cliente->tipo_cliente;
        $this->lista_precio_id = $cliente->lista_precio_id;
        $this->dni = $cliente->dni;
        $this->ruc = $cliente->ruc;
        $this->razon_social = $cliente->razon_social;
        $this->telefono = $cliente->telefono;
        $this->activo = $cliente->activo;
    }

    public function listasPrecios()
    {
        return ListaPrecio::orderBy('nombre')->get();
    }

    public function paises()
    {
        return Pais::orderBy('nombre')->get();
    }

    // ── Actualizaciones en Cascada para Direcciones ─────
    public function updatedPaisId($value)
    {
        $this->departamentos = $value ? Ubigeo::where('pais_id', $value)->departamentos()->get() : [];
        $this->provincia_id = null;
        $this->distrito_id = null;
        $this->provincias = [];
        $this->distritos = [];
    }

    public function updatedDepartamentoId($value)
    {
        $this->provincias = $value ? Ubigeo::where('parent_id', $value)->provincias()->get() : [];
        $this->distrito_id = null;
        $this->distritos = [];
    }

    public function updatedProvinciaId($value)
    {
        $this->distritos = $value ? Ubigeo::where('parent_id', $value)->distritos()->get() : [];
    }

    // ── Guardar Cambios Principales ─────────────────────
    public function guardar()
    {
        if (! auth()->user()->can('clientes.editar')) {
            abort(403);
        }

        $rules = [
            'nombre'   => 'required|string|max:255',
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($this->cliente->user_id)->whereNull('deleted_at')],
            
            'tipo_persona'    => 'required|in:natural,juridica',
            'tipo_cliente'    => 'required|in:minorista,mayorista,emprendedor',
            'lista_precio_id' => 'required|exists:lista_precios,id',
            'telefono'        => 'nullable|string|max:15',
        ];

        if ($this->tipo_persona === 'natural') {
            $rules['dni'] = ['required', 'string', 'size:8', Rule::unique('clientes', 'dni')->ignore($this->cliente->id)];
            $this->ruc = null;
            $this->razon_social = null;
        } else {
            $rules['ruc'] = ['required', 'string', 'size:11', Rule::unique('clientes', 'ruc')->ignore($this->cliente->id)];
            $rules['razon_social'] = 'required|string|max:255';
            $this->dni = null;
        }

        $this->validate($rules);

        $this->cliente->user->update([
            'name'   => $this->nombre,
            'email'  => $this->email,
            'activo' => $this->activo,
        ]);

        $this->cliente->update([
            'tipo_persona'    => $this->tipo_persona,
            'tipo_cliente'    => $this->tipo_cliente,
            'lista_precio_id' => $this->lista_precio_id,
            'dni'             => $this->dni,
            'ruc'             => $this->ruc,
            'razon_social'    => $this->razon_social,
            'telefono'        => $this->telefono,
            'activo'          => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Cliente actualizado correctamente.');
    }

    // ── Gestión de Direcciones ──────────────────────────

    public function abrirModalDireccion(?int $id = null)
    {
        $this->resetValidation();

        if ($id) {
            $direccion = Direccion::findOrFail($id);
            $this->direccion_id = $direccion->id;
            $this->pais_id = $direccion->pais_id;
            $this->updatedPaisId($this->pais_id);
            $this->departamento_id = $direccion->departamento_id;
            $this->updatedDepartamentoId($this->departamento_id);
            $this->provincia_id = $direccion->provincia_id;
            $this->updatedProvinciaId($this->provincia_id);
            $this->distrito_id = $direccion->distrito_id;
            
            $this->direccion = $direccion->direccion;
            $this->referencia = $direccion->referencia ?? '';
            $this->alias = $direccion->alias;
        } else {
            $this->reset(['direccion_id', 'pais_id', 'departamento_id', 'provincia_id', 'distrito_id', 'direccion', 'referencia', 'alias']);
            // Si hay un país por defecto (ej: Perú)
            $peru = Pais::where('nombre', 'Perú')->first();
            if ($peru) {
                $this->pais_id = $peru->id;
                $this->updatedPaisId($this->pais_id);
            }
        }

        Flux::modal('modal-direccion')->show();
    }

    public function guardarDireccion()
    {
        $this->validate([
            'pais_id'         => 'required|exists:paises,id',
            'departamento_id' => 'required|exists:ubigeos,id',
            'provincia_id'    => 'required|exists:ubigeos,id',
            'distrito_id'     => 'required|exists:ubigeos,id',
            'direccion'       => 'required|string|max:255',
            'alias'           => 'required|string|max:50',
            'referencia'      => 'nullable|string|max:255',
        ]);

        // Si es la primera dirección, la hacemos predeterminada
        $esPredeterminada = $this->cliente->direcciones()->count() === 0;

        $data = [
            'pais_id'         => $this->pais_id,
            'departamento_id' => $this->departamento_id,
            'provincia_id'    => $this->provincia_id,
            'distrito_id'     => $this->distrito_id,
            'direccion'       => $this->direccion,
            'alias'           => $this->alias,
            'referencia'      => $this->referencia,
        ];

        if ($this->direccion_id) {
            Direccion::where('id', $this->direccion_id)->where('cliente_id', $this->cliente->id)->update($data);
            Flux::toast(variant: 'success', text: 'Dirección actualizada.');
        } else {
            $data['es_predeterminada'] = $esPredeterminada;
            $this->cliente->direcciones()->create($data);
            Flux::toast(variant: 'success', text: 'Dirección agregada.');
        }

        $this->cliente->load('direcciones.distrito', 'direcciones.provincia', 'direcciones.departamento', 'direcciones.pais');
        Flux::modal('modal-direccion')->close();
    }

    public function eliminarDireccion(int $id)
    {
        $direccion = Direccion::where('id', $id)->where('cliente_id', $this->cliente->id)->firstOrFail();
        
        if ($direccion->es_predeterminada && $this->cliente->direcciones()->count() > 1) {
            Flux::toast(variant: 'warning', text: 'No puedes eliminar la dirección predeterminada. Cambia otra como predeterminada primero.');
            return;
        }

        $direccion->delete();
        $this->cliente->load('direcciones');
        Flux::toast(variant: 'success', text: 'Dirección eliminada.');
    }

    public function predeterminarDireccion(int $id)
    {
        // Quitar predeterminada de todas
        $this->cliente->direcciones()->update(['es_predeterminada' => false]);
        // Asignar a la seleccionada
        Direccion::where('id', $id)->where('cliente_id', $this->cliente->id)->update(['es_predeterminada' => true]);
        
        $this->cliente->load('direcciones');
        Flux::toast(variant: 'success', text: 'Dirección predeterminada actualizada.');
    }
}; ?>

<div class="space-y-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Cliente') }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos del cliente: ') }} {{ $cliente->nombreMostrar() }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.clientes.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardar" class="space-y-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Columna Izquierda: Datos del Usuario --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-zinc-200 dark:border-zinc-800">
                    <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg">
                        <flux:icon.user class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Cuenta de Acceso') }}</h3>
                    </div>
                </div>

                <div class="space-y-6">
                    <flux:field>
                        <flux:label>{{ __('Nombre de la cuenta') }}</flux:label>
                        <flux:input wire:model="nombre" placeholder="Ej. Juan Pérez" required />
                        <flux:error name="nombre" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Correo electrónico') }}</flux:label>
                        <flux:input wire:model="email" type="email" placeholder="cliente@ejemplo.com" required />
                        <flux:error name="email" />
                    </flux:field>
                    
                    <flux:field>
                        <flux:label>{{ __('Estado del Cliente') }}</flux:label>
                        <div class="flex items-center gap-3 h-10">
                            <flux:switch wire:model="activo" />
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $activo ? __('Cliente Activo') : __('Cliente Inactivo') }}
                            </span>
                        </div>
                    </flux:field>
                </div>
            </div>

            {{-- Columna Derecha: Perfil Comercial --}}
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-zinc-200 dark:border-zinc-800">
                    <div class="p-2 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg">
                        <flux:icon.building-office class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Perfil Comercial') }}</h3>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>{{ __('Tipo de Persona') }}</flux:label>
                            <flux:select wire:model.live="tipo_persona" required>
                                <option value="natural">{{ __('Persona Natural') }}</option>
                                <option value="juridica">{{ __('Persona Jurídica (Empresa)') }}</option>
                            </flux:select>
                            <flux:error name="tipo_persona" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Clasificación') }}</flux:label>
                            <flux:select wire:model="tipo_cliente" required>
                                <option value="minorista">{{ __('Minorista') }}</option>
                                <option value="mayorista">{{ __('Mayorista') }}</option>
                                <option value="emprendedor">{{ __('Emprendedor') }}</option>
                            </flux:select>
                            <flux:error name="tipo_cliente" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Lista de Precios') }}</flux:label>
                        <flux:select wire:model="lista_precio_id" required>
                            <option value="">{{ __('Seleccionar lista') }}</option>
                            @foreach($this->listasPrecios() as $lista)
                                <option value="{{ $lista->id }}">{{ $lista->nombre }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="lista_precio_id" />
                    </flux:field>

                    <div class="grid grid-cols-2 gap-4">
                        @if($tipo_persona === 'natural')
                            <flux:field>
                                <flux:label>{{ __('DNI') }}</flux:label>
                                <flux:input wire:model="dni" maxlength="8" required />
                                <flux:error name="dni" />
                            </flux:field>
                        @else
                            <flux:field>
                                <flux:label>{{ __('RUC') }}</flux:label>
                                <flux:input wire:model="ruc" maxlength="11" required />
                                <flux:error name="ruc" />
                            </flux:field>

                            <flux:field class="col-span-2">
                                <flux:label>{{ __('Razón Social') }}</flux:label>
                                <flux:input wire:model="razon_social" required />
                                <flux:error name="razon_social" />
                            </flux:field>
                        @endif
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Teléfono / Celular') }}</flux:label>
                        <flux:input wire:model="telefono" />
                        <flux:error name="telefono" />
                    </flux:field>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
            <flux:button variant="primary" type="submit" icon="check">
                {{ __('Guardar Cambios Principales') }}
            </flux:button>
        </div>
    </form>

    {{-- SECCIÓN DIRECCIONES --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm mt-8 space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-zinc-200 dark:border-zinc-800">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-lg">
                    <flux:icon.map-pin class="size-5" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Libreta de Direcciones') }}</h3>
                    <p class="text-sm text-zinc-500">{{ __('Gestiona los lugares de entrega de este cliente.') }}</p>
                </div>
            </div>
            <flux:button variant="subtle" icon="plus" wire:click="abrirModalDireccion">
                {{ __('Nueva Dirección') }}
            </flux:button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($cliente->direcciones as $dir)
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 relative flex flex-col {{ $dir->es_predeterminada ? 'ring-2 ring-indigo-500 dark:ring-indigo-400 bg-indigo-50/30 dark:bg-indigo-900/10' : 'bg-white dark:bg-zinc-900' }}">
                    @if($dir->es_predeterminada)
                        <div class="absolute -top-2.5 -right-2.5">
                            <span class="flex size-6 items-center justify-center rounded-full bg-indigo-500 text-white shadow-sm ring-2 ring-white dark:ring-zinc-900">
                                <flux:icon.star class="size-3.5" variant="solid" />
                            </span>
                        </div>
                    @endif
                    
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $dir->alias }}</span>
                        <div class="flex items-center gap-1">
                            <flux:dropdown>
                                <flux:button variant="ghost" icon="ellipsis-vertical" size="sm" class="!px-2" />
                                <flux:menu>
                                    @if(!$dir->es_predeterminada)
                                        <flux:menu.item icon="star" wire:click="predeterminarDireccion({{ $dir->id }})">
                                            {{ __('Hacer Predeterminada') }}
                                        </flux:menu.item>
                                    @endif
                                    <flux:menu.item icon="pencil-square" wire:click="abrirModalDireccion({{ $dir->id }})">
                                        {{ __('Editar Dirección') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" class="text-red-600 dark:text-red-400" wire:click="eliminarDireccion({{ $dir->id }})" wire:confirm="¿Seguro que deseas eliminar esta dirección?">
                                        {{ __('Eliminar') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>
                    
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 flex-1">{{ $dir->direccionCompleta() }}</p>
                    
                    @if($dir->referencia)
                        <p class="text-xs text-zinc-500 mt-2 flex items-start gap-1">
                            <flux:icon.information-circle class="size-4 shrink-0" />
                            {{ $dir->referencia }}
                        </p>
                    @endif
                </div>
            @empty
                <div class="col-span-full py-8 text-center text-zinc-500 bg-zinc-50 dark:bg-zinc-800/30 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700">
                    <flux:icon.map class="size-8 mx-auto mb-2 opacity-40" />
                    <p>{{ __('El cliente aún no tiene direcciones registradas.') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- MODAL DIRECCIÓN --}}
    <flux:modal name="modal-direccion" class="w-full max-w-2xl">
        <flux:heading>{{ $direccion_id ? __('Editar Dirección') : __('Nueva Dirección') }}</flux:heading>
        
        <form wire:submit.prevent="guardarDireccion" class="mt-4 space-y-4">
            <flux:field>
                <flux:label>{{ __('Alias (Ej: Casa, Oficina, Almacén Principal)') }}</flux:label>
                <flux:input wire:model="alias" required maxlength="50" />
                <flux:error name="alias" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('País') }}</flux:label>
                    <flux:select wire:model.live="pais_id" required>
                        <option value="">{{ __('Seleccione') }}</option>
                        @foreach($this->paises() as $p)
                            <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="pais_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Departamento / Región') }}</flux:label>
                    <flux:select wire:model.live="departamento_id" required>
                        <option value="">{{ __('Seleccione') }}</option>
                        @foreach($departamentos as $dep)
                            <option value="{{ $dep->id }}">{{ $dep->nombre }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="departamento_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Provincia') }}</flux:label>
                    <flux:select wire:model.live="provincia_id" required>
                        <option value="">{{ __('Seleccione') }}</option>
                        @foreach($provincias as $prov)
                            <option value="{{ $prov->id }}">{{ $prov->nombre }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="provincia_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Distrito') }}</flux:label>
                    <flux:select wire:model="distrito_id" required>
                        <option value="">{{ __('Seleccione') }}</option>
                        @foreach($distritos as $dist)
                            <option value="{{ $dist->id }}">{{ $dist->nombre }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="distrito_id" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Dirección Detallada (Calle, Número, Piso)') }}</flux:label>
                <flux:input wire:model="direccion" required />
                <flux:error name="direccion" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Referencia') }}</flux:label>
                <flux:textarea wire:model="referencia" rows="2" placeholder="Frente a un parque, casa verde, etc." />
                <flux:error name="referencia" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">
                    {{ __('Guardar Dirección') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
