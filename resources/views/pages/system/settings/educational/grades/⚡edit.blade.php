<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Grado')] class extends Component {
    public ?Grade $grade = null;
    public string $grade_name = '';
    public ?string $section = null;
    public int $nivel_id = 0;

    public function mount(int $id): void
    {
        $this->grade = Grade::findOrFail($id);

        $this->fill([
            'grade_name' => $this->grade->grade_name,
            'section' => $this->grade->section ?? '',
            'nivel_id' => $this->grade->nivel_id,
        ]);
    }

    public function getNivelesProperty()
    {
        return Nivel::query()
            ->with('shift')
            ->where('status', 1)
            ->orderBy('nivel_name')
            ->get();
    }

    protected function rules(): array
    {
        return [
            'grade_name' => ['required', 'string', 'max:100'],
            'section' => ['nullable', 'string', 'max:10'],
            'nivel_id' => ['required', 'exists:nivels,id'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'grade_name' => 'nombre del grado',
            'section' => 'seccion',
            'nivel_id' => 'nivel',
        ];
    }

    public function update(): void
    {
        $this->validate();

        $this->grade->update([
            'grade_name' => $this->grade_name,
            'section' => $this->section ?: null,
            'nivel_id' => $this->nivel_id,
        ]);

        Flux::toast(variant: 'success', text: __('Grado actualizado correctamente.'));
        $this->redirect(route('admin.settings.grades.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Grado') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Actualizar informacion del grado') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.settings.grades.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.grades.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Grados') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Editar') }}</span>
    </nav>

    <form wire:submit="update" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion del Grado') }}</flux:heading>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Nombre') }} *</flux:label>
                    <flux:input wire:model="grade_name" />
                    @error('grade_name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Seccion') }}</flux:label>
                    <flux:input wire:model="section" />
                    @error('section') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Nivel') }} *</flux:label>
                <flux:select wire:model="nivel_id">
                    @foreach ($this->niveles as $nivel)
                        <flux:select.option value="{{ $nivel->id }}">{{ $nivel->nivel_name }} - {{ $nivel->shift->shift_name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @error('nivel_id') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
            </flux:field>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Actualizar Grado') }}</flux:button>
            <flux:button href="{{ route('admin.settings.grades.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
