<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Name')"
            />

            <!-- Lastname -->
            <flux:input
                name="lastname"
                :label="__('Lastname')"
                :value="old('lastname')"
                type="text"
                required
                autocomplete="family-name"
                :placeholder="__('Lastname')"
            />

            <!-- DNI -->
            <flux:input
                name="dni"
                :label="__('DNI')"
                :value="old('dni')"
                type="text"
                required
                autocomplete="off"
                placeholder="12345678"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Phone -->
            <flux:input
                name="phone"
                :label="__('Phone')"
                :value="old('phone')"
                type="tel"
                autocomplete="tel"
                placeholder="+593 234-5678"
            />

            <!-- Cellphone -->
            <flux:input
                name="cellphone"
                :label="__('Cellphone')"
                :value="old('cellphone')"
                type="tel"
                autocomplete="tel"
                placeholder="+593 1234-5678"
            />

            <!-- Address -->
            <flux:textarea
                name="address"
                :label="__('Address')"
                :value="old('address')"
                :placeholder="__('Street, number, city, province')"
                rows="2"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
            

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
