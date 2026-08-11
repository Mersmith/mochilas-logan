<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Catálogo de Productos</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .title { font-size: 24px; font-weight: bold; margin: 0; }
        .subtitle { font-size: 14px; color: #666; margin: 5px 0 0 0; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { width: 33.33%; padding: 10px; vertical-align: top; }
        .product-card { border: 1px solid #ddd; padding: 10px; border-radius: 8px; text-align: center; }
        .product-title { font-size: 14px; font-weight: bold; margin: 0 0 5px 0; height: 35px; overflow: hidden; }
        .product-sku { font-size: 11px; color: #888; margin-bottom: 5px; }
        .product-colors { font-size: 11px; margin-bottom: 5px; height: 15px; overflow: hidden; }
        .product-price { font-size: 16px; font-weight: bold; color: #000; }
        .footer { position: fixed; bottom: -20px; left: 0px; right: 0px; height: 20px; text-align: center; font-size: 10px; color: #999; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">MOCHILAS LOGAN</h1>
        <p class="subtitle">
            Catálogo Oficial - {{ $tipo === 'mayorista' ? 'Precios al por Mayor (B2B)' : 'Precios al Público (Retail)' }}
        </p>
    </div>

    <table class="grid">
        <tr>
        @foreach($productos as $index => $producto)
            @if($index > 0 && $index % 3 == 0)
                @if($index % 12 == 0)
                    </tr></table><div class="page-break"></div><table class="grid"><tr>
                @else
                    </tr><tr>
                @endif
            @endif
            
            <td>
                <div class="product-card">
                    <!-- Placeholder image since we might not have public URL accessible from dompdf -->
                    <div style="height: 150px; background-color: #f0f0f0; margin-bottom: 10px; line-height: 150px; color: #aaa;">
                        FOTO REFERENCIAL
                    </div>
                    
                    <h3 class="product-title">{{ $producto->nombre }}</h3>
                    <div class="product-sku">SKU: {{ $producto->variacions->first()?->sku ?? 'N/A' }}</div>
                    
                    @php
                        $colors = [];
                        foreach ($producto->variacions as $v) {
                            foreach ($v->valores as $val) {
                                if (strtolower($val->atributo->nombre) === 'color') {
                                    $colors[] = $val->valor;
                                }
                            }
                        }
                        $colors = array_unique($colors);
                        
                        $basePrice = 0.00;
                        $firstVar = $producto->variacions->first();
                        if ($firstVar) {
                            $listaName = $tipo === 'mayorista' ? 'Precio Mayor' : 'Precio Menor';
                            $basePrice = (float) ($firstVar->precios->firstWhere('listaPrecio.nombre', $listaName)?->precio ?? 0.00);
                        }
                    @endphp
                    
                    <div class="product-colors">
                        @if(!empty($colors))
                            Colores: {{ implode(', ', $colors) }}
                        @else
                            &nbsp;
                        @endif
                    </div>
                    
                    <div class="product-price">
                        S/ {{ number_format($basePrice, 2) }}
                    </div>
                </div>
            </td>
        @endforeach
        </tr>
    </table>

    <div class="footer">
        Generado el {{ date('d/m/Y H:i') }} - Los precios pueden variar sin previo aviso.
    </div>
</body>
</html>
