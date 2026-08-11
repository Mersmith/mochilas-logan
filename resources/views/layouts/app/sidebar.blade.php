<flux:sidebar sticky collapsible="mobile"
    class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
    <flux:sidebar.header>
        <x-app-logo :sidebar="true" href="{{ route('admin.dashboard') }}" wire:navigate />
        <flux:sidebar.collapse class="lg:hidden" />
    </flux:sidebar.header>

    <flux:sidebar.nav>
        @if(auth()->user()->can('usuarios.ver') || auth()->user()->can('clientes.ver') || auth()->user()->can('roles.ver') || auth()->user()->can('permisos.ver'))
            <flux:sidebar.group :heading="__('Sistema')" class="grid">
                @if(auth()->user()->can('roles.ver') || auth()->user()->can('permisos.ver'))
                    <flux:sidebar.group expandable
                        :expanded="request()->routeIs('admin.roles.*') || request()->routeIs('admin.permisos.*')"
                        :heading="__('Accesos')" icon="cog-6-tooth">
                        @can('roles.ver')
                            <flux:sidebar.item icon="shield-check" :href="route('admin.roles.index')"
                                :current="request()->routeIs('admin.roles.index*')" wire:navigate>
                                {{ __('Roles') }}
                            </flux:sidebar.item>
                        @endcan

                        @can('permisos.ver')
                            <flux:sidebar.item icon="key" :href="route('admin.permisos.index')"
                                :current="request()->routeIs('admin.permisos.index*')" wire:navigate>
                                {{ __('Permisos') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif

                @if(auth()->user()->can('usuarios.ver') || auth()->user()->can('clientes.ver'))
                    <flux:sidebar.group expandable
                        :expanded="request()->routeIs('admin.usuarios.*') || request()->routeIs('admin.clientes.*')"
                        :heading="__('Usuarios')" icon="users">
                        @can('usuarios.ver')
                            <flux:sidebar.item icon="user-group" :href="route('admin.usuarios.index')"
                                :current="request()->routeIs('admin.usuarios.index*')" wire:navigate>
                                {{ __('Personal Interno') }}
                            </flux:sidebar.item>
                        @endcan

                        @can('clientes.ver')
                            <flux:sidebar.item icon="user" :href="route('admin.clientes.index')"
                                :current="request()->routeIs('admin.clientes.index*') || request()->routeIs('admin.clientes.create*') || request()->routeIs('admin.clientes.edit*')"
                                wire:navigate>
                                {{ __('Clientes') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.group>
        @endif

        <flux:sidebar.group :heading="__('Negocio')" class="grid">
            <!-- 1. Organización -->
            <flux:sidebar.group expandable
                :expanded="request()->routeIs('admin.sedes.*') || request()->routeIs('admin.almacenes.*')"
                :heading="__('Organización')" icon="building-office">
                @can('sedes.ver')
                    <flux:sidebar.item icon="building-office-2" :href="route('admin.sedes.index')"
                        :current="request()->routeIs('admin.sedes.index*')" wire:navigate>
                        {{ __('Sedes') }}
                    </flux:sidebar.item>
                @endcan
                @can('almacenes.ver')
                    <flux:sidebar.item icon="building-storefront" :href="route('admin.almacenes.index')"
                        :current="request()->routeIs('admin.almacenes.index*')" wire:navigate>
                        {{ __('Almacenes') }}
                    </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>

            <!-- 3. Comercial y Documentos -->
            <flux:sidebar.group expandable
                :expanded="request()->routeIs('admin.proveedores.*') || request()->routeIs('admin.lista-precios.*') || request()->routeIs('admin.tipos-documento.*') || request()->routeIs('admin.series.*')"
                :heading="__('Comercial y Doc.')" icon="document-text">
                @can('tipos-documento.ver')
                    <flux:sidebar.item icon="document-text" :href="route('admin.tipos-documento.index')"
                        :current="request()->routeIs('admin.tipos-documento.index*')" wire:navigate>
                        {{ __('Tipos Documento') }}
                    </flux:sidebar.item>
                @endcan
                @can('series.ver')
                    <flux:sidebar.item icon="hashtag" :href="route('admin.series.index')"
                        :current="request()->routeIs('admin.series.index*')" wire:navigate>
                        {{ __('Series Comprobante') }}
                    </flux:sidebar.item>
                @endcan
                @can('lista-precios.ver')
                    <flux:sidebar.item icon="currency-dollar" :href="route('admin.lista-precios.index')"
                        :current="request()->routeIs('admin.lista-precios.index*')" wire:navigate>
                        {{ __('Listas de Precios') }}
                    </flux:sidebar.item>
                @endcan
                @can('proveedores.ver')
                    <flux:sidebar.item icon="truck" :href="route('admin.proveedores.index')"
                        :current="request()->routeIs('admin.proveedores.index*')" wire:navigate>
                        {{ __('Proveedores') }}
                    </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>
        </flux:sidebar.group>

        <flux:sidebar.group :heading="__('Comercio')" class="grid">
            <!-- Logística -->
            <flux:sidebar.group expandable
                :expanded="request()->routeIs('admin.productos.*') || request()->routeIs('admin.guias.*') || request()->routeIs('admin.kardex.*') || request()->routeIs('admin.inventario.*') || request()->routeIs('admin.tipos-producto.*') || request()->routeIs('admin.categorias.*') || request()->routeIs('admin.marcas.*') || request()->routeIs('admin.atributos.*') || request()->routeIs('admin.unidades-medida.*')"
                :heading="__('Logística')" icon="truck">

                <flux:sidebar.group expandable
                    :expanded="request()->routeIs('admin.tipos-producto.*') || request()->routeIs('admin.categorias.*') || request()->routeIs('admin.marcas.*') || request()->routeIs('admin.atributos.*') || request()->routeIs('admin.unidades-medida.*')"
                    :heading="__('Clasificación')" icon="tag">
                    @can('tipos-producto.ver')
                        <flux:sidebar.item icon="rectangle-group" :href="route('admin.tipos-producto.index')"
                            :current="request()->routeIs('admin.tipos-producto.index*')" wire:navigate>
                            {{ __('Tipos de Producto') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('categorias.ver')
                        <flux:sidebar.item icon="squares-2x2" :href="route('admin.categorias.index')"
                            :current="request()->routeIs('admin.categorias.index*')" wire:navigate>
                            {{ __('Categorías') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('marcas.ver')
                        <flux:sidebar.item icon="bookmark" :href="route('admin.marcas.index')"
                            :current="request()->routeIs('admin.marcas.index*')" wire:navigate>
                            {{ __('Marcas') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('atributos.ver')
                        <flux:sidebar.item icon="swatch" :href="route('admin.atributos.index')"
                            :current="request()->routeIs('admin.atributos.index*')" wire:navigate>
                            {{ __('Atributos') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('unidades-medida.ver')
                        <flux:sidebar.item icon="scale" :href="route('admin.unidades-medida.index')"
                            :current="request()->routeIs('admin.unidades-medida.index*')" wire:navigate>
                            {{ __('Medidas') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>

                @can('productos.ver')
                    <flux:sidebar.item icon="cube" :href="route('admin.productos.index')"
                        :current="request()->routeIs('admin.productos.index*') || request()->routeIs('admin.productos.manage*') || request()->routeIs('admin.productos.create*')"
                        wire:navigate>
                        {{ __('Productos') }}
                    </flux:sidebar.item>
                @endcan

                @can('guias.ver')
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('admin.guias.index')"
                        :current="request()->routeIs('admin.guias.index*')" wire:navigate>
                        {{ __('Guías de Inventario') }}
                    </flux:sidebar.item>
                @endcan

                @can('kardex.ver')
                    <flux:sidebar.item icon="chart-bar" :href="route('admin.kardex.index')"
                        :current="request()->routeIs('admin.kardex.index*')" wire:navigate>
                        {{ __('Kardex Valorizado') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="squares-plus" :href="route('admin.inventario.index')"
                        :current="request()->routeIs('admin.inventario.index*')" wire:navigate>
                        {{ __('Control de Inventario') }}
                    </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>

            <!-- Punto de Venta -->
            <flux:sidebar.group expandable
                :expanded="request()->routeIs('admin.ventas.*') || request()->routeIs('admin.promociones.*') || request()->routeIs('admin.descuentos.*')"
                :heading="__('Punto de Venta')" icon="shopping-bag">
                @can('promociones.ver')
                    <flux:sidebar.item icon="receipt-percent" :href="route('admin.descuentos.index')"
                        :current="request()->routeIs('admin.descuentos.index*') || request()->routeIs('admin.descuentos.create*') || request()->routeIs('admin.descuentos.edit*')"
                        wire:navigate>
                        {{ __('Descuentos') }}
                    </flux:sidebar.item>
                @endcan

                @can('promociones.ver')
                    <flux:sidebar.item icon="ticket" :href="route('admin.cupones.index')"
                        :current="request()->routeIs('admin.cupones.index*') || request()->routeIs('admin.cupones.create*') || request()->routeIs('admin.cupones.edit*')"
                        wire:navigate>
                        {{ __('Cupones') }}
                    </flux:sidebar.item>
                @endcan

                @can('ventas.ver')
                    <flux:sidebar.item icon="shopping-cart" :href="route('admin.ventas.index')"
                        :current="request()->routeIs('admin.ventas.index*') || request()->routeIs('admin.ventas.create*')"
                        wire:navigate>
                        {{ __('Ventas / POS') }}
                    </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>
        </flux:sidebar.group>

        <flux:sidebar.group :heading="__('Analítica')" class="grid">
            <flux:sidebar.group expandable :expanded="request()->routeIs('admin.reportes.*')" :heading="__('Reportes')"
                icon="chart-pie">
                @can('dashboard.ver')
                    <flux:sidebar.item icon="presentation-chart-line" :href="route('admin.dashboard')"
                        :current="request()->routeIs('admin.dashboard')" wire:navigate>
                        {{ __('Ventas') }}
                    </flux:sidebar.item>
                @endcan

                @can('productos.ver')
                    <flux:sidebar.item icon="queue-list" :href="route('admin.reportes.skus.index')"
                        :current="request()->routeIs('admin.reportes.skus.index*')" wire:navigate>
                        {{ __('Catálogo de SKUs') }}
                    </flux:sidebar.item>
                @endcan
            </flux:sidebar.group>
        </flux:sidebar.group>
    </flux:sidebar.nav>

    <flux:spacer />

    <flux:sidebar.item icon="arrow-left-start-on-rectangle" :href="route('home')" target="_blank">
        {{ __('Ir al Inicio') }}
    </flux:sidebar.item>

    <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
</flux:sidebar>

<!-- Mobile User Menu -->
<flux:header class="lg:hidden">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

    <flux:spacer />

    <flux:dropdown position="top" align="end">
        <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

        <flux:menu>
            <flux:menu.radio.group>
                <div class="p-0 text-sm font-normal">
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

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
                <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer" data-test="logout-button">
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
</flux:header>