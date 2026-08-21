<?php

use App\Concerns\ProfileValidationRules;
/* @chisel-email-verification */
use Illuminate\Contracts\Auth\MustVerifyEmail;
/* @end-chisel-email-verification */
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

   public string $name = '';
    public string $lastname = '';
    public string $dni = '';
    public string $phone = '';
    public string $cellphone = '';
    public string $address = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->lastname = $user->lastname;
        $this->dni = $user->dni;
        $this->phone = $user->phone ?? '';
        $this->cellphone = $user->cellphone ?? '';
        $this->address = $user->address ?? '';
        $this->email = $user->email;
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

    /* @chisel-email-verification */
    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

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
    #[Computed]
     public function UserRoles(): array
    {
        return Auth::user()->roles->pluck('name')->toArray();
    }
    /* @end-chisel-email-verification */
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />
                <flux:input wire:model="lastname" :label="__('Lastname')" type="text" required autocomplete="family-name" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="dni" :label="__('DNI')" type="text" required />
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="phone" :label="__('Phone')" type="tel" />
                <flux:input wire:model="cellphone" :label="__('Cellphone')" type="tel" />
            </div>

            <flux:textarea wire:model="address" :label="__('Address')" rows="2" />

            <div>
                <flux:field>
                    <flux:label>{{ __('Roles') }}</flux:label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        @forelse ($this->userRoles as $role)
                            <flux:badge size="sm" variant="outline">{{ $role }}</flux:badge>
                        @empty
                            <flux:text variant="subtle" class="text-sm">{{ __('No roles assigned') }}</flux:text>
                        @endforelse
                    </div>
                    <flux:description>{{ __('Roles are managed by administrators and cannot be changed here.') }}</flux:description>
                </flux:field>
            </div>

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

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </div>
        </form>

        {{-- @chisel-email-verification --}}
        @if ($this->showDeleteUser)
        {{-- @end-chisel-email-verification --}}
            <livewire:pages::settings.delete-user-form />
        {{-- @chisel-email-verification --}}
        @endif
        {{-- @end-chisel-email-verification --}}
    </x-pages::settings.layout>
</section>
