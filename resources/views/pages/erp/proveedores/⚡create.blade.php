<?php

use App\Models\Proveedor;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Nuevo Proveedor')] class extends Component {
    public string $razon_social = '';
    public string $ruc = '';
    public string $direccion = '';
    public string $contacto_nombre = '';
    public string $contacto_celular = '';
    public bool $activo = true;

    public function guardar()
    {
        if (! auth()->user()->can('proveedores.editar')) {
            abort(403);
        }

        $this->validate([
            'razon_social' => 'required|string|max:255',
            'ruc' => 'nullable|string|max:11|unique:proveedores,ruc',
            'direccion' => 'nullable|string|max:255',
            'contacto_nombre' => 'nullable|string|max:255',
            'contacto_celular' => 'nullable|string|max:255',
            'activo' => 'boolean',
        ]);

        Proveedor::create([
            'razon_social' => $this->razon_social,
            'ruc' => $this->ruc ?: null,
            'direccion' => $this->direccion ?: null,
            'contacto_nombre' => $this->contacto_nombre ?: null,
            'contacto_celular' => $this->contacto_celular ?: null,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Registro creado correctamente.');
        return redirect()->route('admin.proveedores.index');
    }
}; ?>

<div class="space-y-6 max-w-2xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Nuevo Proveedor') }}</flux:heading>
            <flux:subheading>{{ __('Registra un nuevo proveedor.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.proveedores.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardar" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        <flux:field>
            <flux:label>{{ __('Razón Social') }}</flux:label>
            <flux:input wire:model="razon_social" placeholder="Ej. Corporación ABC S.A.C." required />
            <flux:error name="razon_social" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('RUC') }}</flux:label>
            <flux:input wire:model="ruc" placeholder="Ej. 20123456789" />
            <flux:error name="ruc" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Dirección') }}</flux:label>
            <flux:input wire:model="direccion" />
            <flux:error name="direccion" />
        </flux:field>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:field>
                <flux:label>{{ __('Nombre del Contacto') }}</flux:label>
                <flux:input wire:model="contacto_nombre" />
                <flux:error name="contacto_nombre" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Celular del Contacto') }}</flux:label>
                <flux:input wire:model="contacto_celular" />
                <flux:error name="contacto_celular" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('Estado') }}</flux:label>
            <div class="flex items-center gap-3 h-10 border border-zinc-200 dark:border-zinc-700 rounded-lg px-3">
                <flux:switch wire:model="activo" />
                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $activo ? __('Activo') : __('Inactivo') }}
                </span>
            </div>
        </flux:field>

        <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button variant="ghost" href="{{ route('admin.proveedores.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
            <flux:button variant="primary" type="submit" icon="check">{{ __('Guardar') }}</flux:button>
        </div>
    </form>
</div>
