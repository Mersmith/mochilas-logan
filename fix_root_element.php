<?php
$files = [
    'tipos-documento/⚡index.blade.php',
    'series/⚡index.blade.php',
    'lista-precios/⚡index.blade.php',
    'proveedores/⚡index.blade.php',
    'tipos-producto/⚡index.blade.php',
    'categorias/⚡index.blade.php',
    'marcas/⚡index.blade.php',
    'atributos/⚡index.blade.php',
    'unidades-medida/⚡index.blade.php'
];

$baseDir = __DIR__ . '/resources/views/pages/erp/';

foreach ($files as $file) {
    $path = $baseDir . $file;
    if (!file_exists($path)) {
        continue;
    }
    $content = file_get_contents($path);
    
    // Fix the extra closing div left after @endcan in the top header
    // It looks like:
    // @endcan
    // </div>
    // </div>
    // We want to remove the extra </div>
    $content = preg_replace(
        '/(@endcan\s*)<\/div>(\s*<\/div>\s*{{-- Filtros --}})/',
        '$1$2',
        $content,
        1
    );

    // Some files might have empty lines instead of {{-- Filtros --}}, let's make it robust
    $content = preg_replace(
        '/(@endcan\s*)<\/div>(\s*<\/div>\s*<div class="bg-white)/',
        '$1$2',
        $content,
        1
    );
    
    file_put_contents($path, $content);
    echo "Fixed $file\n";
}
?>
