<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SedesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
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
            'Dirección',
            'Cant. Almacenes',
            'Cant. Series',
            'Estado',
            'Fecha Creación',
        ];
    }

    public function map($sede): array
    {
        $this->rowCount++;

        return [
            $this->rowCount,
            $sede->id,
            $sede->nombre,
            $sede->direccion ?: 'Sin dirección',
            $sede->almacenes_count ?? 0,
            $sede->series_count ?? 0,
            $sede->trashed() ? 'Eliminada' : ($sede->activo ? 'Activa' : 'Inactiva'),
            $sede->created_at ? $sede->created_at->format('d/m/Y H:i') : '-',
        ];
    }
}
