<?php

use App\Models\Cliente;
use App\Models\Direccion;
use App\Models\Pais;
use App\Models\Ubigeo;
use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public Cliente $cliente;

    // ── Estado Modal de Direcciones ─────────────────────
    public ?int $direccion_id = null;
    public ?int $pais_id = null;
    public ?int $departamento_id = null;
    public ?int $provincia_id = null;
    public ?int $distrito_id = null;
    public string $direccion = '';
    public string $referencia = '';
    public string $alias = '';

    // ── Opciones para Dropdowns
    public $departamentos = [];
    public $provincias = [];
    public $distritos = [];

    public function mount(Cliente $cliente)
    {
        $this->cliente = $cliente->load(['direcciones.distrito', 'direcciones.provincia', 'direcciones.departamento', 'direcciones.pais']);
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

    public function abrirModalDireccion(?int $id = null)
    {
        $this->resetValidation();

        if ($id) {
            $dir = Direccion::where('id', $id)->where('cliente_id', $this->cliente->id)->firstOrFail();
            $this->direccion_id = $dir->id;
            $this->pais_id = $dir->pais_id;
            $this->updatedPaisId($this->pais_id);
            $this->departamento_id = $dir->departamento_id;
            $this->updatedDepartamentoId($this->departamento_id);
            $this->provincia_id = $dir->provincia_id;
            $this->updatedProvinciaId($this->provincia_id);
            $this->distrito_id = $dir->distrito_id;

            $this->direccion = $dir->direccion;
            $this->referencia = $dir->referencia ?? '';
            $this->alias = $dir->alias;
        } else {
            $this->reset(['direccion_id', 'pais_id', 'departamento_id', 'provincia_id', 'distrito_id', 'direccion', 'referencia', 'alias']);
            $peru = Pais::where('nombre', 'Perú')->orWhere('codigo_iso2', 'PE')->first();
            if ($peru) {
                $this->pais_id = $peru->id;
                $this->updatedPaisId($this->pais_id);
            }
        }

        Flux::modal('modal-direccion-erp')->show();
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
            Flux::toast(variant: 'success', text: __('Dirección actualizada.'));
        } else {
            $data['es_predeterminada'] = $esPredeterminada;
            $this->cliente->direcciones()->create($data);
            Flux::toast(variant: 'success', text: __('Dirección agregada.'));
        }

        $this->cliente->load('direcciones.distrito', 'direcciones.provincia', 'direcciones.departamento', 'direcciones.pais');
        Flux::modal('modal-direccion-erp')->close();
    }

    public function eliminarDireccion(int $id)
    {
        $direccion = Direccion::where('id', $id)->where('cliente_id', $this->cliente->id)->firstOrFail();

        if ($direccion->es_predeterminada && $this->cliente->direcciones()->count() > 1) {
            Flux::toast(variant: 'warning', text: __('No puedes eliminar la dirección predeterminada. Establece otra como predeterminada primero.'));
            return;
        }

        $wasDefault = $direccion->es_predeterminada;
        $direccion->delete();

        if ($wasDefault) {
            $next = $this->cliente->direcciones()->first();
            if ($next) {
                $next->update(['es_predeterminada' => true]);
            }
        }

        $this->cliente->load('direcciones');
        Flux::toast(variant: 'success', text: __('Dirección eliminada.'));
    }

    public function predeterminarDireccion(int $id)
    {
        $this->cliente->direcciones()->update(['es_predeterminada' => false]);
        Direccion::where('id', $id)->where('cliente_id', $this->cliente->id)->update(['es_predeterminada' => true]);

        $this->cliente->load('direcciones');
        Flux::toast(variant: 'success', text: __('Dirección predeterminada actualizada.'));
    }
}; ?>

<div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
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
                        <span class="flex size-6 items-center justify-center rounded-full bg-indigo-500 text-white shadow-sm ring-2 ring-white dark:ring-zinc-900" title="{{ __('Dirección Principal') }}">
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
                                <flux:menu.item icon="trash" class="text-red-600 dark:text-red-400" wire:click="eliminarDireccion({{ $dir->id }})" wire:confirm="{{ __('¿Seguro que deseas eliminar esta dirección?') }}">
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

    {{-- MODAL DIRECCIÓN --}}
    <flux:modal name="modal-direccion-erp" class="w-full max-w-2xl">
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
                <flux:textarea wire:model="referencia" rows="2" placeholder="{{ __('Frente a un parque, casa verde, etc.') }}" />
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
