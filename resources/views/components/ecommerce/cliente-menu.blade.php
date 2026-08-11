@props(['type' => 'menu'])

@if($type === 'menu')
    <flux:menu.item :href="route('profile.edit')" icon="user" wire:navigate>{{ __('Datos Personales') }}</flux:menu.item>
    <flux:menu.item :href="route('direcciones')" icon="map-pin" wire:navigate>{{ __('Mis Direcciones') }}</flux:menu.item>
    <flux:menu.item :href="route('compras')" icon="shopping-bag" wire:navigate>{{ __('Mis Compras') }}</flux:menu.item>
    <flux:menu.item :href="route('favoritos')" icon="heart" wire:navigate>{{ __('Favoritos') }}</flux:menu.item>
@else
    <flux:navlist.item :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate icon="user">{{ __('Datos Personales') }}</flux:navlist.item>
    <flux:navlist.item :href="route('direcciones')" :current="request()->routeIs('direcciones')" wire:navigate icon="map-pin">{{ __('Mis Direcciones') }}</flux:navlist.item>
    <flux:navlist.item :href="route('compras')" :current="request()->routeIs('compras')" wire:navigate icon="shopping-bag">{{ __('Mis Compras') }}</flux:navlist.item>
    <flux:navlist.item :href="route('favoritos')" :current="request()->routeIs('favoritos')" wire:navigate icon="heart">{{ __('Favoritos') }}</flux:navlist.item>
@endif
