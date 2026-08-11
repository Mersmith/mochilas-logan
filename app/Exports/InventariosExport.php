<?php

namespace App\Exports;

use App\Models\Inventario;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventariosExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
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
            'ID',
            'Producto',
            'SKU',
            'Atributos',
            'Almacén',
            'Stock Base',
            'Stock Mínimo',
            'Estado Stock',
            'Última Actualización'
        ];
    }

    public function map($inventario): array
    {
        $atributos = '-';
        if ($inventario->variacion && $inventario->variacion->valores->count() > 0) {
            $atributos = $inventario->variacion->valores->map(function ($val) {
                return $val->atributo->nombre . ': ' . $val->valor;
            })->implode(', ');
        }

        $estadoStock = 'Normal';
        if ($inventario->stock_base <= 0) {
            $estadoStock = 'Agotado';
        } elseif ($inventario->stock_base <= $inventario->stock_minimo) {
            $estadoStock = 'Stock Bajo';
        }

        return [
            $inventario->id,
            $inventario->variacion->producto->nombre ?? 'N/A',
            $inventario->variacion->sku ?? 'N/A',
            $atributos,
            $inventario->almacen->nombre ?? 'N/A',
            $inventario->stock_base,
            $inventario->stock_minimo,
            $estadoStock,
            $inventario->updated_at ? $inventario->updated_at->format('Y-m-d H:i:s') : 'N/A'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
