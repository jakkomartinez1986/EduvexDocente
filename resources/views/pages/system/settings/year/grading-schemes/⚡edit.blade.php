<?php

declare(strict_types=1);

use App\Models\Setting\YearSettings\GradingScheme;
use App\Models\Setting\YearSettings\ScolarYear;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Editar Esquema de Calificacion')] class extends Component {
    public ?GradingScheme $gradingScheme = null;
    public ?int $year_id = null;
    public float $formative_percentage = 0.00;
    public float $summative_percentage = 0.00;
    public float $exam_percentage = 0.00;
    public float $project_percentage = 0.00;

    public function mount(int $id): void
    {
        $this->gradingScheme = GradingScheme::findOrFail($id);

        $this->fill([
            'year_id' => $this->gradingScheme->year_id,
            'formative_percentage' => $this->gradingScheme->formative_percentage,
            'summative_percentage' => $this->gradingScheme->summative_percentage,
            'exam_percentage' => $this->gradingScheme->exam_percentage,
            'project_percentage' => $this->gradingScheme->project_percentage,
        ]);
    }

    public function getYearsProperty()
    {
        return ScolarYear::query()->orderBy('year_name', 'desc')->get();
    }

    protected function rules(): array
    {
        return [
            'year_id' => ['required', 'exists:scolar_years,id', 'unique:grading_schemes,year_id,' . $this->gradingScheme->id],
            'formative_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'summative_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'exam_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'project_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'year_id' => 'ano escolar',
            'formative_percentage' => 'porcentaje formativo',
            'summative_percentage' => 'porcentaje sumativo',
            'exam_percentage' => 'porcentaje de examen',
            'project_percentage' => 'porcentaje de proyecto',
        ];
    }

    public function update(): void
    {
        $this->validate();

        $this->gradingScheme->update([
            'year_id' => $this->year_id,
            'formative_percentage' => $this->formative_percentage,
            'summative_percentage' => $this->summative_percentage,
            'exam_percentage' => $this->exam_percentage,
            'project_percentage' => $this->project_percentage,
        ]);

        Flux::toast(variant: 'success', text: __('Esquema de calificacion actualizado correctamente.'));
        $this->redirect(route('admin.settings.grading-schemes.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Editar Esquema de Calificacion') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Actualizar informacion del esquema de calificacion') }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.settings.grading-schemes.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('admin.settings.grading-schemes.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Esquemas de Calificacion') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Editar') }}</span>
    </nav>

    <form wire:submit="update" class="space-y-6 max-w-3xl">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="md" class="mb-4">{{ __('Informacion del Esquema') }}</flux:heading>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Ano Escolar') }} *</flux:label>
                    <flux:select wire:model="year_id" placeholder="{{ __('Seleccione un ano escolar...') }}">
                        @foreach ($this->years as $year)
                            <flux:select.option value="{{ $year->id }}">{{ $year->year_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('year_id') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                </flux:field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Porcentaje Formativo') }} *</flux:label>
                        <flux:input type="number" wire:model="formative_percentage" step="0.01" min="0" max="100" />
                        @error('formative_percentage') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Porcentaje Sumativo') }} *</flux:label>
                        <flux:input type="number" wire:model="summative_percentage" step="0.01" min="0" max="100" />
                        @error('summative_percentage') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Porcentaje de Examen') }} *</flux:label>
                        <flux:input type="number" wire:model="exam_percentage" step="0.01" min="0" max="100" />
                        @error('exam_percentage') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Porcentaje de Proyecto') }} *</flux:label>
                        <flux:input type="number" wire:model="project_percentage" step="0.01" min="0" max="100" />
                        @error('project_percentage') <flux:description class="text-red-500">{{ $message }}</flux:description> @enderror
                    </flux:field>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Actualizar Esquema') }}</flux:button>
            <flux:button href="{{ route('admin.settings.grading-schemes.index') }}" wire:navigate variant="ghost">{{ __('Cancelar') }}</flux:button>
        </div>
    </form>
</div>
