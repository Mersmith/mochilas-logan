<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\MedioPago;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new #[Title('Medios de Pago')] #[Layout('layouts.settings')] class extends Component {
    public $tarjetas = [];
    public $otrasOpciones = [];
    
    // Modal state
    public bool $showCardModal = false;

    // Form fields for card
    public string $numero_tarjeta = '';
    public string $expiracion = '';
    public string $cvv = '';
    
    public function mount()
    {
        $this->loadMediosPago();
    }

    public function loadMediosPago()
    {
        $cliente = Auth::user()->cliente;
        if ($cliente) {
            $medios = $cliente->mediosPagos()->get();
            
            // Tarjetas guardadas
            $this->tarjetas = $medios->where('tipo', 'tarjeta')->sortByDesc('es_predeterminado')->values();
            
            // Si el cliente no tiene Yape o PagoEfectivo creados, los inyectamos en memoria o los creamos.
            // Para el mockup, asumiremos que si no existen, los mostramos como opciones.
            $yape = $medios->where('tipo', 'yape')->first();
            $pagoEfectivo = $medios->where('tipo', 'pagoefectivo')->first();
            
            $this->otrasOpciones = [
                'yape' => $yape,
                'pagoefectivo' => $pagoEfectivo
            ];
        }
    }

    public function openCardModal()
    {
        $this->reset(['numero_tarjeta', 'expiracion', 'cvv']);
        $this->resetValidation();
        $this->showCardModal = true;
    }

    public function saveCard()
    {
        $this->validate([
            'numero_tarjeta' => 'required|string|min:15|max:19',
            'expiracion' => 'required|string|size:5|regex:/^(0[1-9]|1[0-2])\/\d{2}$/',
            'cvv' => 'required|string|min:3|max:4',
        ], [
            'expiracion.regex' => 'El formato debe ser MM/AA',
        ]);

        $cliente = Auth::user()->cliente;
        
        // Simulación: Obtener últimos 4 y deducir proveedor
        $ultimos_cuatro = substr(str_replace(' ', '', $this->numero_tarjeta), -4);
        $primer_digito = substr($this->numero_tarjeta, 0, 1);
        
        $proveedor = 'Visa'; // por defecto
        if ($primer_digito === '5') $proveedor = 'Mastercard';
        if ($primer_digito === '3') $proveedor = 'Amex';

        $isFirst = $cliente->mediosPagos()->count() === 0;

        $cliente->mediosPagos()->create([
            'tipo' => 'tarjeta',
            'proveedor' => $proveedor,
            'ultimos_cuatro' => $ultimos_cuatro,
            'fecha_expiracion' => $this->expiracion,
            'es_predeterminado' => $isFirst,
        ]);

        Flux::toast('Tarjeta guardada exitosamente.', variant: 'success');
        $this->showCardModal = false;
        $this->loadMediosPago();
    }

    public function setAsDefault($tipo, $id = null)
    {
        $cliente = Auth::user()->cliente;

        if ($id) {
            $medio = $cliente->mediosPagos()->find($id);
            if ($medio) {
                $medio->update(['es_predeterminado' => true]);
            }
        } else {
            // Es un Yape o PagoEfectivo que aún no existe en DB para este cliente
            $cliente->mediosPagos()->create([
                'tipo' => $tipo,
                'es_predeterminado' => true,
            ]);
        }
        
        Flux::toast('Medio de pago principal actualizado.', variant: 'success');
        $this->loadMediosPago();
    }

    public function deleteCard($id)
    {
        $cliente = Auth::user()->cliente;
        $medio = $cliente->mediosPagos()->find($id);
        
        if ($medio) {
            $wasDefault = $medio->es_predeterminado;
            $medio->delete();
            
            if ($wasDefault) {
                $next = $cliente->mediosPagos()->first();
                if ($next) {
                    $next->update(['es_predeterminado' => true]);
                }
            }
            
            Flux::toast('Tarjeta eliminada.', variant: 'success');
            $this->loadMediosPago();
        }
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Medios de pago') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Medios de pago')" :subheading="__('Administra tus tarjetas y métodos de pago')">
        
        <!-- Tarjetas guardadas -->
        <div class="mb-8">
            <div class="flex justify-between items-center mb-4">
                <flux:heading size="lg">Tarjetas guardadas</flux:heading>
                <flux:button variant="primary" wire:click="openCardModal" size="sm">Agregar tarjeta</flux:button>
            </div>

            @if(count($tarjetas) > 0)
                <div class="space-y-3">
                    @foreach($tarjetas as $tarjeta)
                        <div class="flex items-center justify-between p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                            <div class="flex items-center gap-4">
                                <div class="bg-zinc-100 dark:bg-zinc-800 p-2 rounded-md font-bold text-xs uppercase w-12 text-center text-zinc-600 dark:text-zinc-300">
                                    {{ $tarjeta->proveedor }}
                                </div>
                                <div>
                                    <p class="font-medium">Débito/Crédito **** {{ $tarjeta->ultimos_cuatro }}</p>
                                    @if($tarjeta->es_predeterminado)
                                        <flux:badge color="blue" size="sm" class="mt-1">Principal</flux:badge>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-4 text-sm">
                                @if(!$tarjeta->es_predeterminado)
                                    <button wire:click="setAsDefault('tarjeta', {{ $tarjeta->id }})" class="font-medium text-zinc-900 dark:text-white hover:underline">Guardar como principal</button>
                                @endif
                                <button wire:click="deleteCard({{ $tarjeta->id }})" class="font-medium text-red-600 hover:underline">Eliminar</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 text-center text-zinc-500">
                    Aún no tienes tarjetas guardadas.
                </div>
            @endif
        </div>

        <!-- Otras opciones -->
        <div>
            <flux:heading size="lg" class="mb-4">Otras opciones</flux:heading>
            
            <div class="space-y-3">
                <!-- Yape -->
                <div class="flex items-center justify-between p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                    <div class="flex items-center gap-4">
                        <div class="bg-[#742384] text-white p-2 rounded-md font-bold text-xs w-12 text-center">YAPE</div>
                        <div>
                            <p class="font-medium">Yape</p>
                            @if(isset($otrasOpciones['yape']) && $otrasOpciones['yape']->es_predeterminado)
                                <flux:badge color="blue" size="sm" class="mt-1">Principal</flux:badge>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        @if(!isset($otrasOpciones['yape']) || !$otrasOpciones['yape']->es_predeterminado)
                            <button wire:click="setAsDefault('yape', {{ $otrasOpciones['yape']->id ?? 'null' }})" class="font-medium text-zinc-900 dark:text-white hover:underline">Guardar como principal</button>
                        @endif
                    </div>
                </div>

                <!-- PagoEfectivo -->
                <div class="flex items-center justify-between p-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                    <div class="flex items-center gap-4">
                        <div class="bg-[#FFB700] text-black p-2 rounded-md font-bold text-xs w-12 text-center">PE</div>
                        <div>
                            <p class="font-medium">PagoEfectivo o QR Agencias y Billeteras</p>
                            @if(isset($otrasOpciones['pagoefectivo']) && $otrasOpciones['pagoefectivo']->es_predeterminado)
                                <flux:badge color="blue" size="sm" class="mt-1">Principal</flux:badge>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        @if(!isset($otrasOpciones['pagoefectivo']) || !$otrasOpciones['pagoefectivo']->es_predeterminado)
                            <button wire:click="setAsDefault('pagoefectivo', {{ $otrasOpciones['pagoefectivo']->id ?? 'null' }})" class="font-medium text-zinc-900 dark:text-white hover:underline">Guardar como principal</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </x-pages::settings.layout>

    <!-- Modal para Agregar Tarjeta -->
    <flux:modal wire:model="showCardModal" class="md:w-[500px]">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:icon.credit-card class="size-6 text-zinc-500" />
                <flux:heading size="lg">Tarjeta de débito o crédito</flux:heading>
            </div>

            <form wire:submit="saveCard" class="space-y-6">
                <flux:input wire:model="numero_tarjeta" label="Número de tarjeta" placeholder="XXXX XXXX XXXX XXXX" required />

                <div class="grid grid-cols-2 gap-6">
                    <flux:input wire:model="expiracion" label="Expiración" placeholder="MM/AA" required />
                    <flux:input wire:model="cvv" type="password" label="Código de seguridad" placeholder="CVV" required />
                </div>

                <p class="text-xs text-zinc-500">Para validar tu tarjeta, es posible que se haga un cargo que luego será reversado.</p>

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button variant="ghost" wire:click="$set('showCardModal', false)">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary">Agregar</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
