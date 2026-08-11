<?php

use App\Models\Cupon;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Cupón')] class extends Component {
    public Cupon $cupon;
    public string $codigo = '';
    public string $tipo_descuento = 'porcentaje';
    public string $valor_descuento = '';
    public string $monto_minimo_compra = '0';
    public string $usos_totales = '1';
    public string $fecha_inicio = '';
    public string $fecha_expiracion = '';
    public bool $activo = true;

    public function mount(Cupon $cupon)
    {
        $this->cupon = $cupon;
        $this->codigo = $cupon->codigo;
        $this->tipo_descuento = $cupon->tipo_descuento;
        $this->valor_descuento = (string) $cupon->valor_descuento;
        $this->monto_minimo_compra = (string) $cupon->monto_minimo_compra;
        $this->usos_totales = (string) $cupon->usos_totales;
        $this->fecha_inicio = $cupon->fecha_inicio ? $cupon->fecha_inicio->format('Y-m-d') : '';
        $this->fecha_expiracion = $cupon->fecha_expiracion ? $cupon->fecha_expiracion->format('Y-m-d') : '';
        $this->activo = $cupon->activo;
    }

    public function generateCode()
    {
        $this->codigo = strtoupper(substr(md5(uniqid()), 0, 8));
    }

    public function guardar()
    {
        if (! auth()->user()->can('promociones.crear')) {
            abort(403);
        }

        $this->codigo = strtoupper(trim($this->codigo));

        $this->validate([
            'codigo' => 'required|string|max:50|unique:cupons,codigo,' . $this->cupon->id,
            'tipo_descuento' => 'required|in:fijo,porcentaje',
            'valor_descuento' => 'required|numeric|min:0.01',
            'monto_minimo_compra' => 'required|numeric|min:0',
            'usos_totales' => 'required|integer|min:1',
            'fecha_inicio' => 'nullable|date',
            'fecha_expiracion' => 'nullable|date|after_or_equal:fecha_inicio',
        ]);

        if ($this->tipo_descuento === 'porcentaje' && $this->valor_descuento > 100) {
            $this->addError('valor_descuento', 'El porcentaje no puede ser mayor a 100.');
            return;
        }

        // Calcular los usos restantes ajustados
        $diferenciaUsos = $this->usos_totales - $this->cupon->usos_totales;
        $nuevosUsosRestantes = $this->cupon->usos_restantes + $diferenciaUsos;

        $this->cupon->update([
            'codigo' => $this->codigo,
            'tipo_descuento' => $this->tipo_descuento,
            'valor_descuento' => $this->valor_descuento,
            'monto_minimo_compra' => $this->monto_minimo_compra,
            'usos_totales' => $this->usos_totales,
            'usos_restantes' => max(0, $nuevosUsosRestantes),
            'fecha_inicio' => $this->fecha_inicio ?: null,
            'fecha_expiracion' => $this->fecha_expiracion ?: null,
            'activo' => $this->activo,
        ]);

        Flux::toast(variant: 'success', text: 'Cupón actualizado correctamente.');
        return redirect()->route('admin.cupones.index');
    }
}; ?>

<div class="space-y-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Cupón') }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos del código promocional.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.cupones.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <form wire:submit.prevent="guardar" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:field>
                <flux:label>{{ __('Código del Cupón') }}</flux:label>
                <div class="flex gap-2">
                    <flux:input wire:model="codigo" placeholder="Ej. VERANO2026" class="uppercase" required />
                    <flux:button variant="outline" wire:click="generateCode" icon="arrow-path" title="Generar aleatorio" />
                </div>
                <flux:error name="codigo" />
            </flux:field>

            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>{{ __('Tipo') }}</flux:label>
                    <flux:select wire:model="tipo_descuento">
                        <option value="porcentaje">% Porcentaje</option>
                        <option value="fijo">$ Monto Fijo</option>
                    </flux:select>
                    <flux:error name="tipo_descuento" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Valor') }}</flux:label>
                    <flux:input type="number" step="0.01" wire:model="valor_descuento" min="0.01" required />
                    <flux:error name="valor_descuento" />
                </flux:field>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:field>
                <flux:label>{{ __('Monto Mínimo de Compra ($)') }}</flux:label>
                <flux:input type="number" step="0.01" wire:model="monto_minimo_compra" min="0" required />
                <flux:error name="monto_minimo_compra" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Usos Máximos (Totales)') }}</flux:label>
                <flux:input type="number" wire:model="usos_totales" min="1" required />
                <flux:error name="usos_totales" />
            </flux:field>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:field>
                <flux:label>{{ __('Fecha de Inicio (Opcional)') }}</flux:label>
                <flux:input type="date" wire:model="fecha_inicio" />
                <flux:error name="fecha_inicio" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Fecha de Expiración (Opcional)') }}</flux:label>
                <flux:input type="date" wire:model="fecha_expiracion" />
                <flux:error name="fecha_expiracion" />
            </flux:field>
        </div>
        
        <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg text-sm text-zinc-600 dark:text-zinc-400">
            <strong>Estadísticas de uso:</strong> Este cupón ha sido utilizado {{ $cupon->usos_totales - $cupon->usos_restantes }} vez/veces. Quedan {{ $cupon->usos_restantes }} usos restantes actualmente.
        </div>

        <flux:switch wire:model="activo" label="Cupón Activo" description="Los cupones inactivos no pueden ser utilizados por los clientes." />

        <div class="flex justify-end pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button variant="primary" type="submit" icon="check">{{ __('Guardar Cambios') }}</flux:button>
        </div>
    </form>
</div>
