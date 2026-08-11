<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProveedoresExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected $query;

    public function __construct(Builder $query)
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
            'Razón Social',
            'RUC',
            'Dirección',
            'Contacto Nombre',
            'Contacto Celular',
            'Estado',
            'Fecha de Creación',
            'Fecha de Actualización',
        ];
    }

    public function map($proveedor): array
    {
        return [
            $proveedor->id,
            $proveedor->razon_social,
            $proveedor->ruc ?? '-',
            $proveedor->direccion ?? '-',
            $proveedor->contacto_nombre ?? '-',
            $proveedor->contacto_celular ?? '-',
            $proveedor->activo ? 'Activo' : 'Inactivo',
            $proveedor->created_at ? $proveedor->created_at->format('Y-m-d H:i:s') : '-',
            $proveedor->updated_at ? $proveedor->updated_at->format('Y-m-d H:i:s') : '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
