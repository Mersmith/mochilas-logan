<?php

use App\Models\Atributo;
use App\Models\AtributoValor;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Gestión de Atributos')] class extends Component {
    // Parent Atributo Form
    public ?int $atributo_id = null;
    public string $nombre = '';

    // Child AtributoValor Form (for Modal)
    public ?int $manage_atributo_id = null;
    public string $manage_atributo_nombre = '';
    
    public ?int $valor_id = null;
    public string $valor = '';
    public string $codigo_color_hex = '';

    public function guardarAtributo(): void
    {
        if (!auth()->user()->hasPermissionTo('atributos.editar')) {
            abort(403, 'No tienes permiso para editar atributos.');
        }

        $this->validate([
            'nombre' => 'required|string|max:255|unique:atributos,nombre,' . ($this->atributo_id ?: 'NULL'),
        ]);

        if ($this->atributo_id) {
            $attr = Atributo::findOrFail($this->atributo_id);
            $attr->update([
                'nombre' => $this->nombre,
            ]);
            Flux::toast(variant: 'success', text: __('Atributo actualizado.'));
        } else {
            Atributo::create([
                'nombre' => $this->nombre,
            ]);
            Flux::toast(variant: 'success', text: __('Atributo registrado.'));
        }

        $this->limpiarFormAtributo();
    }

    public function editarAtributo(int $id): void
    {
        $attr = Atributo::findOrFail($id);
        $this->atributo_id = $attr->id;
        $this->nombre = $attr->nombre;
    }

    public function eliminarAtributo(int $id): void
    {
        if (!auth()->user()->hasPermissionTo('atributos.editar')) {
            abort(403, 'No tienes permiso para eliminar atributos.');
        }

        $attr = Atributo::findOrFail($id);
        
        // Should block if products are already using this attribute.
        // For now, since onDelete cascade is on the migration for valores, it will just delete the values.
        
        $attr->delete();
        Flux::toast(variant: 'success', text: __('Atributo eliminado.'));
    }

    public function limpiarFormAtributo(): void
    {
        $this->atributo_id = null;
        $this->nombre = '';
    }

    // --- MANAGE VALORES ---

    public function manageValores(int $id): void
    {
        $attr = Atributo::findOrFail($id);
        $this->manage_atributo_id = $attr->id;
        $this->manage_atributo_nombre = $attr->nombre;
        $this->limpiarFormValor();
        
        $this->modal('modal-valores')->show();
    }

    public function guardarValor(): void
    {
        if (!auth()->user()->hasPermissionTo('atributos.editar')) {
            abort(403, 'No tienes permiso para editar atributos.');
        }

        $this->validate([
            'valor' => 'required|string|max:255',
            'codigo_color_hex' => 'nullable|string|max:20',
        ]);

        if ($this->valor_id) {
            $val = AtributoValor::findOrFail($this->valor_id);
            $val->update([
                'valor' => $this->valor,
                'codigo_color_hex' => $this->codigo_color_hex,
            ]);
            Flux::toast(variant: 'success', text: __('Valor actualizado.'));
        } else {
            AtributoValor::create([
                'atributo_id' => $this->manage_atributo_id,
                'valor' => $this->valor,
                'codigo_color_hex' => $this->codigo_color_hex,
            ]);
            Flux::toast(variant: 'success', text: __('Valor registrado.'));
        }

        $this->limpiarFormValor();
    }

    public function editarValor(int $id): void
    {
        $val = AtributoValor::findOrFail($id);
        $this->valor_id = $val->id;
        $this->valor = $val->valor;
        $this->codigo_color_hex = $val->codigo_color_hex ?? '';
    }

    public function eliminarValor(int $id): void
    {
        if (!auth()->user()->hasPermissionTo('atributos.editar')) {
            abort(403, 'No tienes permiso para eliminar atributos.');
        }

        $val = AtributoValor::findOrFail($id);
        $val->delete();
        Flux::toast(variant: 'success', text: __('Valor eliminado.'));
    }

    public function limpiarFormValor(): void
    {
        $this->valor_id = null;
        $this->valor = '';
        $this->codigo_color_hex = '';
    }

    #[Computed]
    public function atributos()
    {
        return Atributo::withCount('valores')->orderBy('nombre', 'asc')->get();
    }

    #[Computed]
    public function valoresActuales()
    {
        if (!$this->manage_atributo_id) {
            return [];
        }
        return AtributoValor::where('atributo_id', $this->manage_atributo_id)->orderBy('valor', 'asc')->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Atributos') }}</flux:heading>
            <flux:subheading>{{ __('Administra los atributos (Ej. Color, Talla) y sus posibles valores.') }}</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- FORMULARIO ATRIBUTO PADRE -->
        @can('atributos.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ $atributo_id ? __('Editar Atributo') : __('Nuevo Atributo') }}</flux:heading>
                
                <form wire:submit.prevent="guardarAtributo" class="space-y-4">
                    <flux:input wire:model="nombre" :label="__('Nombre del Atributo')" placeholder="Ej. Color, Talla, Material..." required />
                    
                    <div class="flex gap-4 pt-2">
                        @if($atributo_id)
                            <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarFormAtributo">{{ __('Cancelar') }}</flux:button>
                        @endif
                        <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                            {{ $atributo_id ? __('Actualizar') : __('Guardar') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan

        <!-- LISTA ATRIBUTOS -->
        <div class="{{ auth()->user()->can('atributos.editar') ? 'lg:col-span-2' : 'lg:col-span-3' }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
            <flux:heading size="lg">{{ __('Atributos Registrados') }}</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                            <th class="p-3">{{ __('Nombre') }}</th>
                            <th class="p-3 text-center">{{ __('Valores Registrados') }}</th>
                            @can('atributos.editar')
                                <th class="p-3 text-right">{{ __('Acciones') }}</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->atributos as $attr)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-medium text-zinc-900 dark:text-white">{{ $attr->nombre }}</td>
                                <td class="p-3 text-center text-zinc-600 dark:text-zinc-400">
                                    <flux:badge size="sm">{{ $attr->valores_count }}</flux:badge>
                                </td>
                                @can('atributos.editar')
                                    <td class="p-3 text-right space-x-2">
                                        <flux:button variant="ghost" size="sm" wire:click.prevent="manageValores({{ $attr->id }})">
                                            {{ __('Ver Valores') }}
                                        </flux:button>
                                        <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editarAtributo({{ $attr->id }})" />
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarAtributo({{ $attr->id }})" wire:confirm="¿Está seguro de eliminar este atributo y TODOS sus valores?" />
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->can('atributos.editar') ? 3 : 2 }}" class="text-center py-8 text-zinc-500">
                                    {{ __('No hay atributos registrados.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL PARA GESTIONAR VALORES DEL ATRIBUTO -->
    <flux:modal name="modal-valores" class="w-full max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Valores de: ') }} {{ $manage_atributo_nombre }}</flux:heading>
                <flux:subheading>{{ __('Agrega o edita las opciones para este atributo.') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- FORM VALORES -->
                <div>
                    <form wire:submit.prevent="guardarValor" class="space-y-4">
                        <flux:input wire:model="valor" :label="__('Nombre del Valor')" placeholder="Ej. Rojo, M, 42..." required />
                        
                        <flux:input wire:model="codigo_color_hex" :label="__('Código Hex (Opcional)')" placeholder="Ej. #FF0000" />
                        @if($codigo_color_hex)
                            <div class="flex items-center gap-2 text-sm text-zinc-500 mt-1">
                                <span>{{ __('Previsualización:') }}</span>
                                <div class="w-6 h-6 rounded-full border border-zinc-200" style="background-color: {{ $codigo_color_hex }}"></div>
                            </div>
                        @endif

                        <div class="flex gap-4 pt-2">
                            @if($valor_id)
                                <flux:button variant="ghost" class="flex-1" wire:click.prevent="limpiarFormValor">{{ __('Cancelar') }}</flux:button>
                            @endif
                            <flux:button variant="primary" type="submit" class="flex-1" icon="check">
                                {{ $valor_id ? __('Actualizar') : __('Agregar') }}
                            </flux:button>
                        </div>
                    </form>
                </div>

                <!-- LISTA VALORES -->
                <div>
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 rounded-xl p-4 h-[300px] overflow-y-auto">
                        @if(count($this->valoresActuales) > 0)
                            <ul class="space-y-2">
                                @foreach($this->valoresActuales as $val)
                                    <li class="flex items-center justify-between p-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-sm">
                                        <div class="flex items-center gap-2">
                                            @if($val->codigo_color_hex)
                                                <div class="w-4 h-4 rounded-full border border-zinc-200 shrink-0" style="background-color: {{ $val->codigo_color_hex }}"></div>
                                            @endif
                                            <span class="font-medium text-sm">{{ $val->valor }}</span>
                                        </div>
                                        <div class="flex items-center space-x-1 shrink-0">
                                            <flux:button variant="ghost" icon="pencil-square" size="sm" wire:click.prevent="editarValor({{ $val->id }})" />
                                            <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarValor({{ $val->id }})" wire:confirm="¿Seguro?" />
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="flex items-center justify-center h-full text-zinc-500 text-sm">
                                {{ __('No hay valores registrados.') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cerrar') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
