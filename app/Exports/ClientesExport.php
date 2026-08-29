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

class ClientesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
            'Cliente / Razón Social',
            'Tipo Persona',
            'Documento (DNI/RUC)',
            'Email',
            'Teléfono',
            'Tipo Cliente',
            'Lista de Precios',
            'Estado',
            'Fecha de Creación',
        ];
    }

    public function map($cliente): array
    {
        $this->rowCount++;

        return [
            $this->rowCount,
            $cliente->id,
            $cliente->nombreMostrar(),
            $cliente->tipo_persona === 'juridica' ? 'Persona Jurídica' : 'Persona Natural',
            $cliente->esEmpresa() ? ('RUC: '.($cliente->ruc ?? '-')) : ('DNI: '.($cliente->dni ?? '-')),
            $cliente->user->email ?? '-',
            $cliente->telefono ?? '-',
            ucfirst($cliente->tipo_cliente),
            $cliente->listaPrecio->nombre ?? '-',
            $cliente->activo ? 'Activo' : 'Inactivo',
            $cliente->created_at ? $cliente->created_at->format('d/m/Y H:i') : '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
