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
        echo "File not found: $file\n";
        continue;
    }
    $content = file_get_contents($path);
    
    // 1. Remove Exportar dropdown from top header
    $content = preg_replace(
        '/<div class="flex flex-wrap items-center gap-2">\s*<flux:dropdown>.*?<\/flux:dropdown>\s*(@can\([\'"][^\'"]+?\.editar[\'"]\))/is',
        '$1',
        $content,
        1
    );

    // 2. Remove Limpiar Filtros button from filters
    $content = preg_replace(
        '/<div class="flex-1 sm:text-right">\s*<flux:button[^>]+?wire:click="resetFiltros"[^>]*>.*?<\/flux:button>\s*<\/div>/is',
        '',
        $content,
        1
    );
    $content = preg_replace(
        '/<div class="flex flex-col sm:flex-row items-end gap-3">\s*<div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">(.*?)<\/div>\s*<\/div>/is',
        '<div class="flex flex-col sm:flex-row sm:items-center gap-3">$1</div>',
        $content,
        1
    );

    // 3. Inject table header bar
    $tableHeaderHtml = <<<'HTML'
        <!-- Cabecera de tabla: Acciones + PerPage -->
        <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30">
            <flux:dropdown>
                <flux:button class="!bg-emerald-600 !text-white hover:!bg-emerald-700 border-none" size="sm" icon="arrow-down-tray">{{ __('Exportar') }}</flux:button>
                <flux:menu>
                    <flux:menu.item wire:click="exportarFiltrados" icon="funnel">{{ __('Resultados filtrados') }}</flux:menu.item>
                    <flux:menu.item wire:click="exportarTodos" icon="document-text">{{ __('Todos los registros') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            <flux:button size="sm" class="!bg-red-600 !text-white hover:!bg-red-700 border-none" wire:click="resetFiltros" icon="arrow-path">
                {{ __('Limpiar') }}
            </flux:button>

            <div class="flex items-center gap-2 text-sm text-zinc-500 ml-auto">
                <span class="hidden sm:inline">{{ __('Mostrar') }}</span>
                <flux:select wire:model.live="perPage" class="w-20">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </flux:select>
            </div>
        </div>

        <div class="overflow-x-auto flex-1">
HTML;
    if (strpos($content, 'Cabecera de tabla: Acciones + PerPage') === false) {
        $content = preg_replace(
            '/<div class="overflow-x-auto flex-1">/is',
            $tableHeaderHtml,
            $content,
            1
        );
    }

    // 4. Update empty state
    $content = preg_replace(
        '/(<td colspan="{{ auth\(\)->user\(\)->can\([\'"][^\'"]+?\.editar[\'"]\) \? \d+ : \d+ }}"\s*class="text-center )py-8 text-zinc-500(">\s*).*?<\/td>/is',
        '$1py-12 text-zinc-400$2<div class="flex flex-col items-center gap-2">' . "\n" . '                                    <flux:icon.face-smile class="size-8 text-zinc-300" />' . "\n" . '                                    <span>{{ $search ? __(\'No se encontraron resultados para ":query"\', [\'query\' => $search]) : __(\'No hay registros.\') }}</span>' . "\n" . '                                </div>' . "\n" . '                            </td>',
        $content,
        1
    );

    // 5. Replace table footer
    $content = preg_replace(
        '/<\/table>\s*<\/div>\s*@if\(\$this->([a-zA-Z0-9_]+)->hasPages\(\)\).*?(<!-- Modals de confirmación -->|<x-modal-eliminar)/is',
        "</table>\n        </div>\n\n        <!-- Pie de tabla: Paginación + Info -->\n        <div class=\"px-4 py-4 border-t border-zinc-200 dark:border-zinc-700\">\n            @if(\$this->$1->hasPages())\n                {{ \$this->$1->links() }}\n            @else\n                <p class=\"text-xs text-zinc-400\">\n                    {{ __(':total registro(s)', ['total' => \$this->$1->total()]) }}\n                </p>\n            @endif\n        </div>\n    </div>\n\n    $2",
        $content,
        1
    );
    
    file_put_contents($path, $content);
    echo "Updated $file\n";
}
?>
