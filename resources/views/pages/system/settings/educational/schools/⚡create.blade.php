<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\School;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Crear Colegio')] class extends Component {
    public string $name_school = '';
    public string $distrit = '';
    public string $location = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $website = '';

    protected function rules(): array
    {
        return [
            'name_school' => ['required', 'string', 'max:255'],
            'distrit' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name_school' => 'nombre del colegio',
            'distrit' => 'distrito',
            'location' => 'ubicacion',
            'address' => 'direccion',
            'phone' => 'telefono',
            'email' => 'email',
            'website' => 'sitio web',
        ];
    }

    public function save(): void
    {
        $this->validate();

        School::create([
            'name_school' => $this->name_school,
            'distrit' => $this->distrit ?: null,
            'location' => $this->location ?: null,
            'address' => $this->address ?: null,
            'phone' => $this->phone ?: null,
            'email' => $this->email ?: null,
            'website' => $this->website ?: null,
            'status' => 1,
        ]);

        Flux::toast(variant: 'success', text: __('Colegio creado correctamente.'));
        $this->redirect(route('admin.schools.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Crear Colegio') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Registrar una nueva institucion educativa') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.schools.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.schools.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Colegios') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Crear') }}</span>
    </nav>

    <form wire:submit="save" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion del Colegio') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Nombre') }} *</flux:label>
                    <flux:input wire:model="name_school" placeholder="COLEGIO NACIONAL" />
                    @error('name_school') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Distrito') }}</flux:label>
                    <flux:input wire:model="distrit" placeholder="{{ __('Distrito') }}" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Telefono') }}</flux:label>
                    <flux:input wire:model="phone" placeholder="022345678" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input wire:model="email" type="email" placeholder="correo@ejemplo.com" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Sitio Web') }}</flux:label>
                    <flux:input wire:model="website" placeholder="https://ejemplo.com" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Ubicacion') }}</flux:label>
                    <flux:input wire:model="location" placeholder="{{ __('Ubicacion geografica') }}" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Direccion') }}</flux:label>
                <flux:input wire:model="address" placeholder="{{ __('Direccion completa') }}" />
            </flux:field>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Guardar Colegio') }}</flux:button>
            <flux:button href="{{ route('admin.schools.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
