<?php

use App\Models\Cupon;
use App\Models\Descuento;
use App\Models\Producto;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Descuentos y Cupones')] class extends Component {
    public string $activeTab = 'cupones';

    // Coupon Form Properties
    public string $cup_codigo = '';
    public string $cup_tipo_descuento = 'porcentaje';
    public float $cup_valor_descuento = 0.00;
    public float $cup_monto_minimo_compra = 0.00;
    public int $cup_usos_totales = 1;
    public ?string $cup_fecha_inicio = null;
    public ?string $cup_fecha_expiracion = null;
    public bool $cup_activo = true;

    // Discount Form Properties
    public string $desc_nombre = '';
    public int $desc_porcentaje = 0;
    public ?string $desc_fecha_inicio = null;
    public ?string $desc_fecha_fin = null;
    public bool $desc_activo = true;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->cup_fecha_inicio = now()->format('Y-m-d');
        $this->cup_fecha_expiracion = now()->addMonths(1)->format('Y-m-d');
        
        $this->desc_fecha_inicio = now()->format('Y-m-d');
        $this->desc_fecha_fin = now()->addMonths(1)->format('Y-m-d');
    }

    /**
     * Create coupon.
     */
    public function crearCupon(): void
    {
        $this->validate([
            'cup_codigo' => 'required|string|unique:cupons,codigo|max:50',
            'cup_tipo_descuento' => 'required|in:fijo,porcentaje',
            'cup_valor_descuento' => 'required|numeric|min:0.01',
            'cup_monto_minimo_compra' => 'required|numeric|min:0',
            'cup_usos_totales' => 'required|integer|min:1',
            'cup_fecha_inicio' => 'nullable|date',
            'cup_fecha_expiracion' => 'nullable|date|after_or_equal:cup_fecha_inicio',
            'cup_activo' => 'required|boolean',
        ]);

        Cupon::create([
            'codigo' => strtoupper($this->cup_codigo),
            'tipo_descuento' => $this->cup_tipo_descuento,
            'valor_descuento' => $this->cup_valor_descuento,
            'monto_minimo_compra' => $this->cup_monto_minimo_compra,
            'usos_totales' => $this->cup_usos_totales,
            'usos_restantes' => $this->cup_usos_totales,
            'fecha_inicio' => $this->cup_fecha_inicio,
            'fecha_expiracion' => $this->cup_fecha_expiracion,
            'activo' => $this->cup_activo,
        ]);

        // Reset
        $this->cup_codigo = '';
        $this->cup_valor_descuento = 0.00;
        $this->cup_monto_minimo_compra = 0.00;
        $this->cup_usos_totales = 1;

        Flux::toast(variant: 'success', text: __('Cupón de descuento creado exitosamente.'));
    }

    /**
     * Delete coupon.
     */
    public function eliminarCupon(int $id): void
    {
        $cupon = Cupon::findOrFail($id);
        $cupon->delete();
        Flux::toast(variant: 'success', text: __('Cupón eliminado.'));
    }

    /**
     * Create discount campaign.
     */
    public function crearDescuento(): void
    {
        $this->validate([
            'desc_nombre' => 'required|string|max:100',
            'desc_porcentaje' => 'required|integer|min:1|max:100',
            'desc_fecha_inicio' => 'nullable|date',
            'desc_fecha_fin' => 'nullable|date|after_or_equal:desc_fecha_inicio',
            'desc_activo' => 'required|boolean',
        ]);

        Descuento::create([
            'nombre' => $this->desc_nombre,
            'porcentaje_descuento' => $this->desc_porcentaje,
            'fecha_inicio' => $this->desc_fecha_inicio,
            'fecha_fin' => $this->desc_fecha_fin,
            'activo' => $this->desc_activo,
        ]);

        // Reset
        $this->desc_nombre = '';
        $this->desc_porcentaje = 0;

        Flux::toast(variant: 'success', text: __('Campaña de descuento creada.'));
    }

    /**
     * Delete discount campaign.
     */
    public function eliminarDescuento(int $id): void
    {
        $desc = Descuento::findOrFail($id);
        $desc->delete();
        Flux::toast(variant: 'success', text: __('Campaña eliminada.'));
    }

    /**
     * Computed active coupons.
     */
    #[Computed]
    public function cupones()
    {
        return Cupon::orderBy('created_at', 'desc')->get();
    }

    /**
     * Computed active discount campaigns.
     */
    #[Computed]
    public function descuentos()
    {
        return Descuento::orderBy('created_at', 'desc')->get();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div>
        <flux:heading size="xl">{{ __('Promociones y Descuentos') }}</flux:heading>
        <flux:subheading>{{ __('Administra los cupones y las campañas de descuento de productos para la tienda.') }}</flux:subheading>
    </div>

    <!-- Pestañas (Tabs) -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-700">
        <button wire:click.prevent="$set('activeTab', 'cupones')" class="px-6 py-3 font-semibold text-sm border-b-2 transition-colors {{ $activeTab === 'cupones' ? 'border-black text-black dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
            {{ __('Cupones de Descuento') }}
        </button>
        <button wire:click.prevent="$set('activeTab', 'descuentos')" class="px-6 py-3 font-semibold text-sm border-b-2 transition-colors {{ $activeTab === 'descuentos' ? 'border-black text-black dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">
            {{ __('Campañas de Descuento') }}
        </button>
    </div>

    <!-- Pestaña Cupones -->
    @if($activeTab === 'cupones')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulario Crear Cupón -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ __('Nuevo Cupón') }}</flux:heading>
                
                <form wire:submit.prevent="crearCupon" class="space-y-4">
                    <!-- Código -->
                    <flux:input wire:model="cup_codigo" :label="__('Código del Cupón')" placeholder="Ej. MOCHILA10" required />

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Tipo de Descuento -->
                        <flux:select wire:model="cup_tipo_descuento" :label="__('Tipo')">
                            <flux:select.option value="porcentaje">{{ __('Porcentaje') }}</flux:select.option>
                            <flux:select.option value="fijo">{{ __('Monto Fijo') }}</flux:select.option>
                        </flux:select>

                        <!-- Valor del Descuento -->
                        <flux:input wire:model="cup_valor_descuento" type="number" step="0.01" :label="__('Valor')" placeholder="Ej. 10" required />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Usos Totales -->
                        <flux:input wire:model="cup_usos_totales" type="number" min="1" :label="__('Límite de Usos')" required />

                        <!-- Compra Mínima -->
                        <flux:input wire:model="cup_monto_minimo_compra" type="number" step="0.01" :label="__('Mín. Compra')" placeholder="S/ 0.00" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Fecha Inicio -->
                        <flux:input wire:model="cup_fecha_inicio" type="date" :label="__('Fecha Inicio')" />

                        <!-- Fecha Expiración -->
                        <flux:input wire:model="cup_fecha_expiracion" type="date" :label="__('Fecha Fin')" />
                    </div>

                    <!-- Activo -->
                    <flux:checkbox wire:model="cup_activo" :label="__('Cupón activo para su uso')" />

                    <flux:button variant="primary" type="submit" class="w-full" icon="plus">
                        {{ __('Crear Cupón') }}
                    </flux:button>
                </form>
            </div>

            <!-- Tabla de Cupones -->
            <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
                <flux:heading size="lg">{{ __('Cupones Emitidos') }}</flux:heading>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                                <th class="p-3">{{ __('Código') }}</th>
                                <th class="p-3">{{ __('Descuento') }}</th>
                                <th class="p-3 text-center">{{ __('Mín. Compra') }}</th>
                                <th class="p-3 text-center">{{ __('Usos Restantes') }}</th>
                                <th class="p-3 text-center">{{ __('Expiración') }}</th>
                                <th class="p-3 text-center">{{ __('Estado') }}</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse($this->cupones as $cup)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="p-3 font-bold text-zinc-900 dark:text-white">
                                        {{ $cup->codigo }}
                                    </td>
                                    <td class="p-3">
                                        @if($cup->tipo_descuento === 'porcentaje')
                                            {{ $cup->valor_descuento }}%
                                        @else
                                            S/ {{ number_format($cup->valor_descuento, 2) }}
                                        @endif
                                    </td>
                                    <td class="p-3 text-center">
                                        S/ {{ number_format($cup->monto_minimo_compra, 2) }}
                                    </td>
                                    <td class="p-3 text-center">
                                        {{ $cup->usos_restantes }} / {{ $cup->usos_totales }}
                                    </td>
                                    <td class="p-3 text-center text-zinc-500">
                                        {{ $cup->fecha_expiracion ? $cup->fecha_expiracion->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="p-3 text-center">
                                        @if($cup->activo && $cup->usos_restantes > 0)
                                            <flux:badge color="success">{{ __('Vigente') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right">
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarCupon({{ $cup->id }})" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-8 text-zinc-400">
                                        {{ __('No hay cupones creados.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Pestaña Campañas de Descuento -->
    @if($activeTab === 'descuentos')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Formulario Crear Descuento -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-6 shadow-sm h-fit">
                <flux:heading size="lg">{{ __('Nueva Campaña') }}</flux:heading>
                
                <form wire:submit.prevent="crearDescuento" class="space-y-4">
                    <!-- Nombre -->
                    <flux:input wire:model="desc_nombre" :label="__('Nombre de Campaña')" placeholder="Ej. Campaña Escolar 2026" required />

                    <!-- Porcentaje de Descuento -->
                    <flux:input wire:model="desc_porcentaje" type="number" min="1" max="100" :label="__('Porcentaje de Descuento (%)')" placeholder="Ej. 15" required />

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Fecha Inicio -->
                        <flux:input wire:model="desc_fecha_inicio" type="date" :label="__('Fecha Inicio')" />

                        <!-- Fecha Fin -->
                        <flux:input wire:model="desc_fecha_fin" type="date" :label="__('Fecha Fin')" />
                    </div>

                    <!-- Activo -->
                    <flux:checkbox wire:model="desc_activo" :label="__('Campaña activa')" />

                    <flux:button variant="primary" type="submit" class="w-full" icon="plus">
                        {{ __('Crear Descuento') }}
                    </flux:button>
                </form>
            </div>

            <!-- Tabla de Campañas -->
            <div class="lg:col-span-2 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 space-y-4 shadow-sm">
                <flux:heading size="lg">{{ __('Campañas Registradas') }}</flux:heading>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                                <th class="p-3">{{ __('Campaña / Promoción') }}</th>
                                <th class="p-3 text-center">{{ __('Porcentaje') }}</th>
                                <th class="p-3 text-center">{{ __('Fecha Inicio') }}</th>
                                <th class="p-3 text-center">{{ __('Fecha Fin') }}</th>
                                <th class="p-3 text-center">{{ __('Estado') }}</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse($this->descuentos as $desc)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="p-3 font-semibold text-zinc-900 dark:text-white">
                                        {{ $desc->nombre }}
                                    </td>
                                    <td class="p-3 text-center font-bold text-emerald-600 dark:text-emerald-400">
                                        -{{ $desc->porcentaje_descuento }}%
                                    </td>
                                    <td class="p-3 text-center text-zinc-500">
                                        {{ $desc->fecha_inicio ? $desc->fecha_inicio->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="p-3 text-center text-zinc-500">
                                        {{ $desc->fecha_fin ? $desc->fecha_fin->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="p-3 text-center">
                                        @if($desc->activo)
                                            <flux:badge color="success">{{ __('Activa') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc">{{ __('Inactiva') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right">
                                        <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminarDescuento({{ $desc->id }})" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-zinc-400">
                                        {{ __('No hay campañas de descuento registradas.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
