<?php

use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\Sede;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Title('Control de Cajas y Turnos')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filtro_sede = 'todas';

    #[Url]
    public string $filtro_estado = 'todos';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    #[Url]
    public int $perPage = 15;

    // Modal Crear Caja Física
    public bool $showNuevaCajaModal = false;
    public ?int $nueva_caja_sede_id = null;
    public string $nueva_caja_nombre = '';

    // Modal Apertura de Turno
    public bool $showAperturaModal = false;
    public ?int $apertura_sede_id = null;
    public ?int $apertura_caja_id = null;
    public ?int $apertura_user_id = null;
    public float $apertura_monto_inicial = 100.00;
    public string $apertura_observaciones = '';

    // Modal Movimiento de Caja Chica
    public bool $showMovimientoModal = false;
    public ?int $movimiento_sesion_id = null;
    public string $movimiento_tipo = 'egreso';
    public float $movimiento_monto = 0.00;
    public string $movimiento_motivo = '';
    public string $movimiento_comprobante = '';

    // Modal Cierre y Arqueo
    public bool $showCierreModal = false;
    public ?int $cierre_sesion_id = null;
    public ?CajaSesion $sesionEnCierre = null;
    public ?float $cierre_monto_efectivo = null;
    public string $cierre_observaciones = '';

    public function mount(): void
    {
        $primeraSede = Sede::where('activo', true)->first();
        if ($primeraSede) {
            $this->nueva_caja_sede_id = $primeraSede->id;
            $this->apertura_sede_id = $primeraSede->id;
        }

        $this->apertura_user_id = Auth::id();
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'filtro_sede', 'filtro_estado', 'desde', 'hasta', 'perPage'])) {
            $this->resetPage();
        }
    }

    public function resetFiltros(): void
    {
        $this->reset(['search', 'filtro_sede', 'filtro_estado', 'desde', 'hasta']);
        $this->perPage = 15;
        $this->resetPage();
    }

    // ─── Modales y Operaciones ───────────────────────────────────────────────

    public function abrirModalNuevaCaja(): void
    {
        $this->nueva_caja_nombre = '';
        $this->showNuevaCajaModal = true;
    }

    public function guardarNuevaCaja(): void
    {
        $this->validate([
            'nueva_caja_sede_id' => 'required|exists:sedes,id',
            'nueva_caja_nombre' => 'required|string|max:100',
        ]);

        Caja::create([
            'sede_id' => $this->nueva_caja_sede_id,
            'nombre' => $this->nueva_caja_nombre,
            'activo' => true,
        ]);

        $this->showNuevaCajaModal = false;
        Flux::toast(variant: 'success', text: __('Caja física creada correctamente.'));
    }

    public function abrirModalApertura(?int $cajaId = null): void
    {
        if ($cajaId) {
            $caja = Caja::findOrFail($cajaId);
            $this->apertura_sede_id = $caja->sede_id;
            $this->apertura_caja_id = $caja->id;
        } else {
            $this->apertura_caja_id = $this->cajasDisponiblesParaApertura->first()?->id;
        }

        $this->apertura_user_id = Auth::id();
        $this->apertura_monto_inicial = 100.00;
        $this->apertura_observaciones = '';
        $this->showAperturaModal = true;
    }

    public function updatedAperturaSedeId(): void
    {
        $this->apertura_caja_id = $this->cajasDisponiblesParaApertura->first()?->id;
    }

    public function guardarApertura(): void
    {
        $this->validate([
            'apertura_caja_id' => 'required|exists:cajas,id',
            'apertura_user_id' => 'required|exists:users,id',
            'apertura_monto_inicial' => 'required|numeric|min:0',
        ]);

        // Verificar si la caja ya tiene una sesión abierta
        $cajaExistente = CajaSesion::where('caja_id', $this->apertura_caja_id)
            ->where('estado', 'abierta')
            ->first();

        if ($cajaExistente) {
            Flux::toast(variant: 'danger', text: __('Esta caja ya tiene una sesión abierta activa.'));
            return;
        }

        CajaSesion::create([
            'caja_id' => $this->apertura_caja_id,
            'user_id' => $this->apertura_user_id,
            'monto_inicial' => $this->apertura_monto_inicial,
            'fecha_apertura' => now(),
            'estado' => 'abierta',
            'observaciones_apertura' => $this->apertura_observaciones,
        ]);

        $this->showAperturaModal = false;
        Flux::toast(variant: 'success', text: __('Caja abierta con éxito. ¡Buen turno de venta!'));
    }

    public function abrirModalMovimiento(int $sesionId): void
    {
        $this->movimiento_sesion_id = $sesionId;
        $this->movimiento_tipo = 'egreso';
        $this->movimiento_monto = 0.00;
        $this->movimiento_motivo = '';
        $this->movimiento_comprobante = '';
        $this->showMovimientoModal = true;
    }

    public function guardarMovimiento(): void
    {
        $this->validate([
            'movimiento_sesion_id' => 'required|exists:caja_sesions,id',
            'movimiento_tipo' => 'required|in:ingreso,egreso',
            'movimiento_monto' => 'required|numeric|min:0.01',
            'movimiento_motivo' => 'required|string|max:255',
        ]);

        $sesion = CajaSesion::findOrFail($this->movimiento_sesion_id);

        if ($sesion->estado !== 'abierta') {
            Flux::toast(variant: 'danger', text: __('No se pueden registrar movimientos en una caja cerrada.'));
            return;
        }

        CajaMovimiento::create([
            'caja_sesion_id' => $sesion->id,
            'user_id' => Auth::id(),
            'tipo' => $this->movimiento_tipo,
            'monto' => $this->movimiento_monto,
            'motivo' => $this->movimiento_motivo,
            'comprobante' => $this->movimiento_comprobante,
        ]);

        $sesion->recalcularTotales();

        $this->showMovimientoModal = false;
        Flux::toast(variant: 'success', text: __('Movimiento de caja chica registrado con éxito.'));
    }

    public function abrirModalCierre(int $sesionId): void
    {
        $this->cierre_sesion_id = $sesionId;
        $this->sesionEnCierre = CajaSesion::with(['caja.sede', 'user', 'ventas', 'movimientos'])->findOrFail($sesionId);
        $this->sesionEnCierre->recalcularTotales();

        $this->cierre_monto_efectivo = $this->sesionEnCierre->monto_cierre_esperado;
        $this->cierre_observaciones = '';
        $this->showCierreModal = true;
    }

    public function guardarCierre(): void
    {
        $this->validate([
            'cierre_monto_efectivo' => 'required|numeric|min:0',
        ]);

        $sesion = CajaSesion::findOrFail($this->cierre_sesion_id);

        if ($sesion->estado !== 'abierta') {
            Flux::toast(variant: 'danger', text: __('Esta caja ya fue cerrada anteriormente.'));
            return;
        }

        $sesion->recalcularTotales();

        $montoContado = (float) $this->cierre_monto_efectivo;
        $montoEsperado = (float) $sesion->monto_cierre_esperado;
        $diferencia = round($montoContado - $montoEsperado, 2);

        $sesion->update([
            'monto_cierre_efectivo' => $montoContado,
            'diferencia_efectivo' => $diferencia,
            'fecha_cierre' => now(),
            'estado' => 'cerrada',
            'observaciones_cierre' => $this->cierre_observaciones,
        ]);

        $this->showCierreModal = false;
        Flux::toast(variant: 'success', text: __('Cierre de caja completado y arqueo registrado.'));
    }

    public function descargarReportePdf(int $sesionId): StreamedResponse
    {
        $sesion = CajaSesion::with(['caja.sede', 'user', 'ventas', 'movimientos'])->findOrFail($sesionId);
        $sesion->recalcularTotales();

        $pdf = Pdf::loadView('pdf.cierre-caja', compact('sesion'));
        $pdf->setPaper('A4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'cierre-caja-turno-' . $sesion->id . '.pdf');
    }

    // ─── Computed Properties ─────────────────────────────────────────────────

    #[Computed]
    public function sedes()
    {
        return Sede::where('activo', true)->orderBy('nombre')->get();
    }

    #[Computed]
    public function cajasActivas()
    {
        return Caja::with(['sede', 'sesionActiva.user', 'sesionActiva.ventas', 'sesionActiva.movimientos'])
            ->where('activo', true)
            ->when($this->filtro_sede !== 'todas', fn ($q) => $q->where('sede_id', $this->filtro_sede))
            ->orderBy('sede_id')
            ->orderBy('nombre')
            ->get();
    }

    #[Computed]
    public function cajasDisponiblesParaApertura()
    {
        if (! $this->apertura_sede_id) {
            return collect();
        }

        return Caja::where('sede_id', $this->apertura_sede_id)
            ->where('activo', true)
            ->whereDoesntHave('sesiones', fn ($q) => $q->where('estado', 'abierta'))
            ->orderBy('nombre')
            ->get();
    }

    #[Computed]
    public function cajeros()
    {
        return User::where('activo', true)->orderBy('name')->get();
    }

    #[Computed]
    public function historialSesiones()
    {
        return CajaSesion::with(['caja.sede', 'user'])
            ->when($this->search, function ($q) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%' . $this->search . '%'))
                    ->orWhereHas('caja', fn ($c) => $c->where('nombre', 'like', '%' . $this->search . '%'));
            })
            ->when($this->filtro_sede !== 'todas', function ($q) {
                $q->whereHas('caja', fn ($c) => $c->where('sede_id', $this->filtro_sede));
            })
            ->when($this->filtro_estado !== 'todos', fn ($q) => $q->where('estado', $this->filtro_estado))
            ->when($this->desde, fn ($q) => $q->whereDate('fecha_apertura', '>=', $this->desde))
            ->when($this->hasta, fn ($q) => $q->whereDate('fecha_apertura', '<=', $this->hasta))
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);
    }
}; ?>

