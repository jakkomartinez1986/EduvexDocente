<?php

use App\Concerns\PasswordValidationRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cambiar contraseña')] #[Layout('layouts.auth', ['title' => 'Cambiar contraseña'])] class extends Component {
    use PasswordValidationRules;

    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        if (!Auth::check() || !Auth::user()->must_change_password) {
            $this->redirectRoute('dashboard');
        }
    }

    public function changePassword(): void
    {
        $validated = $this->validate([
            'password' => $this->passwordRules(),
        ]);

        $user = Auth::user();
        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        Flux::toast(variant: 'success', text: 'Contraseña actualizada correctamente.');

        $this->redirectRoute('dashboard');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Cambiar contraseña')"
        :description="__('Debes cambiar tu contraseña para continuar. Ingresa una nueva contraseña segura.')"
    />

    <form wire:submit="changePassword" class="flex flex-col gap-6">
        <flux:input
            wire:model="password"
            :label="__('Nueva contraseña')"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="__('Nueva contraseña')"
            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
            viewable
        />

        <flux:input
            wire:model="password_confirmation"
            :label="__('Confirmar contraseña')"
            type="password"
            required
            autocomplete="new-password"
            :placeholder="__('Confirmar contraseña')"
            viewable
        />

        <flux:button variant="primary" type="submit" class="w-full" data-test="change-password-button">
            {{ __('Guardar contraseña') }}
        </flux:button>
    </form>
</div>
