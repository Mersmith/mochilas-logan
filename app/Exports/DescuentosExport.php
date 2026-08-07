<?php

namespace App\Exports;

use App\Models\Descuento;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DescuentosExport implements FromCollection, WithHeadings, WithMapping
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function collection()
    {
        return $this->query->with('creador')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Porcentaje',
            'Fecha Inicio',
            'Fecha Fin',
            'Estado',
            'Creado Por',
            'Fecha Creación',
        ];
    }

    public function map($descuento): array
    {
        return [
            $descuento->id,
            $descuento->nombre,
            $descuento->porcentaje_descuento . '%',
            $descuento->fecha_inicio ? $descuento->fecha_inicio->format('Y-m-d H:i') : 'N/A',
            $descuento->fecha_fin ? $descuento->fecha_fin->format('Y-m-d H:i') : 'N/A',
            $descuento->activo ? 'Activo' : 'Inactivo',
            $descuento->creador->name ?? 'Sistema',
            $descuento->created_at ? $descuento->created_at->format('Y-m-d H:i:s') : '',
        ];
    }
}
