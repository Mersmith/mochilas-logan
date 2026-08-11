<?php

namespace App\Exports;

use App\Models\Kardex;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KardexExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
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
            'Fecha',
            'Concepto / Motivo',
            'Tipo',
            'Cantidad Base',
            'Costo Unitario (S/)',
            'Costo Total Movimiento (S/)',
            'Stock Final',
            'Valor Total Final (S/)'
        ];
    }

    public function map($movimiento): array
    {
        return [
            $movimiento->created_at->format('Y-m-d H:i:s'),
            $movimiento->concepto,
            $movimiento->tipo_transaccion,
            ($movimiento->tipo_transaccion === 'Entrada' ? '+' : '-') . $movimiento->cantidad,
            number_format($movimiento->costo_unitario, 2),
            number_format($movimiento->costo_total, 2),
            $movimiento->stock_posterior,
            number_format($movimiento->valor_total_almacen, 2)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
