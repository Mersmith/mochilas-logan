<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Cierre de Caja #{{ $sesion->id }}</title>
    <style>
        @page {
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #27272a;
            margin: 0;
        }
        .header {
            border-bottom: 2px solid #18181b;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            color: #18181b;
            margin: 0 0 4px 0;
        }
        .subtitle {
            font-size: 12px;
            color: #71717a;
            margin: 0;
        }
        .grid {
            width: 100%;
            margin-bottom: 15px;
        }
        .grid td {
            vertical-align: top;
            padding: 3px 0;
        }
        .label {
            font-weight: bold;
            color: #71717a;
            text-transform: uppercase;
            font-size: 9px;
        }
        .value {
            font-size: 11px;
            color: #09090b;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #f4f4f5;
            padding: 5px 8px;
            margin: 15px 0 8px 0;
            border-left: 3px solid #18181b;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.data th {
            background-color: #f4f4f5;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
            border-bottom: 1px solid #e4e4e7;
            text-transform: uppercase;
        }
        table.data td {
            padding: 6px 8px;
            border-bottom: 1px solid #f4f4f5;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .box-summary {
            background-color: #fafafa;
            border: 1px solid #e4e4e7;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 15px;
        }
        .signatures {
            margin-top: 40px;
            width: 100%;
        }
        .signatures td {
            width: 50%;
            text-align: center;
            padding: 0 30px;
        }
        .signature-line {
            border-top: 1px solid #71717a;
            padding-top: 5px;
            font-size: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="title">REPORTE DE CIERRE Y ARQUEO DE CAJA</div>
                    <div class="subtitle">MOCHILAS LOGAN — Control de Punto de Venta</div>
                </td>
                <td class="text-right">
                    <div style="font-size: 14px; font-weight: bold;">TURNO #{{ str_pad($sesion->id, 5, '0', STR_PAD_LEFT) }}</div>
                    <div style="font-size: 10px; color: #71717a;">{{ now()->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Datos del Turno -->
    <table class="grid">
        <tr>
            <td style="width: 25%;">
                <div class="label">Sede</div>
                <div class="value font-bold">{{ $sesion->caja->sede->nombre }}</div>
            </td>
            <td style="width: 25%;">
                <div class="label">Caja</div>
                <div class="value font-bold">{{ $sesion->caja->nombre }}</div>
            </td>
            <td style="width: 25%;">
                <div class="label">Cajero(a) Responsable</div>
                <div class="value">{{ $sesion->user->name }}</div>
            </td>
            <td style="width: 25%;">
                <div class="label">Estado</div>
                <div class="value">
                    @if($sesion->estado === 'cerrada')
                        <span class="badge badge-success">CERRADA</span>
                    @else
                        <span class="badge badge-danger">EN CURSO</span>
                    @endif
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Fecha y Hora Apertura</div>
                <div class="value">{{ $sesion->fecha_apertura->format('d/m/Y H:i') }}</div>
            </td>
            <td>
                <div class="label">Fecha y Hora Cierre</div>
                <div class="value">{{ $sesion->fecha_cierre ? $sesion->fecha_cierre->format('d/m/Y H:i') : 'No cerrada' }}</div>
            </td>
            <td>
                <div class="label">Fondo Inicial de Sencillo</div>
                <div class="value font-bold">S/ {{ number_format($sesion->monto_inicial, 2) }}</div>
            </td>
            <td>
                <div class="label">Total Ventas Registradas</div>
                <div class="value font-bold">{{ $sesion->ventas()->count() }} tickets</div>
            </td>
        </tr>
    </table>

    <!-- Arqueo de Efectivo -->
    <div class="section-title">1. Arqueo y Cuadre de Efectivo en Gaveta</div>
    <div class="box-summary">
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <tr>
                <td style="padding: 3px 0;">(+) Fondo Inicial (Apertura de caja)</td>
                <td class="text-right" style="padding: 3px 0;">S/ {{ number_format($sesion->monto_inicial, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 3px 0;">(+) Ventas cobradas en Efectivo</td>
                <td class="text-right" style="padding: 3px 0;">S/ {{ number_format($sesion->total_ventas_efectivo, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 3px 0;">(+) Ingresos Extras de Caja Chica</td>
                <td class="text-right" style="padding: 3px 0;">S/ {{ number_format($sesion->total_ingresos_extra, 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 3px 0; color: #dc2626;">(-) Egresos / Gastos de Caja Chica</td>
                <td class="text-right" style="padding: 3px 0; color: #dc2626;">- S/ {{ number_format($sesion->total_egresos_gastos, 2) }}</td>
            </tr>
            <tr style="border-top: 1px solid #d4d4d8; font-weight: bold;">
                <td style="padding: 6px 0; font-size: 12px;">(=) TOTAL EFECTIVO ESPERADO EN SISTEMA</td>
                <td class="text-right" style="padding: 6px 0; font-size: 12px;">S/ {{ number_format($sesion->monto_cierre_esperado, 2) }}</td>
            </tr>
            <tr style="border-top: 1px solid #18181b; font-weight: bold; background-color: #f4f4f5;">
                <td style="padding: 6px 4px; font-size: 12px;">TOTAL EFECTIVO CONTADO REAL (ARQUEO FÍSICO)</td>
                <td class="text-right" style="padding: 6px 4px; font-size: 12px;">S/ {{ number_format($sesion->monto_cierre_efectivo ?? $sesion->monto_cierre_esperado, 2) }}</td>
            </tr>
            <tr style="font-weight: bold;">
                <td style="padding: 4px 4px;">DIFERENCIA DE CAJA</td>
                <td class="text-right" style="padding: 4px 4px;">
                    @php $dif = (float) $sesion->diferencia_efectivo; @endphp
                    @if($dif > 0)
                        <span style="color: #166534;">+ S/ {{ number_format($dif, 2) }} (SOBRANTE)</span>
                    @elseif($dif < 0)
                        <span style="color: #991b1b;">- S/ {{ number_format(abs($dif), 2) }} (FALTANTE)</span>
                    @else
                        <span style="color: #166534;">S/ 0.00 (CUADRADO EXACTO)</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Resumen de Ventas por Medio de Pago -->
    <div class="section-title">2. Resumen de Ingresos por Medio de Pago</div>
    <table class="data">
        <thead>
            <tr>
                <th>Medio de Pago</th>
                <th class="text-center">Tickets</th>
                <th class="text-right">Total Recaudado</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Efectivo</strong></td>
                <td class="text-center">{{ $sesion->ventas()->where('estado_pago', 'pagado')->where('metodo_pago', 'efectivo')->count() }}</td>
                <td class="text-right font-bold">S/ {{ number_format($sesion->total_ventas_efectivo, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Yape / Plin</strong></td>
                <td class="text-center">{{ $sesion->ventas()->where('estado_pago', 'pagado')->where('metodo_pago', 'yape_plin')->count() }}</td>
                <td class="text-right font-bold">S/ {{ number_format($sesion->total_ventas_yape_plin, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Tarjeta de Crédito / Débito</strong></td>
                <td class="text-center">{{ $sesion->ventas()->where('estado_pago', 'pagado')->where('metodo_pago', 'tarjeta')->count() }}</td>
                <td class="text-right font-bold">S/ {{ number_format($sesion->total_ventas_tarjeta, 2) }}</td>
            </tr>
            <tr>
                <td><strong>Transferencia Bancaria</strong></td>
                <td class="text-center">{{ $sesion->ventas()->where('estado_pago', 'pagado')->where('metodo_pago', 'transferencia')->count() }}</td>
                <td class="text-right font-bold">S/ {{ number_format($sesion->total_ventas_transferencia, 2) }}</td>
            </tr>
            <tr style="background-color: #f4f4f5; font-weight: bold; border-top: 1px solid #18181b;">
                <td>TOTAL VENTAS DEL TURNO</td>
                <td class="text-center">{{ $sesion->ventas()->where('estado_pago', 'pagado')->count() }}</td>
                <td class="text-right">S/ {{ number_format($sesion->total_ventas, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Movimientos de Caja Chica -->
    <div class="section-title">3. Movimientos de Caja Chica (Gastos e Ingresos Extra)</div>
    <table class="data">
        <thead>
            <tr>
                <th>Hora</th>
                <th>Tipo</th>
                <th>Concepto / Motivo</th>
                <th>Comprobante</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sesion->movimientos as $mov)
                <tr>
                    <td>{{ $mov->created_at->format('H:i') }}</td>
                    <td>
                        @if($mov->tipo === 'ingreso')
                            <span style="color: #166534; font-weight: bold;">(+) INGRESO</span>
                        @else
                            <span style="color: #991b1b; font-weight: bold;">(-) GASTO</span>
                        @endif
                    </td>
                    <td>{{ $mov->motivo }}</td>
                    <td>{{ $mov->comprobante ?: '-' }}</td>
                    <td class="text-right font-bold">S/ {{ number_format($mov->monto, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="color: #71717a; padding: 10px;">
                        No se registraron movimientos de caja chica en este turno.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($sesion->observaciones_cierre)
        <div style="margin-top: 10px; font-size: 10px;">
            <strong>Observaciones de Cierre:</strong> {{ $sesion->observaciones_cierre }}
        </div>
    @endif

    <!-- Firmas -->
    <table class="signatures">
        <tr>
            <td>
                <div class="signature-line">
                    {{ $sesion->user->name }}<br>
                    <span style="font-weight: normal; color: #71717a;">Cajero(a) Responsable</span>
                </div>
            </td>
            <td>
                <div class="signature-line">
                    SUPERVISOR / ADMINISTRACIÓN<br>
                    <span style="font-weight: normal; color: #71717a;">Conforme y Validado</span>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
