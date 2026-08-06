<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new #[Title('Editar Usuario')] class extends Component {
    public User $usuario;
    public string $nombre = '';
    public string $email = '';
    public string $rol = '';
    public bool $activo = true;

    public function mount(User $usuario)
    {
        if ($usuario->is_super_admin) {
            abort(403, 'Este usuario no puede ser editado.');
        }

        $this->usuario = $usuario;
        $this->nombre = $usuario->name;
        $this->email = $usuario->email;
        $this->rol = $usuario->roles->first()?->name ?? '';
        $this->activo = $usuario->activo;
    }

    public function roles()
    {
        return Role::orderBy('name')->get();
    }

    public function guardar()
    {
        if (! auth()->user()->can('usuarios.editar')) {
            abort(403);
        }

        $this->validate([
            'nombre' => 'required|string|max:255',
            'email'  => ['required', 'email', Rule::unique('users', 'email')->ignore($this->usuario->id)->whereNull('deleted_at')],
            'rol'    => 'required|exists:roles,name',
        ]);

        $this->usuario->update([
            'name'   => $this->nombre,
            'email'  => $this->email,
            'activo' => $this->activo,
        ]);
        
        $this->usuario->syncRoles([$this->rol]);

        Flux::toast(variant: 'success', text: 'Usuario actualizado correctamente.');
        return redirect()->route('admin.usuarios.index');
    }
}; ?>

<div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Editar Usuario') }}</flux:heading>
            <flux:subheading>{{ __('Modifica los datos del usuario: ') }} {{ $usuario->name }}</flux:subheading>
        </div>
        <flux:button variant="ghost" icon="arrow-left" href="{{ route('admin.usuarios.index') }}" wire:navigate>
            {{ __('Volver') }}
        </flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6 shadow-sm">
        <form wire:submit.prevent="guardar" class="grid grid-cols-1 md:grid-cols-2 gap-6">
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

            <flux:field>
                <flux:label>{{ __('Rol') }}</flux:label>
                <flux:select wire:model="rol" placeholder="Selecciona un rol">
                    @foreach($this->roles() as $r)
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

            <div class="md:col-span-2 flex justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button variant="ghost" href="{{ route('admin.usuarios.index') }}" wire:navigate>{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" type="submit" icon="check">
                    {{ __('Actualizar Usuario') }}
                </flux:button>
            </div>
        </form>
    </div>
</div>
