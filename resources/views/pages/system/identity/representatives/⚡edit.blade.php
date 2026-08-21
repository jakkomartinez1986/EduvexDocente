<?php

declare(strict_types=1);

use App\Models\Identity\Users\Representative;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Representante')] class extends Component {
    public ?Representative $representative = null;
    public string $name = '';
    public string $lastname = '';
    public string $dni = '';
    public string $phone = '';
    public string $cellphone = '';
    public string $address = '';
    public string $email = '';
    public int $status = 1;

    public ?int $student_id = null;
    public ?string $occupation = null;
    public ?string $relationship = null;
    public ?string $work_phone = null;
    public ?string $geolocation_info = null;

    public string $studentSearch = '';

    public function mount(int $id): void
    {
        $this->representative = Representative::with(['user', 'student'])->findOrFail($id);
        $user = $this->representative->user;

        $this->fill([
            'name' => $user->name,
            'lastname' => $user->lastname,
            'dni' => $user->dni,
            'phone' => $user->phone ?? '',
            'cellphone' => $user->cellphone ?? '',
            'address' => $user->address ?? '',
            'email' => $user->email,
            'status' => $user->status,
            'student_id' => $this->representative->student_id,
            'occupation' => $this->representative->occupation,
            'relationship' => $this->representative->relationship,
            'work_phone' => $this->representative->work_phone,
            'geolocation_info' => $this->representative->geolocation_info,
        ]);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'dni' => ['required', 'string', 'max:10', 'unique:users,dni,' . $this->representative->user_id],
            'phone' => ['nullable', 'string', 'max:20'],
            'cellphone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $this->representative->user_id],
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
            'student_id' => 'estudiante',
        ];
    }

    #[\Livewire\Attributes\Computed]
    public function studentsProperty(): \Illuminate\Support\Collection
    {
        return \App\Models\Identity\Users\Student::with('user')
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

    public function update(): void
    {
        $this->validate();

        $this->representative->user->update([
            'name' => $this->name,
            'lastname' => $this->lastname,
            'dni' => $this->dni,
            'phone' => $this->phone,
            'cellphone' => $this->cellphone,
            'address' => $this->address,
            'email' => $this->email,
            'status' => $this->status,
        ]);

        $this->representative->update([
            'student_id' => $this->student_id,
            'occupation' => $this->occupation,
            'relationship' => $this->relationship,
            'work_phone' => $this->work_phone,
            'geolocation_info' => $this->geolocation_info,
        ]);

        Flux::toast(variant: 'success', text: __('Representante actualizado correctamente.'));
        $this->redirect(route('system.identity.representatives.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Representante') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Actualizar informacion del representante') }}</flux:text>
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
                    <flux:input wire:model="relationship" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Ocupacion') }}</flux:label>
                    <flux:input wire:model="occupation" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Telefono Laboral') }}</flux:label>
                    <flux:input wire:model="work_phone" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Geolocalizacion') }}</flux:label>
                    <flux:input wire:model="geolocation_info" />
                </flux:field>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Actualizar Representante') }}</flux:button>
            <flux:button href="{{ route('system.identity.representatives.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
