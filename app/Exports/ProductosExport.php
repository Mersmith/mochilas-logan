<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductosExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    use Exportable;

    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query->with(['tipoProducto', 'marca', 'categoria']);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tipo de Producto',
            'Marca',
            'Categoría',
            'Nombre',
            'Slug',
            'Descripción',
            'Estado',
            'Fecha de Creación',
            'Fecha de Actualización',
        ];
    }

    public function map($producto): array
    {
        return [
            $producto->id,
            $producto->tipoProducto->nombre ?? '-',
            $producto->marca->nombre ?? '-',
            $producto->categoria->nombre ?? '-',
            $producto->nombre,
            $producto->slug,
            $producto->descripcion,
            $producto->activo ? 'Activo' : 'Inactivo',
            $producto->created_at ? $producto->created_at->format('Y-m-d H:i:s') : '-',
            $producto->updated_at ? $producto->updated_at->format('Y-m-d H:i:s') : '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => ['font' => ['bold' => true]],
        ];
    }
}
