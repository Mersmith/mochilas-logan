<?php

use App\Models\Cliente;
use App\Models\ListaPrecio;
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

    public function mount(Cliente $cliente)
    {
        $this->cliente = $cliente->load(['user']);

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

        Flux::toast(variant: 'success', text: __('Cliente actualizado correctamente.'));
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
                        <p class="text-sm text-zinc-500">{{ __('Credenciales y estado de la cuenta.') }}</p>
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
                        <p class="text-sm text-zinc-500">{{ __('Clasificación comercial y datos fiscales.') }}</p>
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

    {{-- Componente Independiente de Libreta de Direcciones --}}
    <livewire:pages::erp.clientes.direcciones :cliente="$cliente" :key="'direcciones-'.$cliente->id" />
</div>
