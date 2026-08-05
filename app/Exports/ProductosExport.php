<?php

namespace App\Exports;

use App\Models\Producto;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductosExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $search = ''
    ) {}

    /**
     * Query with optional search filter (respects the same filter as the Livewire component).
     */
    public function query()
    {
        return Producto::query()
            ->with(['tipoProducto', 'marca', 'categoria', 'variacions.inventarios'])
            ->when($this->search, fn ($q) => $q
                ->where('nombre', 'like', '%'.$this->search.'%')
                ->orWhere('slug', 'like', '%'.$this->search.'%')
            )
            ->orderBy('nombre');
    }

    /**
     * Spreadsheet tab title.
     */
    public function title(): string
    {
        return 'Productos';
    }

    /**
     * Column headings row.
     *
     * @return array<string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Slug',
            'Línea / Tipo',
            'Marca',
            'Categoría',
            'Variaciones',
            'Stock Total',
            'Estado',
            'Fecha de Creación',
        ];
    }

    /**
     * Map each Producto row to an array of cell values.
     *
     * @param  Producto  $producto
     * @return array<int|string>
     */
    public function map($producto): array
    {
        $stockTotal = $producto->variacions->flatMap->inventarios->sum('stock_base');

        return [
            $producto->id,
            $producto->nombre,
            $producto->slug,
            $producto->tipoProducto?->nombre ?? '-',
            $producto->marca?->nombre ?? '-',
            $producto->categoria?->nombre ?? '-',
            $producto->variacions->count(),
            $stockTotal,
            $producto->activo ? 'Activo' : 'Inactivo',
            $producto->created_at?->format('d/m/Y'),
        ];
    }

    /**
     * Style the header row and apply alternating row colors.
     */
    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        // Alternate row banding (light gray for even rows)
        for ($row = 3; $row <= $lastRow; $row += 2) {
            $sheet->getStyle("A{$row}:J{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F9FAFB');
        }

        // Outer border
        $sheet->getStyle("A2:J{$lastRow}")->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('E4E4E7');

        // Center numeric columns
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("J2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [
            // Header row styling
            2 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 11,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '18181B'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
