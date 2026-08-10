<?php

use App\Models\Cliente;
use App\Models\ListaPrecio;
use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nuevo Cliente')] class extends Component {
    // ── Datos de Cuenta (User) ─────────────────────────
    public string $nombre = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    // ── Datos del Perfil (Cliente) ──────────────────────
    public string $tipo_persona = 'natural';
    public string $tipo_cliente = 'minorista';
    public ?int $lista_precio_id = null;
    public ?string $dni = null;
    public ?string $ruc = null;
    public ?string $razon_social = null;
    public ?string $telefono = null;
    public bool $activo = true;

    public function mount()
    {
        // Asignar lista de precios por defecto (la primera que encuentre)
        $this->lista_precio_id = ListaPrecio::first()?->id;
    }

    public function listasPrecios()
    {
        return ListaPrecio::orderBy('nombre')->get();
    }

    public function guardar()
    {
        if (! auth()->user()->can('clientes.crear')) {
            abort(403);
        }

        $rules = [
            // Validación del User
            'nombre'   => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',

            // Validación del Cliente
            'tipo_persona'    => 'required|in:natural,juridica',
            'tipo_cliente'    => 'required|in:minorista,mayorista,emprendedor',
            'lista_precio_id' => 'required|exists:lista_precios,id',
            'telefono'        => 'nullable|string|max:15',
        ];

        // Validaciones condicionales según tipo de persona
        if ($this->tipo_persona === 'natural') {
            $rules['dni'] = 'required|string|size:8|unique:clientes,dni';
            $this->ruc = null;
            $this->razon_social = null;
        } else {
            $rules['ruc'] = 'required|string|size:11|unique:clientes,ruc';
            $rules['razon_social'] = 'required|string|max:255';
            $this->dni = null;
        }

        $this->validate($rules);

        // Crear User
        $user = User::create([
            'name'     => $this->nombre,
            'email'    => $this->email,
            'password' => bcrypt($this->password),
            'activo'   => $this->activo,
        ]);

        // Asignar rol de cliente
        $user->assignRole('cliente');

        // Crear Perfil Cliente
        Cliente::create([
            'user_id'         => $user->id,
            'tipo_persona'    => $this->tipo_persona,
            'tipo_cliente'    => $this->tipo_cliente,
            'lista_precio_id' => $this->lista_precio_id,
            'dni'             => $this->dni,
            'ruc'             => $this->ruc,
            'razon_social'    => $this->razon_social,
            'telefono'        => $this->telefono,
            'activo'          => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Cliente creado correctamente.');
        return redirect()->route('admin.clientes.index');
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo Cliente') }}</flux:heading>
            <flux:subheading>{{ __('Crea un nuevo perfil de cliente y su cuenta de acceso.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.clientes.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardar" class="space-y-8">
        {{-- SECCIÓN: Cuenta de Usuario --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-zinc-200 dark:border-zinc-800">
                <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg">
                    <flux:icon.user class="size-5" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Cuenta de Acceso') }}</h3>
                    <p class="text-sm text-zinc-500">{{ __('Datos con los que el cliente ingresará al sistema.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Nombre de la cuenta (Responsable)') }}</flux:label>
                    <flux:input wire:model="nombre" placeholder="Ej. Juan Pérez" required />
                    <flux:error name="nombre" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Correo electrónico') }}</flux:label>
                    <flux:input wire:model="email" type="email" placeholder="cliente@ejemplo.com" required />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Contraseña') }}</flux:label>
                    <flux:input wire:model="password" type="password" placeholder="Mínimo 8 caracteres" required />
                    <flux:error name="password" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Confirmar Contraseña') }}</flux:label>
                    <flux:input wire:model="password_confirmation" type="password" placeholder="Repite la contraseña" required />
                </flux:field>
            </div>
        </div>

        {{-- SECCIÓN: Perfil Comercial --}}
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
            <div class="flex items-center gap-3 pb-4 border-b border-zinc-200 dark:border-zinc-800">
                <div class="p-2 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg">
                    <flux:icon.building-office class="size-5" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Perfil Comercial') }}</h3>
                    <p class="text-sm text-zinc-500">{{ __('Clasificación y datos fiscales del cliente.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <flux:field>
                    <flux:label>{{ __('Tipo de Persona') }}</flux:label>
                    <flux:select wire:model.live="tipo_persona" required>
                        <option value="natural">{{ __('Persona Natural') }}</option>
                        <option value="juridica">{{ __('Persona Jurídica (Empresa)') }}</option>
                    </flux:select>
                    <flux:error name="tipo_persona" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Clasificación del Cliente') }}</flux:label>
                    <flux:select wire:model="tipo_cliente" required>
                        <option value="minorista">{{ __('Minorista') }}</option>
                        <option value="mayorista">{{ __('Mayorista') }}</option>
                        <option value="emprendedor">{{ __('Emprendedor') }}</option>
                    </flux:select>
                    <flux:error name="tipo_cliente" />
                </flux:field>

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
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @if($tipo_persona === 'natural')
                    <flux:field>
                        <flux:label>{{ __('DNI') }}</flux:label>
                        <flux:input wire:model="dni" maxlength="8" placeholder="8 dígitos" required />
                        <flux:error name="dni" />
                    </flux:field>
                @else
                    <flux:field>
                        <flux:label>{{ __('RUC') }}</flux:label>
                        <flux:input wire:model="ruc" maxlength="11" placeholder="11 dígitos" required />
                        <flux:error name="ruc" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Razón Social') }}</flux:label>
                        <flux:input wire:model="razon_social" placeholder="Nombre de la empresa" required />
                        <flux:error name="razon_social" />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>{{ __('Teléfono / Celular') }}</flux:label>
                    <flux:input wire:model="telefono" placeholder="Ej. 999 888 777" />
                    <flux:error name="telefono" />
                </flux:field>

                <flux:field class="md:col-span-2">
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

        <div class="flex justify-end gap-3 pt-4">
            <flux:button variant="ghost" href="{{ route('admin.clientes.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
            <flux:button variant="primary" type="submit" icon="check">
                {{ __('Guardar Cliente') }}
            </flux:button>
        </div>
    </form>
</div>
