<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Venta</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .header p {
            margin: 5px 0;
        }
        .info {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .info td {
            padding: 5px;
            vertical-align: top;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .table th, .table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .totals {
            width: 50%;
            float: right;
            border-collapse: collapse;
        }
        .totals th, .totals td {
            padding: 5px;
            text-align: right;
        }
        .totals th {
            width: 60%;
        }
        .footer {
            clear: both;
            text-align: center;
            margin-top: 30px;
            font-size: 10px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>MOCHILAS LOGAN</h1>
        <p>RUC: 20123456789</p>
        <p>Av. Javier Prado 1234, San Isidro, Lima</p>
        <p><strong>{{ strtoupper($venta->tipoDocumento->nombre) }}</strong></p>
        <p>{{ $venta->serie }}-{{ str_pad($venta->correlativo, 6, '0', STR_PAD_LEFT) }}</p>
    </div>

    <table class="info">
        <tr>
            <td width="15%"><strong>Cliente:</strong></td>
            <td width="45%">{{ $venta->user->name }}</td>
            <td width="15%"><strong>Fecha:</strong></td>
            <td width="25%">{{ $venta->created_at->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td><strong>Email:</strong></td>
            <td>{{ $venta->user->email }}</td>
            <td><strong>Pago:</strong></td>
            <td class="capitalize">{{ $venta->metodo_pago }}</td>
        </tr>
        <tr>
            <td><strong>Almacén:</strong></td>
            <td colspan="3">{{ $venta->movimientosKardex->first()?->almacen?->nombre ?? 'Principal' }}</td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>Cant.</th>
                <th>Descripción</th>
                <th class="text-right">P. Unit.</th>
                <th class="text-right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $det)
                @php
                    $descValores = $det->variacion->valores->map(fn($v) => $v->atributo->nombre . ': ' . $v->valor)->implode(', ');
                @endphp
                <tr>
                    <td class="text-center">{{ $det->cantidad }} {{ $det->unidadMedida->abreviacion ?? 'UND' }}</td>
                    <td>
                        <strong>{{ $det->variacion->producto->nombre }}</strong><br>
                        <span style="font-size: 10px; color: #555;">{{ $descValores }}</span>
                    </td>
                    <td class="text-right">S/ {{ number_format($det->precio_unitario, 2) }}</td>
                    <td class="text-right">S/ {{ number_format($det->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <th>Subtotal:</th>
            <td>S/ {{ number_format($venta->subtotal, 2) }}</td>
        </tr>
        <tr>
            <th>Descuento:</th>
            <td>S/ {{ number_format($venta->descuento, 2) }}</td>
        </tr>
        <tr>
            <th>Costo de Envío:</th>
            <td>S/ {{ number_format($venta->costo_envio, 2) }}</td>
        </tr>
        <tr>
            <th><strong>Total a Pagar:</strong></th>
            <td><strong>S/ {{ number_format($venta->total, 2) }}</strong></td>
        </tr>
    </table>

    <div class="footer">
        <p>Gracias por su compra.</p>
        <p>Este es un comprobante electrónico válido. Emitido por el sistema Mochilas Logan.</p>
    </div>
</body>
</html>
