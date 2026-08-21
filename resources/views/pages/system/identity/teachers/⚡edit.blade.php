<?php

declare(strict_types=1);

use App\Models\Identity\Users\Teacher;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Docente')] class extends Component {
    public ?Teacher $teacher = null;
    public string $name = '';
    public string $lastname = '';
    public string $dni = '';
    public string $phone = '';
    public string $cellphone = '';
    public string $address = '';
    public string $email = '';
    public int $status = 1;

    public ?string $specialization = null;
    public ?string $title = null;
    public ?string $education_level = null;
    public ?string $hire_date = null;

    public function mount(int $id): void
    {
        $this->teacher = Teacher::with('user')->findOrFail($id);
        $user = $this->teacher->user;

        $this->fill([
            'name' => $user->name,
            'lastname' => $user->lastname,
            'dni' => $user->dni,
            'phone' => $user->phone ?? '',
            'cellphone' => $user->cellphone ?? '',
            'address' => $user->address ?? '',
            'email' => $user->email,
            'status' => $user->status,
            'specialization' => $this->teacher->specialization,
            'title' => $this->teacher->title,
            'education_level' => $this->teacher->education_level,
            'hire_date' => $this->teacher->hire_date?->format('Y-m-d'),
        ]);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'max:10', 'unique:users,dni,' . $this->teacher->user_id],
            'phone' => ['nullable', 'string', 'max:20'],
            'cellphone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $this->teacher->user_id],
            'status' => ['required', 'integer', 'in:0,1'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'education_level' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'lastname' => 'apellido',
            'dni' => 'DNI',
            'email' => 'email',
        ];
    }

    public function update(): void
    {
        $this->validate();

        $this->teacher->user->update([
            'name' => $this->name,
            'lastname' => $this->lastname,
            'dni' => $this->dni,
            'phone' => $this->phone,
            'cellphone' => $this->cellphone,
            'address' => $this->address,
            'email' => $this->email,
            'status' => $this->status,
        ]);

        $this->teacher->update([
            'specialization' => $this->specialization,
            'title' => $this->title,
            'education_level' => $this->education_level,
            'hire_date' => $this->hire_date ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('Docente actualizado correctamente.'));
        $this->redirect(route('system.identity.teachers.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Docente') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Actualizar informacion del docente') }}</flux:text>
        </div>
        <flux:button href="{{ route('system.identity.teachers.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('system.identity.teachers.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Docentes') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Editar') }}</span>
    </nav>

    <form wire:submit="update" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion Personal') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Nombre') }} *</flux:label>
                    <flux:input wire:model="name" placeholder="NOMBRE" />
                    @error('name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Apellido') }} *</flux:label>
                    <flux:input wire:model="lastname" placeholder="APELLIDO" />
                    @error('lastname') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('DNI') }} *</flux:label>
                    <flux:input wire:model="dni" maxlength="10" />
                    @error('dni') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Email') }} *</flux:label>
                    <flux:input wire:model="email" type="email" />
                    @error('email') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Telefono') }}</flux:label>
                    <flux:input wire:model="phone" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Celular') }}</flux:label>
                    <flux:input wire:model="cellphone" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Direccion') }}</flux:label>
                <flux:input wire:model="address" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Estado') }}</flux:label>
                <flux:radio.group wire:model="status" layout="row">
                    <flux:radio value="1">{{ __('Activo') }}</flux:radio>
                    <flux:radio value="0">{{ __('Inactivo') }}</flux:radio>
                </flux:radio.group>
            </flux:field>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion Docente') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Codigo') }}</flux:label>
                    <flux:input :value="$this->teacher->teacher_code" disabled />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Especializacion') }}</flux:label>
                    <flux:input wire:model="specialization" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Titulo') }}</flux:label>
                    <flux:input wire:model="title" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Nivel Educativo') }}</flux:label>
                    <flux:input wire:model="education_level" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Fecha de Ingreso') }}</flux:label>
                    <flux:input wire:model="hire_date" type="date" />
                </flux:field>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Actualizar Docente') }}</flux:button>
            <flux:button href="{{ route('system.identity.teachers.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
