<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\DatoReembolso;
use Illuminate\Support\Facades\Auth;
use Flux\Flux;

new #[Title('Datos para reembolso')] #[Layout('layouts.settings')] class extends Component {
    public ?DatoReembolso $reembolso = null;
    
    // Modal state
    public bool $showModal = false;

    // Form fields
    public string $banco = '';
    public string $tipo_cuenta = '';
    public string $cci = '';
    public string $cci_confirmation = '';

    public function mount()
    {
        $this->loadReembolso();
    }

    public function loadReembolso()
    {
        $cliente = Auth::user()->cliente;
        if ($cliente) {
            $this->reembolso = $cliente->datoReembolso;
        }
    }

    public function openModal()
    {
        if ($this->reembolso) {
            $this->banco = $this->reembolso->banco;
            $this->tipo_cuenta = $this->reembolso->tipo_cuenta;
            $this->cci = $this->reembolso->cci;
            $this->cci_confirmation = $this->reembolso->cci;
        } else {
            $this->reset(['banco', 'tipo_cuenta', 'cci', 'cci_confirmation']);
        }
        
        $this->resetValidation();
        $this->showModal = true;
    }

    public function saveReembolso()
    {
        $this->validate([
            'banco' => 'required|string',
            'tipo_cuenta' => 'required|string',
            'cci' => 'required|string|min:15|max:30|confirmed',
        ], [
            'cci.confirmed' => 'Los números CCI no coinciden.',
        ]);

        $cliente = Auth::user()->cliente;
        
        if ($this->reembolso) {
            $this->reembolso->update([
                'banco' => $this->banco,
                'tipo_cuenta' => $this->tipo_cuenta,
                'cci' => $this->cci,
            ]);
            Flux::toast('Datos de reembolso actualizados.', variant: 'success');
        } else {
            $cliente->datoReembolso()->create([
                'banco' => $this->banco,
                'tipo_cuenta' => $this->tipo_cuenta,
                'cci' => $this->cci,
            ]);
            Flux::toast('Datos de reembolso guardados.', variant: 'success');
        }

        $this->showModal = false;
        $this->loadReembolso();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Datos para reembolso') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Datos para reembolso')" :subheading="__('Puedes editar en cualquier momento los datos de tu cuenta bancaria ingresada para futuros reembolsos.')">
        
        <div class="mb-6 flex justify-end">
            <flux:button variant="primary" wire:click="openModal">
                {{ $reembolso ? 'Editar datos' : 'Completar datos' }}
            </flux:button>
        </div>

        @if($reembolso)
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl divide-y divide-zinc-200 dark:divide-zinc-700">
                <div class="p-6">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-1">Banco</p>
                    <p class="text-base text-zinc-900 dark:text-white">{{ $reembolso->banco }}</p>
                </div>
                <div class="p-6">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-1">Tipo de cuenta</p>
                    <p class="text-base text-zinc-900 dark:text-white">{{ $reembolso->tipo_cuenta }}</p>
                </div>
                <div class="p-6">
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400 mb-1">CCI (Código de Cuenta Interbancario)</p>
                    <p class="text-base text-zinc-900 dark:text-white">{{ $reembolso->cci }}</p>
                </div>
            </div>
        @else
            <div class="bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-xl p-8 text-center text-zinc-500">
                Aún no has registrado datos para tus reembolsos.
            </div>
        @endif

    </x-pages::settings.layout>

    <!-- Modal para Completar/Editar Datos -->
    <flux:modal wire:model="showModal" class="md:w-[500px]">
        <div class="space-y-6">
            <div class="flex items-center gap-3">
                <flux:heading size="lg">Completar datos</flux:heading>
            </div>

            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Para futuros reembolsos, ingresa una cuenta bancaria asociada al DNI o carnet de extranjería que utilizaste al crear tu cuenta.
            </p>

            <form wire:submit="saveReembolso" class="space-y-6">
                
                <flux:select wire:model="banco" label="Banco" required>
                    <option value="">Selecciona una opción</option>
                    <option value="Banco de Crédito del Perú (BCP)">Banco de Crédito del Perú (BCP)</option>
                    <option value="BBVA">BBVA</option>
                    <option value="Interbank">Interbank</option>
                    <option value="Scotiabank">Scotiabank</option>
                    <option value="Banco de la Nación">Banco de la Nación</option>
                    <option value="Pichincha">Pichincha</option>
                    <option value="BanBif">BanBif</option>
                    <option value="Caja Arequipa">Caja Arequipa</option>
                    <option value="Caja Huancayo">Caja Huancayo</option>
                </flux:select>

                <flux:select wire:model="tipo_cuenta" label="Tipo de cuenta" required>
                    <option value="">Selecciona una opción</option>
                    <option value="Cuenta Ahorros">Cuenta Ahorros</option>
                    <option value="Cuenta Corriente">Cuenta Corriente</option>
                    <option value="Cuenta Sueldo">Cuenta Sueldo</option>
                </flux:select>

                <flux:input wire:model="cci" label="CCI (Código de Cuenta Interbancario)" placeholder="Ingresa un CCI" required />
                <flux:input wire:model="cci_confirmation" label="Confirma tu CCI" placeholder="Ingresa un CCI" required />

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button variant="ghost" wire:click="$set('showModal', false)">Cancelar</flux:button>
                    <flux:button type="submit" variant="primary">Guardar</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</section>
