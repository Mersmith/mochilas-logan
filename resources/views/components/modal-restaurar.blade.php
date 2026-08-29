@props([
    'name' => 'modal-restaurar',
    'title' => __('¿Restaurar registro?'),
    'description' => __('Estás a punto de restaurar este registro. Volverá a estar activo y disponible en el sistema.'),
    'action' => 'ejecutarRestauracion',
])

<flux:modal name="{{ $name }}" class="min-w-[22rem]">
    <form wire:submit.prevent="{{ $action }}" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <flux:subheading>
                <p>{{ $description }}</p>
            </flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ __('Sí, restaurar') }}</flux:button>
        </div>
    </form>
</flux:modal>
