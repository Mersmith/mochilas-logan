@props([
    'name' => 'modal-confirmar',
    'title' => __('¿Estás seguro?'),
    'description' => __('Esta acción no se puede deshacer.'),
    'action' => '',
    'buttonText' => __('Sí, continuar'),
    'buttonVariant' => 'primary',
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
            <flux:button type="submit" variant="{{ $buttonVariant }}">{{ $buttonText }}</flux:button>
        </div>
    </form>
</flux:modal>
