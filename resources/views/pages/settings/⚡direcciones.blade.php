<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\Direccion;
use App\Models\Pais;
use App\Models\Ubigeo;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new #[Title('Mis Direcciones')] #[Layout('layouts.settings')] class extends Component {
    public $direcciones = [];
    
    // Modal state
    public bool $showAddressModal = false;
    public ?int $editingId = null;

    // Form fields
    public string $alias = '';
    public string $destinatario = '';
    public string $telefono_contacto = '';
    public ?int $pais_id = null;
    public ?int $departamento_id = null;
    public ?int $provincia_id = null;
    public ?int $distrito_id = null;
    public string $direccion = '';
    public string $referencia = '';
    public string $codigo_postal = '';
    public bool $es_predeterminada = false;

    // Dropdown data
    public $paises = [];
    public $departamentos = [];
    public $provincias = [];
    public $distritos = [];

    public function mount()
    {
        $this->loadDirecciones();
        $this->paises = Pais::all();
        // Default to Peru if exists
        $peru = Pais::where('codigo_iso2', 'PE')->first();
        if ($peru) {
            $this->pais_id = $peru->id;
            $this->loadDepartamentos();
        }
    }

    public function loadDirecciones()
    {
        $cliente = Auth::user()->cliente;
        if ($cliente) {
            $this->direcciones = $cliente->direcciones()
                ->with(['pais', 'departamento', 'provincia', 'distrito'])
                ->orderByDesc('es_predeterminada')
                ->get();
        }
    }

    public function updatedPaisId()
    {
        $this->departamento_id = null;
        $this->provincia_id = null;
        $this->distrito_id = null;
        $this->provincias = [];
        $this->distritos = [];
        $this->loadDepartamentos();
    }

    public function updatedDepartamentoId()
    {
        $this->provincia_id = null;
        $this->distrito_id = null;
        $this->distritos = [];
        $this->loadProvincias();
    }

    public function updatedProvinciaId()
    {
        $this->distrito_id = null;
        $this->loadDistritos();
    }

    public function loadDepartamentos()
    {
        if ($this->pais_id) {
            $this->departamentos = Ubigeo::departamentos()->where('pais_id', $this->pais_id)->get();
        } else {
            $this->departamentos = [];
        }
    }

    public function loadProvincias()
    {
        if ($this->departamento_id) {
            $this->provincias = Ubigeo::where('parent_id', $this->departamento_id)->orderBy('nombre')->get();
        } else {
            $this->provincias = [];
        }
    }

    public function loadDistritos()
    {
        if ($this->provincia_id) {
            $this->distritos = Ubigeo::where('parent_id', $this->provincia_id)->orderBy('nombre')->get();
        } else {
            $this->distritos = [];
        }
    }

    public function resetForm()
    {
        $this->reset([
            'editingId', 'alias', 'destinatario', 'telefono_contacto',
            'departamento_id', 'provincia_id', 'distrito_id',
            'direccion', 'referencia', 'codigo_postal', 'es_predeterminada'
        ]);
        $this->resetValidation();
        // Keep default country
        $peru = Pais::where('codigo_iso2', 'PE')->first();
        if ($peru) {
            $this->pais_id = $peru->id;
            $this->loadDepartamentos();
        }
    }

    public function openAddressModal()
    {
        $this->resetForm();
        $this->showAddressModal = true;
    }

    public function editAddress($id)
    {
        $this->resetValidation();
        $direccion = Auth::user()->cliente->direcciones()->findOrFail($id);
        
        $this->editingId = $direccion->id;
        $this->alias = $direccion->alias;
        $this->destinatario = $direccion->destinatario ?? '';
        $this->telefono_contacto = $direccion->telefono_contacto ?? '';
        $this->pais_id = $direccion->pais_id;
        
        $this->loadDepartamentos();
        $this->departamento_id = $direccion->departamento_id;
        
        $this->loadProvincias();
        $this->provincia_id = $direccion->provincia_id;
        
        $this->loadDistritos();
        $this->distrito_id = $direccion->distrito_id;
        
        $this->direccion = $direccion->direccion;
        $this->referencia = $direccion->referencia ?? '';
        $this->codigo_postal = $direccion->codigo_postal ?? '';
        $this->es_predeterminada = $direccion->es_predeterminada;

        $this->showAddressModal = true;
    }

    public function saveAddress()
    {
        $validated = $this->validate([
            'alias' => 'required|string|max:50',
            'destinatario' => 'nullable|string|max:255',
            'telefono_contacto' => 'nullable|string|max:15',
            'pais_id' => 'required|exists:paises,id',
            'departamento_id' => 'required|exists:ubigeos,id',
            'provincia_id' => 'required|exists:ubigeos,id',
            'distrito_id' => 'required|exists:ubigeos,id',
            'direccion' => 'required|string|max:255',
            'referencia' => 'nullable|string|max:255',
            'codigo_postal' => 'nullable|string|max:10',
            'es_predeterminada' => 'boolean',
        ]);

        $cliente = Auth::user()->cliente;

        if ($this->editingId) {
            $direccion = $cliente->direcciones()->findOrFail($this->editingId);
            $direccion->update($validated);
            Flux::toast('Dirección actualizada exitosamente.', variant: 'success');
        } else {
            // Si es la primera dirección, forzar como predeterminada
            if ($cliente->direcciones()->count() === 0) {
                $validated['es_predeterminada'] = true;
            }
            $cliente->direcciones()->create($validated);
            Flux::toast('Dirección agregada exitosamente.', variant: 'success');
        }

        $this->showAddressModal = false;
        $this->loadDirecciones();
    }

    public function setAsDefault($id)
    {
        $cliente = Auth::user()->cliente;
        $direccion = $cliente->direcciones()->findOrFail($id);
        $direccion->update(['es_predeterminada' => true]);
        
        Flux::toast('Dirección principal actualizada.', variant: 'success');
        $this->loadDirecciones();
    }

    public function deleteAddress($id)
    {
        $cliente = Auth::user()->cliente;
        $direccion = $cliente->direcciones()->findOrFail($id);
        
        $wasDefault = $direccion->es_predeterminada;
        $direccion->delete();
        
        // Si borramos la predeterminada, y hay otras, marcamos la primera como predeterminada
        if ($wasDefault) {
            $nextAddress = $cliente->direcciones()->first();
            if ($nextAddress) {
                $nextAddress->update(['es_predeterminada' => true]);
            }
        }
        
        Flux::toast('Dirección eliminada.', variant: 'success');
        $this->loadDirecciones();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Mis Direcciones') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Mis Direcciones')" :subheading="__('Administra tus direcciones de envío')">
        <div class="flex justify-between items-center mb-6">
            <flux:heading size="lg">Direcciones</flux:heading>
            <flux:button variant="primary" wire:click="openAddressModal" icon="plus" size="sm">Agregar dirección</flux:button>
        </div>

        @if(count($direcciones) > 0)
            <div class="space-y-4">
                @foreach($direcciones as $dir)
                    <div class="flex flex-col md:flex-row md:items-start justify-between bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-5">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <flux:heading size="md" class="font-semibold">{{ $dir->alias }}</flux:heading>
                                @if($dir->es_predeterminada)
                                    <flux:badge color="blue" size="sm">Principal</flux:badge>
                                @endif
                            </div>
                            <div class="text-zinc-600 dark:text-zinc-400 text-sm space-y-1">
                                <p>{{ $dir->direccion }} {{ $dir->referencia ? '('.$dir->referencia.')' : '' }}</p>
                                <p>{{ $dir->distrito?->nombre }}, {{ $dir->provincia?->nombre }}, {{ $dir->departamento?->nombre }}</p>
                                <p>{{ $dir->pais?->nombre }}</p>
                                @if($dir->destinatario)
                                    <p class="mt-2 text-xs">Recibe: {{ $dir->destinatario }} {{ $dir->telefono_contacto ? ' - '.$dir->telefono_contacto : '' }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-4 mt-4 md:mt-0 text-sm">
                            @if(!$dir->es_predeterminada)
                                <button wire:click="setAsDefault({{ $dir->id }})" class="font-medium text-zinc-900 dark:text-white hover:underline transition">Guardar como principal</button>
                            @endif
                            <button wire:click="editAddress({{ $dir->id }})" class="font-medium text-zinc-900 dark:text-white hover:underline transition">Editar</button>
                            <button wire:click="deleteAddress({{ $dir->id }})" wire:confirm="¿Estás seguro de eliminar esta dirección?" class="font-medium text-red-600 hover:underline transition">Eliminar</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 text-center text-zinc-500">
                Aún no tienes direcciones registradas.
            </div>
        @endif
    </x-pages::settings.layout>

    <!-- Modal para Agregar/Editar Dirección -->
    <flux:modal wire:model="showAddressModal" class="md:w-[600px]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Editar Dirección' : 'Nueva Dirección' }}</flux:heading>

            <form wire:submit="saveAddress" class="space-y-6">
                <flux:input wire:model="alias" label="Alias (Ej: Casa, Oficina, Almacén Principal)" required />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:select wire:model.live="pais_id" label="País" required>
                        <option value="">Seleccione</option>
                        @foreach($paises as $pais)
                            <option value="{{ $pais->id }}">{{ $pais->nombre }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="departamento_id" label="Departamento / Región" required>
                        <option value="">Seleccione</option>
                        @foreach($departamentos as $dep)
                            <option value="{{ $dep->id }}">{{ $dep->nombre }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="provincia_id" label="Provincia" required>
                        <option value="">Seleccione</option>
                        @foreach($provincias as $prov)
                            <option value="{{ $prov->id }}">{{ $prov->nombre }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="distrito_id" label="Distrito" required>
                        <option value="">Seleccione</option>
                        @foreach($distritos as $dist)
                            <option value="{{ $dist->id }}">{{ $dist->nombre }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:input wire:model="direccion" label="Dirección Detallada (Calle, Número, Piso)" required />
                <flux:textarea wire:model="referencia" label="Referencia" rows="2" placeholder="Frente a un parque, casa verde, etc." />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:input wire:model="destinatario" label="Nombre de quien recibe (Opcional)" />
                    <flux:input wire:model="telefono_contacto" label="Teléfono de contacto (Opcional)" />
                </div>

                @if(!$editingId || !$es_predeterminada)
                    <flux:checkbox wire:model="es_predeterminada" label="Guardar como dirección principal" />
                @endif

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button variant="ghost" wire:click="$set('showAddressModal', false)">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary">Guardar Dirección</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
