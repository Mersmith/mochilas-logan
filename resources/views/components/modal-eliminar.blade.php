@props([
    'name' => 'modal-eliminar',
    'title' => __('¿Eliminar registro?'),
    'description' => __('Estás a punto de enviar este registro a la papelera. Podrás restaurarlo más adelante.'),
    'action' => 'ejecutarEliminacion',
    'isPermanent' => false,
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
            <flux:button type="submit" variant="danger">{{ $isPermanent ? __('Sí, eliminar permanentemente') : __('Sí, eliminar') }}</flux:button>
        </div>
    </form>
</flux:modal>
