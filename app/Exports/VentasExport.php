<?php

namespace App\Exports;

use App\Models\Venta;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VentasExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Comprobante',
            'Cliente',
            'Almacén',
            'Fecha',
            'Método de Pago',
            'Total (S/)',
            'Estado'
        ];
    }

    public function map($venta): array
    {
        return [
            $venta->tipoDocumento->nombre . ' (' . $venta->serie . '-' . str_pad($venta->correlativo, 6, '0', STR_PAD_LEFT) . ')',
            $venta->user->name ?? 'N/A',
            $venta->movimientosKardex->first()?->almacen?->nombre ?? '-',
            $venta->created_at->format('Y-m-d H:i:s'),
            ucfirst($venta->metodo_pago),
            number_format($venta->total, 2),
            $venta->estado_pago === 'pagado' ? 'Completado' : 'Anulado'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
