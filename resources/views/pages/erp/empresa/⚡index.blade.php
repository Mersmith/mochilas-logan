<?php

use App\Models\Almacen;
use App\Models\EmpresaConfiguracion;
use App\Models\TipoDocumento;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Configuración de la Empresa')] class extends Component {
    public ?EmpresaConfiguracion $empresa = null;

    public string $nombre_comercial = '';
    public string $razon_social = '';
    public string $ruc = '';
    public string $direccion_fiscal = '';
    public string $telefono_contacto = '';
    public string $whatsapp_pedidos = '';
    public string $email_contacto = '';
    public string $horario_atencion = '';
    public string $facebook_url = '';
    public string $instagram_url = '';
    public string $tiktok_url = '';
    public ?int $almacen_web_id = null;
    public ?int $tipo_documento_web_id = null;
    public float $costo_envio_fijo = 9.90;

    public function mount(): void
    {
        $this->empresa = EmpresaConfiguracion::getEmpresa();

        $this->nombre_comercial = $this->empresa->nombre_comercial ?? '';
        $this->razon_social = $this->empresa->razon_social ?? '';
        $this->ruc = $this->empresa->ruc ?? '';
        $this->direccion_fiscal = $this->empresa->direccion_fiscal ?? '';
        $this->telefono_contacto = $this->empresa->telefono_contacto ?? '';
        $this->whatsapp_pedidos = $this->empresa->whatsapp_pedidos ?? '';
        $this->email_contacto = $this->empresa->email_contacto ?? '';
        $this->horario_atencion = $this->empresa->horario_atencion ?? '';
        $this->facebook_url = $this->empresa->facebook_url ?? '';
        $this->instagram_url = $this->empresa->instagram_url ?? '';
        $this->tiktok_url = $this->empresa->tiktok_url ?? '';
        $this->almacen_web_id = $this->empresa->almacen_web_id;
        $this->tipo_documento_web_id = $this->empresa->tipo_documento_web_id;
        $this->costo_envio_fijo = (float) ($this->empresa->costo_envio_fijo ?? 9.90);
    }

    #[Computed]
    public function almacenes()
    {
        return Almacen::where('activo', true)->get();
    }

    #[Computed]
    public function tiposDocumento()
    {
        return TipoDocumento::where('activo', true)->get();
    }

    public function guardar(): void
    {
        if (! auth()->user()->can('panel.acceder')) {
            abort(403);
        }

        $this->validate([
            'nombre_comercial' => 'required|string|max:255',
            'razon_social' => 'nullable|string|max:255',
            'ruc' => 'nullable|string|max:20',
            'direccion_fiscal' => 'nullable|string|max:255',
            'telefono_contacto' => 'nullable|string|max:50',
            'whatsapp_pedidos' => 'required|string|max:50',
            'email_contacto' => 'nullable|email|max:255',
            'horario_atencion' => 'nullable|string|max:255',
            'facebook_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'tiktok_url' => 'nullable|url|max:255',
            'almacen_web_id' => 'nullable|exists:almacens,id',
            'tipo_documento_web_id' => 'nullable|exists:tipo_documentos,id',
            'costo_envio_fijo' => 'required|numeric|min:0',
        ]);

        $this->empresa->update([
            'nombre_comercial' => $this->nombre_comercial,
            'razon_social' => $this->razon_social,
            'ruc' => $this->ruc,
            'direccion_fiscal' => $this->direccion_fiscal,
            'telefono_contacto' => $this->telefono_contacto,
            'whatsapp_pedidos' => $this->whatsapp_pedidos,
            'email_contacto' => $this->email_contacto,
            'horario_atencion' => $this->horario_atencion,
            'facebook_url' => $this->facebook_url,
            'instagram_url' => $this->instagram_url,
            'tiktok_url' => $this->tiktok_url,
            'almacen_web_id' => $this->almacen_web_id,
            'tipo_documento_web_id' => $this->tipo_documento_web_id,
            'costo_envio_fijo' => $this->costo_envio_fijo,
        ]);

        Flux::toast(variant: 'success', text: __('Configuración de la empresa guardada correctamente.'));
    }
}; ?>

