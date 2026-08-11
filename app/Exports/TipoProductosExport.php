<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TipoProductosExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
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
            'Slug',
            'Estado',
            'Fecha Creación',
        ];
    }

    public function map($item): array
    {
        $this->rowCount++;

        return [
            $this->rowCount,
            $item->id,
            $item->nombre,
            $item->slug,
            $item->trashed() ? 'Eliminado' : ($item->activo ? 'Activo' : 'Inactivo'),
            $item->created_at ? $item->created_at->format('d/m/Y H:i') : '-',
        ];
    }
}
