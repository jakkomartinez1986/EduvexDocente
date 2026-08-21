<?php

use App\Models\Security\Authorizations\Role;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Usuario')] class extends Component {
    public ?User $user = null;
    public string $name = '';
    public string $lastname = '';
    public string $dni = '';
    public string $phone = '';
    public string $cellphone = '';
    public string $address = '';
    public string $email = '';
    public int $status = 1;
    public array $selectedRoles = [];

    public function mount(int $id): void
    {
        $this->user = User::findOrFail($id);

        $this->fill([
            'name' => $this->user->name,
            'lastname' => $this->user->lastname,
            'dni' => $this->user->dni,
            'phone' => $this->user->phone ?? '',
            'cellphone' => $this->user->cellphone ?? '',
            'address' => $this->user->address ?? '',
            'email' => $this->user->email,
            'status' => $this->user->status,
        ]);

        $this->selectedRoles = $this->user->roles->pluck('id')->toArray();
    }

    public function getAvailableRolesProperty()
    {
        $excludedRoles = ['SUPER-ADMIN', 'DOCENTE', 'TUTOR', 'REPRESENTANTE', 'ESTUDIANTE'];
        $query = Role::orderBy('name');

        if ($this->user && $this->user->hasRole('SUPER-ADMIN')) {
            return $query->get();
        }

        return $query->whereNotIn('name', $excludedRoles)->get();
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'max:10', 'unique:users,dni,' . $this->user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'cellphone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $this->user->id],
            'status' => ['required', 'integer', 'in:0,1'],
            'selectedRoles' => ['required', 'array', 'min:1'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'lastname' => 'apellido',
            'dni' => 'DNI',
            'email' => 'email',
            'status' => 'estado',
            'selectedRoles' => 'roles',
        ];
    }

    public function update(): void
    {
        $this->validate();

        $this->user->update([
            'name' => $this->name,
            'lastname' => $this->lastname,
            'dni' => $this->dni,
            'phone' => $this->phone,
            'cellphone' => $this->cellphone,
            'address' => $this->address,
            'email' => $this->email,
            'status' => $this->status,
        ]);

        $excludedRoles = ['SUPER-ADMIN', 'DOCENTE', 'TUTOR', 'REPRESENTANTE', 'ESTUDIANTE'];
        $roleIds = Role::whereIn('id', $this->selectedRoles)
            ->whereNotIn('name', $excludedRoles)
            ->pluck('id')
            ->toArray();
        $this->user->syncRoles($roleIds);

        Flux::toast(variant: 'success', text: __('Usuario actualizado correctamente.'));
        $this->redirect(route('system.identity.users.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Usuario') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Actualizar informacion del usuario') }}</flux:text>
        </div>
        <flux:button href="{{ route('system.identity.users.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('system.identity.users.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Usuarios') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Editar') }}</span>
    </nav>

    <form wire:submit.prevent="update" class="space-y-6 max-w-3xl">
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
            <flux:heading size="md" class="mb-4">{{ __('Roles') }}</flux:heading>
            <flux:field>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach($this->availableRoles as $role)
                        <label class="flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition
                            {{ in_array($role->id, $selectedRoles)
                                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-400'
                                : 'border-zinc-200 dark:border-zinc-700 hover:border-zinc-300 dark:hover:border-zinc-600' }}">
                            <input type="checkbox" wire:model.live="selectedRoles" value="{{ $role->id }}"
                                   class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500" />
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $role->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('selectedRoles') <flux:description class="text-red-500 mt-2">{{ $message }}</flux:description> @enderror
            </flux:field>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Actualizar Usuario') }}</flux:button>
            <flux:button href="{{ route('system.identity.users.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