<div class="space-y-6 max-w-5xl mx-auto pb-12">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Información y Parámetros de la Empresa') }}</flux:heading>
            <flux:subheading>{{ __('Administra los datos comerciales, números de atención por WhatsApp y configuración de ventas del sistema.') }}</flux:subheading>
        </div>
        <div>
            <flux:button variant="primary" icon="check" wire:click="guardar" class="w-full md:w-auto">
                {{ __('Guardar Cambios') }}
            </flux:button>
        </div>
    </div>

    <form wire:submit.prevent="guardar" class="space-y-6">
        <!-- Tarjeta 1: Datos Fiscales y Legales -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <flux:icon name="building-office-2" class="size-6 text-zinc-600 dark:text-zinc-400" />
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Datos Fiscales y Comerciales') }}</h3>
                    <p class="text-xs text-zinc-500">{{ __('Información visible para comprobantes y membretes.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>{{ __('Nombre Comercial') }}</flux:label>
                    <flux:input wire:model="nombre_comercial" placeholder="Ej: Mochilas Logan" required />
                    <flux:error name="nombre_comercial" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Razón Social') }}</flux:label>
                    <flux:input wire:model="razon_social" placeholder="Ej: Mochilas Logan S.A.C." />
                    <flux:error name="razon_social" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('RUC') }}</flux:label>
                    <flux:input wire:model="ruc" placeholder="Ej: 20600000000" maxlength="20" />
                    <flux:error name="ruc" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Dirección Fiscal') }}</flux:label>
                    <flux:input wire:model="direccion_fiscal" placeholder="Ej: Av. Principal 123, Lima, Perú" />
                    <flux:error name="direccion_fiscal" />
                </flux:field>
            </div>
        </div>

        <!-- Tarjeta 2: Canales de Atención y WhatsApp -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <flux:icon name="chat-bubble-left-right" class="size-6 text-emerald-600" />
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Atención al Cliente y Pedidos') }}</h3>
                    <p class="text-xs text-zinc-500">{{ __('Configura el número al que se derivan las compras por WhatsApp.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label class="flex items-center gap-1.5">
                        <span class="font-bold text-emerald-600">WhatsApp Oficial de Pedidos (E-commerce)</span>
                    </flux:label>
                    <flux:input wire:model="whatsapp_pedidos" placeholder="Ej: 51916647675 (Código de país + número)" required />
                    <flux:description class="text-xxs text-zinc-500">{{ __('Formato internacional sin signos ni espacios (ej: 51916647675 para Perú).') }}</flux:description>
                    <flux:error name="whatsapp_pedidos" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Teléfono Fijo / Móvil de Contacto') }}</flux:label>
                    <flux:input wire:model="telefono_contacto" placeholder="Ej: +51 916 647 675 / (01) 234-5678" />
                    <flux:error name="telefono_contacto" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Correo Electrónico de Contacto') }}</flux:label>
                    <flux:input wire:model="email_contacto" type="email" placeholder="ventas@mochilaslogan.com" />
                    <flux:error name="email_contacto" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Horario de Atención') }}</flux:label>
                    <flux:input wire:model="horario_atencion" placeholder="Ej: Lunes a Sábado: 9:00 AM - 7:00 PM" />
                    <flux:error name="horario_atencion" />
                </flux:field>
            </div>
        </div>

        <!-- Tarjeta 3: Parámetros del E-Commerce y Ventas Web -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <flux:icon name="globe-alt" class="size-6 text-blue-600" />
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Parámetros de Ventas Online (E-commerce)') }}</h3>
                    <p class="text-xs text-zinc-500">{{ __('Almacén y tipo de comprobante por defecto para compras en la tienda.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <flux:field>
                    <flux:label>{{ __('Almacén para Stock Web') }}</flux:label>
                    <flux:select wire:model="almacen_web_id" placeholder="Seleccionar almacén...">
                        <option value="">{{ __('Todos los almacenes activos') }}</option>
                        @foreach($this->almacenes as $alm)
                            <option value="{{ $alm->id }}">{{ $alm->nombre }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="almacen_web_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Comprobante por Defecto Web') }}</flux:label>
                    <flux:select wire:model="tipo_documento_web_id" placeholder="Seleccionar comprobante...">
                        <option value="">{{ __('Boleta de Venta (Por defecto)') }}</option>
                        @foreach($this->tiposDocumento as $td)
                            <option value="{{ $td->id }}">{{ $td->nombre }} ({{ $td->codigo_sunat }})</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="tipo_documento_web_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Costo de Envío Estándar (S/)') }}</flux:label>
                    <flux:input wire:model="costo_envio_fijo" type="number" step="0.10" min="0" required />
                    <flux:error name="costo_envio_fijo" />
                </flux:field>
            </div>
        </div>

        <!-- Tarjeta 4: Redes Sociales -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm space-y-6">
            <div class="flex items-center gap-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <flux:icon name="share" class="size-6 text-purple-600" />
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Redes Sociales') }}</h3>
                    <p class="text-xs text-zinc-500">{{ __('Enlaces a perfiles oficiales mostrados en la tienda.') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <flux:field>
                    <flux:label>{{ __('Facebook URL') }}</flux:label>
                    <flux:input wire:model="facebook_url" placeholder="https://facebook.com/mochilaslogan" />
                    <flux:error name="facebook_url" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Instagram URL') }}</flux:label>
                    <flux:input wire:model="instagram_url" placeholder="https://instagram.com/mochilaslogan" />
                    <flux:error name="instagram_url" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('TikTok URL') }}</flux:label>
                    <flux:input wire:model="tiktok_url" placeholder="https://tiktok.com/@mochilaslogan" />
                    <flux:error name="tiktok_url" />
                </flux:field>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4">
            <flux:button variant="primary" icon="check" type="submit">
                {{ __('Guardar Cambios') }}
            </flux:button>
        </div>
    </form>
</div>
