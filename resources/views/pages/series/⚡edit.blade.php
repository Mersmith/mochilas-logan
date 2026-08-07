<?php

use App\Models\Serie;
use App\Models\Sede;
use App\Models\TipoDocumento;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Component;

new #[Title('Editar Serie')] class extends Component {
    public Serie $serieObj;
    public ?int $sede_id = null;
    public ?int $tipo_documento_id = null;
    public string $serie = '';
    public int $correlativo = 0;
    public bool $activo = true;

    public function mount(Serie $serie)
    {
        $this->serieObj = $serie;
        $this->sede_id = $serie->sede_id;
        $this->tipo_documento_id = $serie->tipo_documento_id;
        $this->serie = $serie->serie;
        $this->correlativo = $serie->correlativo;
        $this->activo = $serie->activo;
    }

    #[Computed]
    public function sedes()
    {
        return Sede::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function tiposDocumento()
    {
        return TipoDocumento::where('activo', true)->orderBy('nombre')->get();
    }

    public function guardar()
    {
        if (! auth()->user()->can('series.editar')) {
            abort(403);
        }

        $this->validate([
            'sede_id' => 'required|exists:sedes,id',
            'tipo_documento_id' => 'required|exists:tipo_documentos,id',
            'serie' => 'required|string|max:10',
            'correlativo' => 'required|integer|min:0',
            'activo' => 'boolean',
        ]);

        $this->serieObj->update([
            'sede_id' => $this->sede_id,
            'tipo_documento_id' => $this->tipo_documento_id,
            'serie' => $this->serie,
            'correlativo' => $this->correlativo,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Serie actualizada correctamente.');
        return redirect()->route('admin.series.index');
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Serie') }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos de la serie: ') }} {{ $serieObj->serie }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.series.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <form wire:submit.prevent="guardar" class="grid grid-cols-1 gap-6">
            <flux:field>
                <flux:label>{{ __('Sede') }}</flux:label>
                <flux:select wire:model="sede_id" required>
                    <flux:select.option value="">{{ __('Seleccione una Sede') }}</flux:select.option>
                    @foreach($this->sedes as $sede)
                        <flux:select.option :value="$sede->id">{{ $sede->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="sede_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Tipo de Documento') }}</flux:label>
                <flux:select wire:model="tipo_documento_id" required>
                    <flux:select.option value="">{{ __('Seleccione Tipo de Documento') }}</flux:select.option>
                    @foreach($this->tiposDocumento as $tipo)
                        <flux:select.option :value="$tipo->id">{{ $tipo->nombre }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="tipo_documento_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Serie') }}</flux:label>
                <flux:input wire:model="serie" placeholder="Ej. F001, B001..." required />
                <flux:error name="serie" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Correlativo') }}</flux:label>
                <flux:input type="number" wire:model="correlativo" min="0" required />
                <flux:error name="correlativo" />
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
                <flux:button variant="ghost" href="{{ route('admin.series.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    {{ __('Actualizar Serie') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
