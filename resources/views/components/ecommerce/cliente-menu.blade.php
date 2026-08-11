@props(['type' => 'menu'])

@if($type === 'menu')
    <flux:menu.item :href="route('profile.edit')" icon="user" wire:navigate>{{ __('Datos Personales') }}</flux:menu.item>
    <flux:menu.item :href="route('direcciones')" icon="map-pin" wire:navigate>{{ __('Mis Direcciones') }}</flux:menu.item>
    <flux:menu.item :href="route('pagos')" icon="credit-card" wire:navigate>{{ __('Medios de pago') }}</flux:menu.item>
    <flux:menu.item :href="route('reembolso')" icon="banknotes" wire:navigate>{{ __('Datos para reembolso') }}</flux:menu.item>
    <flux:menu.item :href="route('compras')" icon="shopping-bag" wire:navigate>{{ __('Mis Compras') }}</flux:menu.item>
    <flux:menu.item :href="route('favoritos')" icon="heart" wire:navigate>{{ __('Favoritos') }}</flux:menu.item>
    <flux:menu.separator />
    <flux:menu.item :href="route('security.edit')" icon="cog" wire:navigate>{{ __('Configurar mi cuenta') }}</flux:menu.item>
    <flux:menu.item :href="route('appearance.edit')" icon="paint-brush" wire:navigate>{{ __('Apariencia') }}</flux:menu.item>
@else
    <flux:navlist.group>
        <flux:navlist.item :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate icon="user">{{ __('Datos Personales') }}</flux:navlist.item>
        <flux:navlist.item :href="route('direcciones')" :current="request()->routeIs('direcciones')" wire:navigate icon="map-pin">{{ __('Mis Direcciones') }}</flux:navlist.item>
        <flux:navlist.item :href="route('pagos')" :current="request()->routeIs('pagos')" wire:navigate icon="credit-card">{{ __('Medios de pago') }}</flux:navlist.item>
        <flux:navlist.item :href="route('reembolso')" :current="request()->routeIs('reembolso')" wire:navigate icon="banknotes">{{ __('Datos para reembolso') }}</flux:navlist.item>
        <flux:navlist.item :href="route('compras')" :current="request()->routeIs('compras')" wire:navigate icon="shopping-bag">{{ __('Mis Compras') }}</flux:navlist.item>
        <flux:navlist.item :href="route('favoritos')" :current="request()->routeIs('favoritos')" wire:navigate icon="heart">{{ __('Favoritos') }}</flux:navlist.item>
    </flux:navlist.group>

    <div class="my-4 border-t border-zinc-200 dark:border-zinc-700"></div>

    <flux:navlist.group>
        <flux:navlist.item :href="route('security.edit')" :current="request()->routeIs('security.edit')" wire:navigate icon="cog">{{ __('Configurar mi cuenta') }}</flux:navlist.item>
        <flux:navlist.item :href="route('appearance.edit')" :current="request()->routeIs('appearance.edit')" wire:navigate icon="paint-brush">{{ __('Apariencia') }}</flux:navlist.item>
    </flux:navlist.group>

    <div class="my-4 border-t border-zinc-200 dark:border-zinc-700"></div>

    <flux:navlist.group>
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:navlist.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer text-left text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200">
                {{ __('Cerrar sesión') }}
            </flux:navlist.item>
        </form>
    </flux:navlist.group>
@endif
