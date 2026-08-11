<div class="flex items-start max-md:flex-col {{ auth()->user()->hasRole('cliente') ? 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8' : '' }}">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Settings') }}">
            @if(auth()->user()->hasRole('cliente'))
                <x-ecommerce.cliente-menu type="navlist" />
            @else
                <flux:navlist.item :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate icon="user">{{ __('Profile') }}</flux:navlist.item>
                <flux:navlist.item :href="route('security.edit')" :current="request()->routeIs('security.edit')" wire:navigate icon="shield-check">{{ __('Security') }}</flux:navlist.item>
                <flux:navlist.item :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit')" wire:navigate icon="paint-brush">{{ __('Appearance') }}</flux:navlist.item>
            @endif
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
