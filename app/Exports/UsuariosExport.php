<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsuariosExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
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
            'Email',
            'Roles',
            'Estado',
            'Fecha Creación',
        ];
    }

    public function map($usuario): array
    {
        $this->rowCount++;

        return [
            $this->rowCount,
            $usuario->id,
            $usuario->name,
            $usuario->email,
            $usuario->roles->pluck('name')->implode(', ') ?: 'Sin rol',
            $usuario->deleted_at ? 'Eliminado' : ($usuario->activo ? 'Activo' : 'Inactivo'),
            $usuario->created_at ? $usuario->created_at->format('d/m/Y H:i') : '-',
        ];
    }
}
