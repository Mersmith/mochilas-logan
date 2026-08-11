<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CuponesExport implements FromCollection, WithHeadings, WithMapping
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
            'Código',
            'Tipo Descuento',
            'Valor',
            'Monto Mínimo Compra',
            'Usos Totales',
            'Usos Restantes',
            'Fecha Expiración',
            'Estado',
            'Creado Por',
            'Fecha Creación',
        ];
    }

    public function map($cupon): array
    {
        return [
            $cupon->id,
            $cupon->codigo,
            ucfirst($cupon->tipo_descuento),
            $cupon->tipo_descuento === 'porcentaje' ? $cupon->valor_descuento.'%' : '$'.$cupon->valor_descuento,
            '$'.$cupon->monto_minimo_compra,
            $cupon->usos_totales,
            $cupon->usos_restantes,
            $cupon->fecha_expiracion ? $cupon->fecha_expiracion->format('Y-m-d') : 'Sin expiración',
            $cupon->activo ? 'Activo' : 'Inactivo',
            $cupon->creador->name ?? 'Sistema',
            $cupon->created_at ? $cupon->created_at->format('Y-m-d H:i:s') : '',
        ];
    }
}
