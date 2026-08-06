<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new #[Title('Gestión de Usuarios')] class extends Component {
    // ── Filtros ──────────────────────────────────────────
    public string $search = '';
    public string $filtroRol = '';
    public string $filtroEstado = 'activos'; // activos | eliminados | todos

    // ── Formulario Crear/Editar ───────────────────────────
    public ?int $usuario_id = null;
    public string $nombre = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $rol = '';
    public bool $activo = true;
    public bool $showForm = false;

    // ── Modal Cambiar Contraseña ──────────────────────────
    public ?int $cambiarPass_usuario_id = null;
    public string $nueva_password = '';
    public string $nueva_password_confirmation = '';

    /**
     * Lista de usuarios con filtros.
     */
    #[Computed]
    public function usuarios()
    {
        $query = User::with('roles')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->when($this->filtroRol, fn ($q) => $q->role($this->filtroRol))
            ->orderBy('name');

        if ($this->filtroEstado === 'eliminados') {
            $query->onlyTrashed();
        } elseif ($this->filtroEstado === 'todos') {
            $query->withTrashed();
        }
        // 'activos' → por defecto (sin trashed)

        return $query->get();
    }

    /**
     * Lista de roles disponibles para el select.
     */
    #[Computed]
    public function roles()
    {
        return Role::orderBy('name')->get();
    }

    /**
     * Guardar o actualizar usuario.
     */
    public function guardar(): void
    {
        if (! auth()->user()->hasPermissionTo('usuarios.editar')) {
            abort(403);
        }

        $rules = [
            'nombre' => 'required|string|max:255',
            'email'  => ['required', 'email', Rule::unique('users', 'email')->ignore($this->usuario_id)->whereNull('deleted_at')],
            'rol'    => 'required|exists:roles,name',
        ];

        if (! $this->usuario_id) {
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        $this->validate($rules);

        if ($this->usuario_id) {
            $usuario = User::withTrashed()->findOrFail($this->usuario_id);
            $usuario->update(['name' => $this->nombre, 'email' => $this->email, 'activo' => $this->activo]);
            $usuario->syncRoles([$this->rol]);
            Flux::toast(variant: 'success', text: 'Usuario actualizado correctamente.');
        } else {
            $usuario = User::create([
                'name'     => $this->nombre,
                'email'    => $this->email,
                'password' => bcrypt($this->password),
                'activo'   => $this->activo,
            ]);
            $usuario->assignRole($this->rol);
            Flux::toast(variant: 'success', text: 'Usuario creado correctamente.');
        }

        $this->limpiarForm();
    }

    /**
     * Cargar datos del usuario en el formulario.
     */
    public function editar(int $id): void
    {
        $usuario = User::withTrashed()->with('roles')->findOrFail($id);
        $this->usuario_id = $usuario->id;
        $this->nombre = $usuario->name;
        $this->email = $usuario->email;
        $this->rol = $usuario->roles->first()?->name ?? '';
        $this->activo = $usuario->activo;
        $this->password = '';
        $this->password_confirmation = '';
        $this->showForm = true;
    }

    /**
     * Eliminación lógica (soft delete).
     */
    public function eliminar(int $id): void
    {
        if (! auth()->user()->hasPermissionTo('usuarios.editar')) {
            abort(403);
        }

        $usuario = User::findOrFail($id);

        if ($usuario->id === auth()->id()) {
            Flux::toast(variant: 'warning', text: 'No puedes eliminar tu propia cuenta.');
            return;
        }

        $usuario->delete();
        Flux::toast(variant: 'success', text: 'Usuario eliminado (soft delete).');
    }

    /**
     * Activar / Desactivar usuario.
     */
    public function toggleActivo(int $id): void
    {
        if (! auth()->user()->hasPermissionTo('usuarios.editar')) {
            abort(403);
        }

        $usuario = User::findOrFail($id);

        if ($usuario->id === auth()->id()) {
            Flux::toast(variant: 'warning', text: 'No puedes desactivarte a ti mismo.');
            return;
        }

        $usuario->update(['activo' => ! $usuario->activo]);
        Flux::toast(
            variant: $usuario->activo ? 'success' : 'warning',
            text: $usuario->activo ? 'Usuario activado.' : 'Usuario desactivado.'
        );
    }

    /**
     * Restaurar usuario eliminado.
     */
    public function restaurar(int $id): void
    {
        if (! auth()->user()->hasPermissionTo('usuarios.editar')) {
            abort(403);
        }

        User::withTrashed()->findOrFail($id)->restore();
        Flux::toast(variant: 'success', text: 'Usuario restaurado correctamente.');
    }

    /**
     * Abrir modal para cambiar contraseña.
     */
    public function abrirCambiarPassword(int $id): void
    {
        $this->cambiarPass_usuario_id = $id;
        $this->nueva_password = '';
        $this->nueva_password_confirmation = '';
        Flux::modal('cambiar-password')->show();
    }

    /**
     * Guardar nueva contraseña.
     */
    public function cambiarPassword(): void
    {
        if (! auth()->user()->hasPermissionTo('usuarios.editar')) {
            abort(403);
        }

        $this->validate([
            'nueva_password' => 'required|string|min:8|confirmed',
        ], [], [
            'nueva_password'              => 'nueva contraseña',
            'nueva_password_confirmation' => 'confirmación',
        ]);

        User::withTrashed()->findOrFail($this->cambiarPass_usuario_id)
            ->update(['password' => bcrypt($this->nueva_password)]);

        Flux::toast(variant: 'success', text: 'Contraseña actualizada correctamente.');
        Flux::modal('cambiar-password')->close();
        $this->cambiarPass_usuario_id = null;
        $this->nueva_password = '';
        $this->nueva_password_confirmation = '';
    }

    public function limpiarForm(): void
    {
        $this->usuario_id = null;
        $this->nombre = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->rol = '';
        $this->activo = true;
        $this->showForm = false;
    }

    public function nuevoUsuario(): void
    {
        $this->limpiarForm();
        $this->showForm = true;
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Gestión de Usuarios') }}</flux:heading>
            <flux:subheading>{{ __('Administra los usuarios del sistema y sus roles de acceso.') }}</flux:subheading>
        </div>
        @can('usuarios.editar')
            <flux:button variant="primary" icon="plus" wire:click="nuevoUsuario">
                {{ __('Nuevo Usuario') }}
            </flux:button>
        @endcan
    </div>

    {{-- Formulario Crear/Editar (Modal-like panel) --}}
    @if($showForm)
        @can('usuarios.editar')
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">{{ $usuario_id ? __('Editar Usuario') : __('Nuevo Usuario') }}</flux:heading>
                    <flux:button variant="ghost" icon="x-mark" size="sm" wire:click="limpiarForm" />
                </div>

                <form wire:submit.prevent="guardar" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Nombre completo') }}</flux:label>
                        <flux:input wire:model="nombre" placeholder="Ej. Juan Pérez" required />
                        <flux:error name="nombre" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Correo electrónico') }}</flux:label>
                        <flux:input wire:model="email" type="email" placeholder="usuario@logan.com" required />
                        <flux:error name="email" />
                    </flux:field>

                    @if(!$usuario_id)
                        <flux:field>
                            <flux:label>{{ __('Contraseña') }}</flux:label>
                            <flux:input wire:model="password" type="password" placeholder="Mínimo 8 caracteres" required />
                            <flux:error name="password" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Confirmar contraseña') }}</flux:label>
                            <flux:input wire:model="password_confirmation" type="password" placeholder="Repite la contraseña" required />
                        </flux:field>
                    @endif

                    <flux:field class="md:col-span-{{ $usuario_id ? '1' : '1' }}">
                        <flux:label>{{ __('Rol') }}</flux:label>
                        <flux:select wire:model="rol" placeholder="Selecciona un rol">
                            @foreach($this->roles as $r)
                                <option value="{{ $r->name }}">{{ ucfirst($r->name) }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="rol" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Estado') }}</flux:label>
                        <div class="flex items-center gap-3 h-10">
                            <flux:switch wire:model="activo" />
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $activo ? __('Usuario activo') : __('Usuario inactivo') }}
                            </span>
                        </div>
                    </flux:field>

                    <div class="md:col-span-2 flex justify-end gap-3 pt-2">
                        <flux:button variant="ghost" wire:click="limpiarForm">{{ __('Cancelar') }}</flux:button>
                        <flux:button variant="primary" type="submit" icon="check">
                            {{ $usuario_id ? __('Actualizar') : __('Crear Usuario') }}
                        </flux:button>
                    </div>
                </form>
            </div>
        @endcan
    @endif

    {{-- Filtros --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 shadow-sm">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="{{ __('Buscar por nombre o email...') }}"
                />
            </div>
            <flux:select wire:model.live="filtroRol" class="sm:w-48">
                <option value="">{{ __('Todos los roles') }}</option>
                @foreach($this->roles as $r)
                    <option value="{{ $r->name }}">{{ ucfirst($r->name) }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="filtroEstado" class="sm:w-44">
                <option value="activos">{{ __('Activos') }}</option>
                <option value="eliminados">{{ __('Desactivados') }}</option>
                <option value="todos">{{ __('Todos') }}</option>
            </flux:select>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 font-semibold bg-zinc-50 dark:bg-zinc-800/40">
                        <th class="p-3">{{ __('Usuario') }}</th>
                        <th class="p-3">{{ __('Email') }}</th>
                        <th class="p-3 text-center">{{ __('Rol') }}</th>
                        <th class="p-3 text-center">{{ __('Estado') }}</th>
                        <th class="p-3 text-center">{{ __('Registrado') }}</th>
                        @can('usuarios.editar')
                            <th class="p-3"></th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($this->usuarios as $usuario)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors {{ $usuario->deleted_at ? 'opacity-50' : '' }}">
                            <td class="p-3">
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-700 dark:text-indigo-300 font-semibold text-xs">
                                        {{ $usuario->initials() }}
                                    </div>
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ $usuario->name }}</span>
                                    @if($usuario->id === auth()->id())
                                        <flux:badge color="indigo" size="sm">{{ __('Tú') }}</flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="p-3 text-zinc-600 dark:text-zinc-400">{{ $usuario->email }}</td>
                            <td class="p-3 text-center">
                                @if($usuario->roles->isNotEmpty())
                                    <flux:badge color="purple">{{ ucfirst($usuario->roles->first()->name) }}</flux:badge>
                                @else
                                    <span class="text-zinc-400 text-xs">—</span>
                                @endif
                            </td>
                            <td class="p-3 text-center">
                                @if($usuario->deleted_at)
                                    <flux:badge color="red">{{ __('Eliminado') }}</flux:badge>
                                @elseif($usuario->activo)
                                    <flux:badge color="green">{{ __('Activo') }}</flux:badge>
                                @else
                                    <flux:badge color="orange">{{ __('Inactivo') }}</flux:badge>
                                @endif
                            </td>
                            <td class="p-3 text-center text-zinc-500 text-xs">
                                {{ $usuario->created_at->format('d/m/Y') }}
                            </td>
                            @can('usuarios.editar')
                                <td class="p-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @if($usuario->deleted_at)
                                            <flux:button
                                                variant="ghost"
                                                icon="arrow-path"
                                                size="sm"
                                                wire:click="restaurar({{ $usuario->id }})"
                                                wire:confirm="¿Restaurar a este usuario?"
                                                title="{{ __('Restaurar') }}"
                                            />
                                        @else
                                            <flux:button
                                                variant="ghost"
                                                icon="key"
                                                size="sm"
                                                wire:click="abrirCambiarPassword({{ $usuario->id }})"
                                                title="{{ __('Cambiar contraseña') }}"
                                            />
                                            <flux:button
                                                variant="ghost"
                                                icon="pencil-square"
                                                size="sm"
                                                wire:click="editar({{ $usuario->id }})"
                                                title="{{ __('Editar') }}"
                                            />
                                            @if($usuario->id !== auth()->id())
                                                <flux:button
                                                    variant="ghost"
                                                    icon="{{ $usuario->activo ? 'pause-circle' : 'play-circle' }}"
                                                    size="sm"
                                                    class="{{ $usuario->activo ? 'text-orange-500 hover:text-orange-600' : 'text-green-500 hover:text-green-600' }}"
                                                    wire:click="toggleActivo({{ $usuario->id }})"
                                                    wire:confirm="{{ $usuario->activo ? '¿Desactivar este usuario?' : '¿Activar este usuario?' }}"
                                                    title="{{ $usuario->activo ? __('Desactivar') : __('Activar') }}"
                                                />
                                                <flux:button
                                                    variant="ghost"
                                                    icon="trash"
                                                    size="sm"
                                                    class="text-red-500 hover:text-red-600"
                                                    wire:click="eliminar({{ $usuario->id }})"
                                                    wire:confirm="¿Eliminar este usuario? Podrás restaurarlo después."
                                                    title="{{ __('Eliminar (soft delete)') }}"
                                                />
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            @endcan
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-zinc-500">
                                <flux:icon.users class="size-10 mx-auto mb-2 opacity-30" />
                                {{ __('No se encontraron usuarios.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-zinc-200 dark:border-zinc-800 text-xs text-zinc-400">
            {{ $this->usuarios->count() }} {{ __('usuario(s) encontrado(s)') }}
        </div>
    </div>

    {{-- Modal Cambiar Contraseña --}}
    <flux:modal name="cambiar-password" class="w-full max-w-md">
        <flux:heading>{{ __('Cambiar Contraseña') }}</flux:heading>
        <flux:subheading>{{ __('Ingresa la nueva contraseña para este usuario.') }}</flux:subheading>

        <form wire:submit.prevent="cambiarPassword" class="mt-4 space-y-4">
            <flux:field>
                <flux:label>{{ __('Nueva contraseña') }}</flux:label>
                <flux:input wire:model="nueva_password" type="password" placeholder="Mínimo 8 caracteres" />
                <flux:error name="nueva_password" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Confirmar contraseña') }}</flux:label>
                <flux:input wire:model="nueva_password_confirmation" type="password" placeholder="Repite la nueva contraseña" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" icon="lock-closed">
                    {{ __('Actualizar Contraseña') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
