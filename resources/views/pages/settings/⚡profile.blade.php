<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('admin.dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        <div class="my-10 border-t border-zinc-200 dark:border-zinc-700 pt-6">
            <flux:heading size="lg">{{ __('Roles y Permisos') }}</flux:heading>
            <flux:subheading>{{ __('Tus niveles de acceso en el sistema') }}</flux:subheading>

            <div class="mt-6 space-y-4">
                <div>
                    <span class="text-sm font-medium text-zinc-500 uppercase">{{ __('Rol Actual') }}</span>
                    <div class="mt-2 flex gap-2">
                        @forelse(auth()->user()->roles as $role)
                            <flux:badge color="primary" class="capitalize">{{ $role->name }}</flux:badge>
                        @empty
                            <span class="text-zinc-500 text-sm">{{ __('Sin rol asignado') }}</span>
                        @endforelse
                    </div>
                </div>
                
                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <span class="text-sm font-medium text-zinc-500 uppercase mb-2 block">{{ __('Permisos Autorizados') }}</span>
                    <div class="flex flex-wrap gap-2">
                        @forelse(auth()->user()->getAllPermissions() as $permission)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ $permission->name }}
                            </span>
                        @empty
                            <span class="text-zinc-500 text-sm">{{ __('No cuentas con permisos adicionales.') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        @if ($this->showDeleteUser)
            <div class="my-10 border-t border-zinc-200 dark:border-zinc-700 pt-6">
                <livewire:pages::settings.delete-user-form />
            </div>
        @endif
    </x-pages::settings.layout>
</section>
