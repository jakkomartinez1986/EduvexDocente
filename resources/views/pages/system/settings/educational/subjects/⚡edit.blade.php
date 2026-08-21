<?php

declare(strict_types=1);

use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Subject;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Asignatura')] class extends Component {
    public ?Subject $subject = null;
    public string $subject_name = '';
    public ?int $area_id = null;

    public function mount(int $id): void
    {
        $this->subject = Subject::findOrFail($id);

        $this->fill([
            'subject_name' => $this->subject->subject_name,
            'area_id' => $this->subject->area_id,
        ]);
    }

    public function getAreasProperty()
    {
        return Area::query()->orderBy('area_name')->get();
    }

    protected function rules(): array
    {
        return [
            'subject_name' => ['required', 'string', 'max:150', 'unique:subjects,subject_name,' . $this->subject->id],
            'area_id' => ['required', 'exists:areas,id'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'subject_name' => 'nombre de la asignatura',
            'area_id' => 'area',
        ];
    }

    public function update(): void
    {
        $this->validate();

        $this->subject->update([
            'subject_name' => $this->subject_name,
            'area_id' => $this->area_id,
        ]);

        Flux::toast(variant: 'success', text: __('Asignatura actualizada correctamente.'));
        $this->redirect(route('admin.settings.subjects.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Asignatura') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Actualizar informacion de la asignatura') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.settings.subjects.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.subjects.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Asignaturas') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Editar') }}</span>
    </nav>

    <form wire:submit="update" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion de la Asignatura') }}</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Nombre') }} *</flux:label>
                    <flux:input wire:model="subject_name" />
                    @error('subject_name') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Area') }} *</flux:label>
                    <flux:select wire:model="area_id" placeholder="{{ __('Seleccione un area...') }}">
                        @foreach ($this->areas as $area)
                            <flux:select.option value="{{ $area->id }}">{{ $area->area_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('area_id') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Actualizar Asignatura') }}</flux:button>
            <flux:button href="{{ route('admin.settings.subjects.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
