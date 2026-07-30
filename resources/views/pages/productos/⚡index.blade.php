<?php

use App\Models\Producto;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Flux\Flux;

new #[Title('Catálogo de Productos')] class extends Component {
    use WithPagination;

    public string $search = '';

    /**
     * Reset pagination when search changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Get products.
     */
    #[Computed]
    public function productos()
    {
        return Producto::with(['tipoProducto', 'marca', 'categoria'])
            ->when($this->search, function ($query) {
                $query->where('nombre', 'like', '%' . $this->search . '%')
                      ->orWhere('slug', 'like', '%' . $this->search . '%');
            })
            ->orderBy('nombre', 'asc')
            ->paginate(10);
    }

    /**
     * Soft delete a product.
     */
    public function eliminar(int $id): void
    {
        $producto = Producto::findOrFail($id);
        $producto->delete();
        
        Flux::toast(variant: 'success', text: __('Producto eliminado correctamente.'));
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Catálogo de Productos') }}</flux:heading>
            <flux:subheading>{{ __('Administra los productos de tu catálogo, gestiona sus variaciones, precios y empaques.') }}</flux:subheading>
        </div>
        
        <flux:button variant="primary" icon="plus" :href="route('admin.productos.create')" wire:navigate>
            {{ __('Nuevo Producto') }}
        </flux:button>
    </div>

    <!-- Buscador -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between bg-zinc-50 dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="w-full sm:w-80">
            <flux:input wire:model.live="search" placeholder="Buscar producto..." icon="magnifying-glass" />
        </div>
    </div>

    <!-- Tabla (Tailwind para compatibilidad con Flux Free) -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Nombre del Producto') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Línea/Tipo') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Marca') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Categoría') }}</th>
                        <th class="p-4 font-semibold text-zinc-700 dark:text-zinc-300 text-center">{{ __('Estado') }}</th>
                        <th class="p-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->productos as $prod)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="p-4">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $prod->nombre }}</div>
                                <div class="text-xs text-zinc-500">{{ $prod->slug }}</div>
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $prod->tipoProducto->nombre }}
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $prod->marca->nombre }}
                            </td>
                            <td class="p-4 text-zinc-600 dark:text-zinc-400">
                                {{ $prod->categoria->nombre }}
                            </td>
                            <td class="p-4 text-center">
                                @if($prod->activo)
                                    <flux:badge color="success">{{ __('Activo') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc">{{ __('Inactivo') }}</flux:badge>
                                @endif
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <flux:button variant="ghost" icon="cog" size="sm" :href="route('admin.productos.manage', $prod)" wire:navigate title="Gestionar Variaciones, Precios y Empaques" />
                                <flux:button variant="ghost" icon="trash" size="sm" wire:click.prevent="eliminar({{ $prod->id }})" wire:confirm="¿Está seguro de eliminar este producto?" title="Eliminar Producto" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-zinc-500">
                                {{ __('No se encontraron productos en el catálogo.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->productos->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $this->productos->links() }}
            </div>
        @endif
    </div>
</div>
