<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #{{ $venta->serie }}-{{ str_pad($venta->correlativo, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            color: #000;
            background: #fff;
            width: 80mm;
            margin: 0 auto;
            padding: 10px 8px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        .logo {
            font-size: 16px;
            font-weight: 900;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }
        .header-info {
            font-size: 10px;
            line-height: 1.3;
            margin-bottom: 8px;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .double-divider {
            border-top: 2px solid #000;
            margin: 6px 0;
        }

        .doc-title {
            font-size: 13px;
            font-weight: bold;
            margin: 4px 0;
        }
        .doc-number {
            font-size: 14px;
            font-weight: 900;
        }

        .info-table {
            width: 100%;
            font-size: 10.5px;
            line-height: 1.3;
        }
        .info-table td {
            vertical-align: top;
            padding: 1px 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin: 4px 0;
        }
        .items-table th {
            border-bottom: 1px dashed #000;
            padding: 4px 0;
            font-size: 10px;
            text-transform: uppercase;
        }
        .items-table td {
            padding: 3px 0;
            vertical-align: top;
        }

        .totals-table {
            width: 100%;
            font-size: 11px;
            line-height: 1.4;
        }
        .totals-table td {
            padding: 1px 0;
        }
        .total-pay {
            font-size: 15px;
            font-weight: 900;
        }

        .footer {
            margin-top: 12px;
            font-size: 9.5px;
            line-height: 1.3;
            color: #333;
        }

        .btn-print {
            background: #18181b;
            color: #fff;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            margin: 15px auto;
            display: block;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            body {
                width: 100%;
                margin: 0;
                padding: 0;
            }
            @page {
                margin: 0;
                size: auto;
            }
        }
    </style>
</head>
<body>

    <div class="no-print" style="text-align: center; margin-bottom: 10px;">
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir Ticket (80mm)</button>
    </div>

    <!-- Encabezado Empresa -->
    <div class="text-center">
        <div class="logo">MOCHILAS LOGAN</div>
        <div class="header-info">
            VENTA DE MOCHILAS Y ACCESORIOS<br>
            RUC: 20608549321<br>
            {{ $venta->movimientosKardex->first()?->almacen?->sede?->nombre ?? 'Sede Principal' }}<br>
            {{ $venta->movimientosKardex->first()?->almacen?->sede?->direccion ?? 'Av. Comercial #123' }}<br>
            Teléfono: 999 888 777
        </div>
    </div>

    <div class="divider"></div>

    <!-- Datos del Comprobante -->
    <div class="text-center">
        <div class="doc-title uppercase">{{ $venta->tipoDocumento->nombre }}</div>
        <div class="doc-number">{{ $venta->serie }}-{{ str_pad($venta->correlativo, 6, '0', STR_PAD_LEFT) }}</div>
    </div>

    <div class="divider"></div>

    <!-- Información de Venta -->
    <table class="info-table">
        <tr>
            <td style="width: 35%;">FECHA/HORA:</td>
            <td class="font-bold">{{ $venta->created_at->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td>CLIENTE:</td>
            <td class="font-bold uppercase">{{ $venta->user->name }}</td>
        </tr>
        @if($venta->user->cliente?->dni || $venta->user->cliente?->ruc)
            <tr>
                <td>DOC. IDENT.:</td>
                <td class="font-bold">{{ $venta->user->cliente?->dni ? 'DNI ' . $venta->user->cliente->dni : 'RUC ' . $venta->user->cliente->ruc }}</td>
            </tr>
        @endif
        <tr>
            <td>ALMACÉN:</td>
            <td>{{ $venta->movimientosKardex->first()?->almacen?->nombre ?? 'Almacén Central' }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Items / Detalle de Productos -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-left" style="width: 15%;">CANT</th>
                <th class="text-left" style="width: 50%;">DESCRIPCIÓN</th>
                <th class="text-right" style="width: 15%;">P.U.</th>
                <th class="text-right" style="width: 20%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $det)
                @php
                    $desc = $det->variacion->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
                @endphp
                <tr>
                    <td class="font-bold">{{ $det->cantidad }} {{ $det->unidadMedida->abreviacion ?? 'UND' }}</td>
                    <td>
                        {{ $det->variacion->producto->nombre }}
                        @if($desc)
                            <br><span style="font-size: 9px; color: #444;">({{ $desc }})</span>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($det->precio_unitario, 2) }}</td>
                    <td class="text-right font-bold">{{ number_format($det->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="double-divider"></div>

    <!-- Totales -->
    <table class="totals-table">
        <tr>
            <td>SUBTOTAL:</td>
            <td class="text-right font-bold">S/ {{ number_format($venta->subtotal, 2) }}</td>
        </tr>
        @if($venta->descuento > 0)
            <tr>
                <td>DESCUENTO:</td>
                <td class="text-right font-bold">- S/ {{ number_format($venta->descuento, 2) }}</td>
            </tr>
        @endif
        <tr class="total-pay">
            <td style="padding-top: 4px;">TOTAL A PAGAR:</td>
            <td class="text-right" style="padding-top: 4px;">S/ {{ number_format($venta->total, 2) }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Información de Pago -->
    <table class="info-table">
        <tr>
            <td style="width: 45%;">FORMA DE PAGO:</td>
            <td class="font-bold uppercase text-right">{{ $venta->metodo_pago }}</td>
        </tr>
        @if($venta->comentarios && str_contains($venta->comentarios, 'PAGO:'))
            <tr>
                <td colspan="2" style="font-size: 9.5px; padding-top: 2px;">
                    {{ $venta->comentarios }}
                </td>
            </tr>
        @endif
    </table>

    <div class="divider"></div>

    <!-- Pie de Ticket -->
    <div class="text-center footer">
        <div class="font-bold" style="font-size: 11px; margin-bottom: 3px;">¡GRACIAS POR SU COMPRA!</div>
        <div>CONSERVE ESTE TICKET PARA CUALQUIER RECLAMO O CAMBIO DENTRO DE LOS 7 DÍAS.</div>
        <div style="margin-top: 6px; font-size: 8.5px; color: #555;">mochilaslogan.com | Logan Mochilas ERP</div>
    </div>

    <script>
        // Auto-abrir diálogo de impresión si se abre en ventana nueva
        window.addEventListener('DOMContentLoaded', () => {
            if (window.opener) {
                window.print();
            }
        });
    </script>
</body>
</html>
