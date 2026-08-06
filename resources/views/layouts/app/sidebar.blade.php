<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('admin.dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    @can('dashboard.ver')
                        <flux:sidebar.item icon="home" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('productos.ver')
                        <flux:sidebar.item icon="tag" :href="route('admin.productos.index')" :current="request()->routeIs('admin.productos.index*') || request()->routeIs('admin.productos.manage*') || request()->routeIs('admin.productos.create*')" wire:navigate>
                            {{ __('Productos') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('guias.ver')
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.guias.index')" :current="request()->routeIs('admin.guias.index*')" wire:navigate>
                            {{ __('Guías de Inventario') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('kardex.ver')
                        <flux:sidebar.item icon="chart-bar" :href="route('admin.kardex.index')" :current="request()->routeIs('admin.kardex.index*')" wire:navigate>
                            {{ __('Kardex Valorizado') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('ventas.ver')
                        <flux:sidebar.item icon="shopping-cart" :href="route('admin.ventas.index')" :current="request()->routeIs('admin.ventas.index*') || request()->routeIs('admin.ventas.create*')" wire:navigate>
                            {{ __('Ventas / POS') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('promociones.ver')
                        <flux:sidebar.item icon="ticket" :href="route('admin.promociones.index')" :current="request()->routeIs('admin.promociones.index*')" wire:navigate>
                            {{ __('Promociones / Cupones') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('descuentos.ver')
                        <flux:sidebar.item icon="receipt-percent" :href="route('admin.descuentos.index')" :current="request()->routeIs('admin.descuentos.index*')" wire:navigate>
                            {{ __('Descuentos de Productos') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Mantenimiento')" class="grid">
                    @can('sedes.ver')
                        <flux:sidebar.item icon="building-office-2" :href="route('admin.sedes.index')" :current="request()->routeIs('admin.sedes.index*')" wire:navigate>
                            {{ __('Sedes') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('almacenes.ver')
                        <flux:sidebar.item icon="building-storefront" :href="route('admin.almacenes.index')" :current="request()->routeIs('admin.almacenes.index*')" wire:navigate>
                            {{ __('Almacenes') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('proveedores.ver')
                        <flux:sidebar.item icon="truck" :href="route('admin.proveedores.index')" :current="request()->routeIs('admin.proveedores.index*')" wire:navigate>
                            {{ __('Proveedores') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('tipos-producto.ver')
                        <flux:sidebar.item icon="rectangle-group" :href="route('admin.tipos-producto.index')" :current="request()->routeIs('admin.tipos-producto.index*')" wire:navigate>
                            {{ __('Tipos de Producto') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('marcas.ver')
                        <flux:sidebar.item icon="tag" :href="route('admin.marcas.index')" :current="request()->routeIs('admin.marcas.index*')" wire:navigate>
                            {{ __('Marcas') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('categorias.ver')
                        <flux:sidebar.item icon="squares-2x2" :href="route('admin.categorias.index')" :current="request()->routeIs('admin.categorias.index*')" wire:navigate>
                            {{ __('Categorías') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('atributos.ver')
                        <flux:sidebar.item icon="swatch" :href="route('admin.atributos.index')" :current="request()->routeIs('admin.atributos.index*')" wire:navigate>
                            {{ __('Atributos') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('unidades-medida.ver')
                        <flux:sidebar.item icon="scale" :href="route('admin.unidades-medida.index')" :current="request()->routeIs('admin.unidades-medida.index*')" wire:navigate>
                            {{ __('Unidades de Medida') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('lista-precios.ver')
                        <flux:sidebar.item icon="currency-dollar" :href="route('admin.lista-precios.index')" :current="request()->routeIs('admin.lista-precios.index*')" wire:navigate>
                            {{ __('Listas de Precios') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('tipos-documento.ver')
                        <flux:sidebar.item icon="document-text" :href="route('admin.tipos-documento.index')" :current="request()->routeIs('admin.tipos-documento.index*')" wire:navigate>
                            {{ __('Tipos Documento') }}
                        </flux:sidebar.item>
                    @endcan

                    @can('series.ver')
                        <flux:sidebar.item icon="hashtag" :href="route('admin.series.index')" :current="request()->routeIs('admin.series.index*')" wire:navigate>
                            {{ __('Series Comprobante') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
                @if(auth()->user()->can('roles.ver') || auth()->user()->can('permisos.ver'))
                    <flux:sidebar.group :heading="__('Seguridad')" class="grid">
                        @can('roles.ver')
                            <flux:sidebar.item icon="users" :href="route('admin.roles.index')" :current="request()->routeIs('admin.roles.index*')" wire:navigate>
                                {{ __('Roles') }}
                            </flux:sidebar.item>
                        @endcan

                        @can('permisos.ver')
                            <flux:sidebar.item icon="key" :href="route('admin.permisos.index')" :current="request()->routeIs('admin.permisos.index*')" wire:navigate>
                                {{ __('Permisos') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                    {{ __('Repository') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="book-open-text" href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                    {{ __('Documentation') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
