<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AlmacenesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    use Exportable;

    protected Builder $query;

    protected int $rowCount = 0;

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
            'N°',
            'ID',
            'Nombre',
            'Ubicación',
            'Sede',
            'Estado',
            'Fecha Creación',
        ];
    }

    public function map($almacen): array
    {
        $this->rowCount++;

        return [
            $this->rowCount,
            $almacen->id,
            $almacen->nombre,
            $almacen->ubicacion ?: 'Sin ubicación',
            $almacen->sede ? $almacen->sede->nombre : '-',
            $almacen->trashed() ? 'Eliminado' : ($almacen->activo ? 'Activo' : 'Inactivo'),
            $almacen->created_at ? $almacen->created_at->format('d/m/Y H:i') : '-',
        ];
    }
}