<div class="space-y-6">
    <!-- Encabezado de Página -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Control de Cajas y Turnos') }}</flux:heading>
            <flux:subheading>{{ __('Apertura de turno, control de gastos de caja chica y arqueo de efectivo.') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="ghost" icon="plus" wire:click="abrirModalNuevaCaja">
                {{ __('Crear Caja Física') }}
            </flux:button>
            <flux:button variant="primary" icon="lock-open" wire:click="abrirModalApertura">
                {{ __('Abrir Turno de Caja') }}
            </flux:button>
        </div>
    </div>

    <!-- Sección 1: Cajas Físicas en Tiempo Real -->
    <div>
        <flux:heading size="lg" class="mb-4">{{ __('Estado de Cajas por Sede') }}</flux:heading>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($this->cajasActivas as $caja)
                @php
                    $sesion = $caja->sesionActiva;
                    if ($sesion) {
                        $sesion->recalcularTotales();
                    }
                @endphp
                <div class="bg-white dark:bg-zinc-900 border {{ $sesion ? 'border-emerald-500/50 dark:border-emerald-500/30' : 'border-zinc-200 dark:border-zinc-700' }} rounded-xl p-5 shadow-sm space-y-4 relative overflow-hidden">
                    @if($sesion)
                        <div class="absolute top-0 right-0 transform translate-x-4 -translate-y-4 w-16 h-16 bg-emerald-500/10 rounded-full blur-xl pointer-events-none"></div>
                    @endif

                    <!-- Header Tarjeta -->
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-xs font-semibold text-zinc-400 uppercase">{{ $caja->sede->nombre }}</div>
                            <div class="text-base font-bold text-zinc-900 dark:text-white">{{ $caja->nombre }}</div>
                        </div>
                        @if($sesion)
                            <flux:badge color="success" icon="lock-open">{{ __('Turno Abierto') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" icon="lock-closed">{{ __('Cerrada') }}</flux:badge>
                        @endif
                    </div>

                    <!-- Cuerpo Tarjeta -->
                    @if($sesion)
                        <div class="space-y-2 border-t border-b border-zinc-100 dark:border-zinc-800 py-3 text-xs">
                            <div class="flex justify-between">
                                <span class="text-zinc-500">{{ __('Cajero(a):') }}</span>
                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $sesion->user->name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-zinc-500">{{ __('Apertura:') }}</span>
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $sesion->fecha_apertura->format('d/m H:i') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-zinc-500">{{ __('Fondo Inicial:') }}</span>
                                <span class="font-medium text-zinc-700 dark:text-zinc-300">S/ {{ number_format($sesion->monto_inicial, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-zinc-500">{{ __('Ventas Efectivo:') }}</span>
                                <span class="font-medium text-emerald-600">+ S/ {{ number_format($sesion->total_ventas_efectivo, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-zinc-500">{{ __('Ventas Digitales (Yape/Tarj.):') }}</span>
                                <span class="font-medium text-blue-600">S/ {{ number_format($sesion->total_ventas_tarjeta + $sesion->total_ventas_transferencia + $sesion->total_ventas_yape_plin, 2) }}</span>
                            </div>
                            @if($sesion->total_egresos_gastos > 0)
                                <div class="flex justify-between text-rose-600">
                                    <span>{{ __('Gastos Caja Chica:') }}</span>
                                    <span>- S/ {{ number_format($sesion->total_egresos_gastos, 2) }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between pt-2 border-t border-zinc-100 dark:border-zinc-800 text-sm font-bold">
                                <span class="text-zinc-900 dark:text-white">{{ __('Efectivo Esperado:') }}</span>
                                <span class="text-emerald-600">S/ {{ number_format($sesion->monto_cierre_esperado, 2) }}</span>
                            </div>
                        </div>

                        <!-- Acciones Turno Abierto -->
                        <div class="flex items-center gap-2 pt-1">
                            <flux:button variant="ghost" size="sm" icon="plus" class="flex-1 text-xs" wire:click="abrirModalMovimiento({{ $sesion->id }})">
                                {{ __('Gasto / Ingreso') }}
                            </flux:button>
                            <flux:button variant="danger" size="sm" icon="lock-closed" class="flex-1 text-xs" wire:click="abrirModalCierre({{ $sesion->id }})">
                                {{ __('Arqueo y Cierre') }}
                            </flux:button>
                        </div>
                    @else
                        <div class="py-6 text-center text-xs text-zinc-400 space-y-2">
                            <flux:icon.lock-closed class="size-8 mx-auto text-zinc-300 dark:text-zinc-700" />
                            <p>{{ __('Caja lista para iniciar un nuevo turno de ventas.') }}</p>
                        </div>
                        <flux:button variant="primary" size="sm" class="w-full text-xs" wire:click="abrirModalApertura({{ $caja->id }})">
                            {{ __('Abrir esta Caja') }}
                        </flux:button>
                    @endif
                </div>
            @empty
                <div class="col-span-full text-center py-12 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                    <p class="text-zinc-400 text-sm">{{ __('No hay cajas físicas registradas. Haz clic en "Crear Caja Física" para empezar.') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Sección 2: Historial de Turnos y Cierres -->
    <div class="space-y-4 pt-4">
        <flux:heading size="lg">{{ __('Historial de Turnos y Arqueos Anteriores') }}</flux:heading>

        <!-- Filtros -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row flex-wrap gap-3">
                <div class="flex-1 min-w-[200px]">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Buscar por cajero o nombre de caja...') }}" />
                </div>
                
                <flux:select wire:model.live="filtro_sede" class="sm:w-44">
                    <option value="todas">{{ __('Todas las Sedes') }}</option>
                    @foreach($this->sedes as $s)
                        <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                    @endforeach
                </flux:select>
                
                <flux:select wire:model.live="filtro_estado" class="sm:w-44">
                    <option value="todos">{{ __('Todos los Estados') }}</option>
                    <option value="abierta">{{ __('Abierta') }}</option>
                    <option value="cerrada">{{ __('Cerrada') }}</option>
                </flux:select>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <flux:input wire:model.live="desde" type="date" label="{{ __('Desde') }}" class="w-full sm:w-40" />
                <flux:input wire:model.live="hasta" type="date" label="{{ __('Hasta') }}" class="w-full sm:w-40" />
                <flux:button size="sm" class="!bg-red-600 !text-white hover:!bg-red-700 border-none sm:mt-6" wire:click="resetFiltros" icon="arrow-path">
                    {{ __('Limpiar') }}
                </flux:button>
            </div>
        </div>

        <!-- Tabla Historial -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden flex flex-col">
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <th class="p-3 font-semibold text-zinc-700 dark:text-zinc-300"># Turno</th>
                            <th class="p-3 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Sede / Caja') }}</th>
                            <th class="p-3 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Cajero(a)') }}</th>
                            <th class="p-3 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Apertura') }}</th>
                            <th class="p-3 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Cierre') }}</th>
                            <th class="p-3 font-semibold text-zinc-700 dark:text-zinc-300 text-right">{{ __('Fondo') }}</th>
                            <th class="p-3 font-semibold text-zinc-700 dark:text-zinc-300 text-right">{{ __('Ventas Totales') }}</th>
                            <th class="p-3 font-semibold text-zinc-700 dark:text-zinc-300 text-right">{{ __('Esperado') }}</th>
                            <th class="p-3 font-semibold text-zinc-700 dark:text-zinc-300 text-right">{{ __('Contado') }}</th>
                            <th class="p-3 font-semibold text-zinc-700 dark:text-zinc-300 text-center">{{ __('Diferencia') }}</th>
                            <th class="p-3 font-semibold text-zinc-700 dark:text-zinc-300 text-center">{{ __('Estado') }}</th>
                            <th class="p-3 text-right">{{ __('Reporte') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->historialSesiones as $hSesion)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="p-3 font-bold text-zinc-900 dark:text-white">
                                    #{{ str_pad($hSesion->id, 5, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="p-3">
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $hSesion->caja->nombre }}</div>
                                    <div class="text-xxs text-zinc-500">{{ $hSesion->caja->sede->nombre }}</div>
                                </td>
                                <td class="p-3 text-zinc-700 dark:text-zinc-300">
                                    {{ $hSesion->user->name }}
                                </td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $hSesion->fecha_apertura->format('d/m/Y H:i') }}
                                </td>
                                <td class="p-3 text-zinc-600 dark:text-zinc-400">
                                    {{ $hSesion->fecha_cierre ? $hSesion->fecha_cierre->format('d/m/Y H:i') : '-' }}
                                </td>
                                <td class="p-3 text-right text-zinc-700 dark:text-zinc-300">
                                    S/ {{ number_format($hSesion->monto_inicial, 2) }}
                                </td>
                                <td class="p-3 text-right font-bold text-zinc-900 dark:text-white">
                                    S/ {{ number_format($hSesion->total_ventas, 2) }}
                                </td>
                                <td class="p-3 text-right text-zinc-700 dark:text-zinc-300">
                                    S/ {{ number_format($hSesion->monto_cierre_esperado, 2) }}
                                </td>
                                <td class="p-3 text-right font-bold text-zinc-900 dark:text-white">
                                    {{ $hSesion->monto_cierre_efectivo !== null ? 'S/ ' . number_format($hSesion->monto_cierre_efectivo, 2) : '-' }}
                                </td>
                                <td class="p-3 text-center">
                                    @if($hSesion->estado === 'cerrada')
                                        @php $dif = (float) $hSesion->diferencia_efectivo; @endphp
                                        @if($dif > 0)
                                            <span class="text-emerald-600 font-bold">+ S/ {{ number_format($dif, 2) }}</span>
                                        @elseif($dif < 0)
                                            <span class="text-rose-600 font-bold">- S/ {{ number_format(abs($dif), 2) }}</span>
                                        @else
                                            <span class="text-zinc-500 font-medium">S/ 0.00</span>
                                        @endif
                                    @else
                                        <span class="text-zinc-400 italic">{{ __('En curso') }}</span>
                                    @endif
                                </td>
                                <td class="p-3 text-center">
                                    @if($hSesion->estado === 'abierta')
                                        <flux:badge color="success" size="sm">{{ __('Abierta') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">{{ __('Cerrada') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="p-3 text-right space-x-1">
                                    <flux:button variant="ghost" icon="arrow-down-tray" size="xs" wire:click="descargarReportePdf({{ $hSesion->id }})" title="{{ __('Descargar Reporte PDF') }}" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-12 text-zinc-400">
                                    {{ __('No se encontraron registros de turnos de caja.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-700">
                {{ $this->historialSesiones->links() }}
            </div>
        </div>
    </div>

    <!-- MODAL 1: Crear Caja Física -->
    <flux:modal name="modal-nueva-caja" class="md:w-96 space-y-6" wire:model="showNuevaCajaModal">
        <div>
            <flux:heading size="lg">{{ __('Nueva Caja Física') }}</flux:heading>
            <flux:subheading>{{ __('Registra una caja para asociarla a una sede de la tienda.') }}</flux:subheading>
        </div>

        <form wire:submit.prevent="guardarNuevaCaja" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Sede') }}</flux:label>
                <flux:select wire:model="nueva_caja_sede_id">
                    @foreach($this->sedes as $s)
                        <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="nueva_caja_sede_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Nombre de la Caja') }}</flux:label>
                <flux:input wire:model="nueva_caja_nombre" placeholder="Ej. Caja 1 - Principal" required />
                <flux:error name="nueva_caja_nombre" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" type="button" wire:click="$set('showNuevaCajaModal', false)">
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Crear Caja') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- MODAL 2: Apertura de Caja (Turno) -->
    <flux:modal name="modal-apertura" class="md:w-96 space-y-6" wire:model="showAperturaModal">
        <div>
            <flux:heading size="lg">{{ __('Apertura de Caja / Turno') }}</flux:heading>
            <flux:subheading>{{ __('Inicia el turno asignando el fondo inicial de sencillo.') }}</flux:subheading>
        </div>

        <form wire:submit.prevent="guardarApertura" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Sede') }}</flux:label>
                <flux:select wire:model.live="apertura_sede_id">
                    @foreach($this->sedes as $s)
                        <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="apertura_sede_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Caja Física') }}</flux:label>
                <flux:select wire:model="apertura_caja_id">
                    @foreach($this->cajasDisponiblesParaApertura as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="apertura_caja_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Cajero(a) Responsable') }}</flux:label>
                <flux:select wire:model="apertura_user_id">
                    @foreach($this->cajeros as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="apertura_user_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Fondo Inicial de Sencillo (S/)') }}</flux:label>
                <flux:input type="number" step="0.01" min="0" wire:model="apertura_monto_inicial" required />
                <flux:error name="apertura_monto_inicial" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Observación Inicial (Opcional)') }}</flux:label>
                <flux:textarea wire:model="apertura_observaciones" rows="2" placeholder="Ej. Billetes y monedas de sencillo..." />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" type="button" wire:click="$set('showAperturaModal', false)">
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Abrir Caja') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- MODAL 3: Movimiento de Caja Chica (Gasto / Ingreso) -->
    <flux:modal name="modal-movimiento" class="md:w-96 space-y-6" wire:model="showMovimientoModal">
        <div>
            <flux:heading size="lg">{{ __('Movimiento de Caja Chica') }}</flux:heading>
            <flux:subheading>{{ __('Registra un gasto menor o una inyección de dinero extra.') }}</flux:subheading>
        </div>

        <form wire:submit.prevent="guardarMovimiento" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Tipo de Movimiento') }}</flux:label>
                <flux:select wire:model="movimiento_tipo">
                    <option value="egreso">{{ __('Gasto / Egreso de Caja Chica (-)') }}</option>
                    <option value="ingreso">{{ __('Ingreso Extra de Efectivo (+)') }}</option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Monto (S/)') }}</flux:label>
                <flux:input type="number" step="0.01" min="0.01" wire:model="movimiento_monto" required />
                <flux:error name="movimiento_monto" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Concepto / Motivo') }}</flux:label>
                <flux:input wire:model="movimiento_motivo" placeholder="Ej. Compra de bolsas, pasaje, delivery..." required />
                <flux:error name="movimiento_motivo" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Nro. Comprobante / Recibo (Opcional)') }}</flux:label>
                <flux:input wire:model="movimiento_comprobante" placeholder="Ej. Boleta B001-456 / Recibo" />
            </flux:field>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" type="button" wire:click="$set('showMovimientoModal', false)">
                    {{ __('Cancelar') }}
                </flux:button>
                <flux:button variant="primary" type="submit">
                    {{ __('Registrar Movimiento') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- MODAL 4: Arqueo y Cierre de Caja -->
    @if($showCierreModal && $sesionEnCierre)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/40 backdrop-blur-sm">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 w-full max-w-lg rounded-xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <form wire:submit.prevent="guardarCierre">
                    <div class="p-6 space-y-6">
                        <!-- Cabecera -->
                        <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-4">
                            <div>
                                <flux:heading size="lg">{{ __('Arqueo y Cierre de Turno') }}</flux:heading>
                                <flux:subheading>{{ $sesionEnCierre->caja->nombre }} — {{ $sesionEnCierre->caja->sede->nombre }}</flux:subheading>
                            </div>
                            <flux:button variant="ghost" icon="x-mark" wire:click="$set('showCierreModal', false)" />
                        </div>

                        <!-- Resumen Calculado por Sistema -->
                        <div class="bg-zinc-50 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 space-y-2 text-xs">
                            <div class="flex justify-between text-zinc-500">
                                <span>{{ __('(+) Fondo Inicial:') }}</span>
                                <span class="font-semibold text-zinc-900 dark:text-white">S/ {{ number_format($sesionEnCierre->monto_inicial, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-emerald-600">
                                <span>{{ __('(+) Ventas en Efectivo:') }}</span>
                                <span class="font-semibold">+ S/ {{ number_format($sesionEnCierre->total_ventas_efectivo, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-emerald-600">
                                <span>{{ __('(+) Ingresos Extras:') }}</span>
                                <span class="font-semibold">+ S/ {{ number_format($sesionEnCierre->total_ingresos_extra, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-rose-600">
                                <span>{{ __('(-) Gastos de Caja Chica:') }}</span>
                                <span class="font-semibold">- S/ {{ number_format($sesionEnCierre->total_egresos_gastos, 2) }}</span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-zinc-200 dark:border-zinc-700 text-sm font-bold">
                                <span class="text-zinc-900 dark:text-white">{{ __('(=) Total Efectivo Esperado:') }}</span>
                                <span class="text-emerald-600 font-black">S/ {{ number_format($sesionEnCierre->monto_cierre_esperado, 2) }}</span>
                            </div>
                        </div>

                        <!-- Conteo Físico Real -->
                        <flux:field>
                            <flux:label>{{ __('Efectivo Físico Contado en Gaveta (S/)') }}</flux:label>
                            <flux:input type="number" step="0.01" min="0" wire:model.live.debounce.200ms="cierre_monto_efectivo" class="text-lg font-bold" required />
                            <flux:error name="cierre_monto_efectivo" />
                        </flux:field>

                        <!-- Cálculo de Diferencia en tiempo real -->
                        @php
                            $contado = (float) $cierre_monto_efectivo;
                            $esperado = (float) $sesionEnCierre->monto_cierre_esperado;
                            $dif = round($contado - $esperado, 2);
                        @endphp
                        <div class="p-3 rounded-lg border {{ $dif == 0 ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800' : ($dif > 0 ? 'bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-800' : 'bg-rose-50 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800') }} flex items-center justify-between text-xs">
                            <span class="font-semibold">
                                @if($dif == 0)
                                    ✅ {{ __('Cuadre de Caja:') }}
                                @elseif($dif > 0)
                                    🟢 {{ __('Sobrante de Caja:') }}
                                @else
                                    🔴 {{ __('Faltante de Caja:') }}
                                @endif
                            </span>
                            <span class="text-sm font-black {{ $dif == 0 ? 'text-emerald-700 dark:text-emerald-300' : ($dif > 0 ? 'text-blue-700 dark:text-blue-300' : 'text-rose-700 dark:text-rose-300') }}">
                                {{ $dif >= 0 ? '+ ' : '' }}S/ {{ number_format($dif, 2) }}
                            </span>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('Observaciones de Cierre (Opcional)') }}</flux:label>
                            <flux:textarea wire:model="cierre_observaciones" rows="2" placeholder="Ej. Cuadre verificado conforme..." />
                        </flux:field>

                        <!-- Botones de Acción -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                            <flux:button variant="ghost" type="button" wire:click="$set('showCierreModal', false)">
                                {{ __('Cancelar') }}
                            </flux:button>
                            <flux:button variant="danger" type="submit">
                                {{ __('Confirmar y Cerrar Turno') }}
                            </flux:button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
