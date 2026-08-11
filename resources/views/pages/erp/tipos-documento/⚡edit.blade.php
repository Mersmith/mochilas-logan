<?php

use App\Models\TipoDocumento;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Tipo de Documento')] class extends Component {
    public TipoDocumento $tipoDocumento;
    public string $nombre = '';
    public string $codigo_sunat = '';
    public bool $activo = true;

    public function mount(TipoDocumento $tipoDocumento)
    {
        $this->tipoDocumento = $tipoDocumento;
        $this->nombre = $tipoDocumento->nombre;
        $this->codigo_sunat = $tipoDocumento->codigo_sunat ?? '';
        $this->activo = $tipoDocumento->activo;
    }

    public function guardar()
    {
        if (! auth()->user()->can('tipos-documento.editar')) {
            abort(403);
        }

        $this->validate([
            'nombre' => 'required|string|max:255',
            'codigo_sunat' => 'nullable|string|max:10',
            'activo' => 'boolean',
        ]);

        $this->tipoDocumento->update([
            'nombre' => $this->nombre,
            'codigo_sunat' => $this->codigo_sunat,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Tipo de Documento actualizado correctamente.');
        return redirect()->route('admin.tipos-documento.index');
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Tipo de Documento') }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos del tipo de documento: ') }} {{ $tipoDocumento->nombre }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.tipos-documento.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <form wire:submit.prevent="guardar" class="grid grid-cols-1 gap-6">
            <flux:field>
                <flux:label>{{ __('Nombre') }}</flux:label>
                <flux:input wire:model="nombre" placeholder="Ej. Factura, Boleta..." required />
                <flux:error name="nombre" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Código SUNAT') }}</flux:label>
                <flux:input wire:model="codigo_sunat" placeholder="Ej. 01, 03..." />
                <flux:error name="codigo_sunat" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Estado') }}</flux:label>
                <div class="flex items-center gap-3 h-10">
                    <flux:switch wire:model="activo" />
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $activo ? __('Activo') : __('Inactivo') }}
                    </span>
                </div>
            </flux:field>

            <div class="flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" href="{{ route('admin.tipos-documento.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    {{ __('Actualizar Tipo de Documento') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
