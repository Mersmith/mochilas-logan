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

class GuiasInventarioExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query->with(['proveedor', 'almacenOrigen.sede', 'almacenDestino.sede', 'tipoDocumento', 'creador']);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Tipo de Movimiento',
            'Estado',
            'Fecha',
            'Documento',
            'Serie-Correlativo',
            'Origen',
            'Destino',
            'Proveedor',
            'Creado Por',
            'Motivo',
        ];
    }

    public function map($guia): array
    {
        $origen = $guia->almacenOrigen ? $guia->almacenOrigen->nombre.' ('.$guia->almacenOrigen->sede->nombre.')' : '-';
        $destino = $guia->almacenDestino ? $guia->almacenDestino->nombre.' ('.$guia->almacenDestino->sede->nombre.')' : '-';

        return [
            $guia->id,
            $guia->tipo_movimiento,
            $guia->estado,
            $guia->fecha_movimiento ? $guia->fecha_movimiento->format('Y-m-d') : '-',
            $guia->tipoDocumento ? $guia->tipoDocumento->nombre : '-',
            $guia->serie.'-'.str_pad($guia->correlativo, 6, '0', STR_PAD_LEFT),
            $origen,
            $destino,
            $guia->proveedor ? $guia->proveedor->razon_social : '-',
            $guia->creador ? $guia->creador->name : '-',
            $guia->motivo ?? '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
