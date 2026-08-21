<?php

declare(strict_types=1);

use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\Student;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Crear Representante')] class extends Component {
    public string $name = '';
    public string $lastname = '';
    public string $dni = '';
    public string $phone = '';
    public string $cellphone = '';
    public string $address = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public int $status = 0;

    public ?int $student_id = null;
    public ?string $occupation = null;
    public ?string $relationship = null;
    public ?string $work_phone = null;
    public ?string $geolocation_info = null;

    public string $studentSearch = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'max:10', 'unique:users,dni'],
            'phone' => ['nullable', 'string', 'max:20'],
            'cellphone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', 'integer', 'in:0,1'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'work_phone' => ['nullable', 'string', 'max:20'],
            'geolocation_info' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'lastname' => 'apellido',
            'dni' => 'DNI',
            'email' => 'email',
            'password' => 'contrasena',
            'student_id' => 'estudiante',
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function studentsProperty(): \Illuminate\Support\Collection
    {
        return Student::with('user')
            ->whereHas('user', fn ($q) =>
                $q->where('name', 'like', "%{$this->studentSearch}%")
                    ->orWhere('lastname', 'like', "%{$this->studentSearch}%")
                    ->orWhere('dni', 'like', "%{$this->studentSearch}%")
            )
            ->orWhere('student_code', 'like', "%{$this->studentSearch}%")
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'label' => "{$s->student_code} - {$s->user?->fullname} (DNI: {$s->user?->dni})",
            ]);
    }

    public function save(): void
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'lastname' => $this->lastname,
            'dni' => $this->dni,
            'phone' => $this->phone,
            'cellphone' => $this->cellphone,
            'address' => $this->address,
            'email' => $this->email,
            'password' => $this->password,
            'status' => $this->status,
            'must_change_password' => true,
        ]);

        Representative::create([
            'user_id' => $user->id,
            'student_id' => $this->student_id,
            'occupation' => $this->occupation,
            'relationship' => $this->relationship,
            'work_phone' => $this->work_phone,
            'geolocation_info' => $this->geolocation_info,
        ]);

        Flux::toast(variant: 'success', text: __('Representante creado correctamente. El administrador debe activar la cuenta.'));
        $this->redirect(route('system.identity.representatives.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Crear Representante') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Registrar un nuevo representante en el sistema') }}</flux:text>
        </div>
        <flux:button href="{{ route('system.identity.representatives.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('system.identity.representatives.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Representantes') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Crear') }}</span>
    </nav>

    <form wire:submit="save" class="space-y-6 max-w-3xl">
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
                    <flux:input wire:model="dni" placeholder="1721583092" maxlength="10" />
                    @error('dni') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Email') }} *</flux:label>
                    <flux:input wire:model="email" type="email" placeholder="correo@ejemplo.com" />
                    @error('email') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Telefono') }}</flux:label>
                    <flux:input wire:model="phone" placeholder="022345678" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Celular') }}</flux:label>
                    <flux:input wire:model="cellphone" placeholder="0991234567" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Direccion') }}</flux:label>
                <flux:input wire:model="address" placeholder="{{ __('Direccion completa') }}" />
            </flux:field>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Contrasena') }} *</flux:label>
                    <flux:input wire:model="password" type="password" placeholder="{{ __('Minimo 8 caracteres') }}" />
                    @error('password') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Confirmar Contrasena') }} *</flux:label>
                    <flux:input wire:model="password_confirmation" type="password" placeholder="{{ __('Repetir contrasena') }}" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Estado') }}</flux:label>
                <flux:radio.group wire:model="status" layout="row">
                    <flux:radio value="1">{{ __('Activo') }}</flux:radio>
                    <flux:radio value="0">{{ __('Inactivo') }}</flux:radio>
                </flux:radio.group>
            </flux:field>
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion Representante') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field class="sm:col-span-2">
                    <flux:label>{{ __('Estudiante') }} *</flux:label>
                    <flux:input wire:model.live="studentSearch" :placeholder="__('Buscar estudiante por nombre, DNI o codigo...')" icon="magnifying-glass" />
                    <select wire:model="student_id" class="mt-1 w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-sm">
                        <option value="">{{ __('Seleccione un estudiante') }}</option>
                        @foreach ($this->students as $student)
                            <option value="{{ $student['id'] }}">{{ $student['label'] }}</option>
                        @endforeach
                    </select>
                    @error('student_id') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Parentesco') }}</flux:label>
                    <flux:input wire:model="relationship" placeholder="{{ __('Madre, Padre, Tutor...') }}" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Ocupacion') }}</flux:label>
                    <flux:input wire:model="occupation" placeholder="{{ __('Ingeniera, Docente...') }}" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Telefono Laboral') }}</flux:label>
                    <flux:input wire:model="work_phone" placeholder="022345678" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Geolocalizacion') }}</flux:label>
                    <flux:input wire:model="geolocation_info" placeholder="{{ __('Direccion o coordenadas') }}" />
                </flux:field>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Guardar Representante') }}</flux:button>
            <flux:button href="{{ route('system.identity.representatives.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
