<?php

declare(strict_types=1);

use App\Models\Identity\Users\Student;
use App\Models\TeacherManagement\Attendances\Attendance;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\TemporaryUploadedFile;

new #[Title('Justificar Inasistencias')] class extends Component {
    use WithFileUploads;

    public int $id;
    public ?Student $student = null;
    public array $attendanceRecords = [];
    public array $justifications = [];
    public array $files = [];

    public function mount(int $id): void
    {
        $this->student = Student::with('user')->findOrFail($id);

        $attendances = Attendance::where('student_id', $id)
            ->where('status', 'I')
            ->orderBy('date', 'desc')
            ->get();

        $this->attendanceRecords = $attendances->toArray();

        foreach ($attendances as $att) {
            $this->justifications[$att->id] = $att->justification ?? '';
            $this->files[$att->id] = null;
        }
    }

    public function justify(int $attendanceId): void
    {
        $attendance = Attendance::findOrFail($attendanceId);

        $rules = [
            "justifications.$attendanceId" => ['nullable', 'string', 'max:500'],
            "files.$attendanceId" => ['nullable', 'file', 'max:2048', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ];

        $this->validate($rules);

        $path = $attendance->justification_file_path;

        if ($this->files[$attendanceId]) {
            $path = $this->files[$attendanceId]->store('justifications', 'public');
        }

        $attendance->update([
            'status' => 'J',
            'justification' => $this->justifications[$attendanceId] ?: null,
            'justification_file_path' => $path,
            'recorded_by' => auth()->id(),
        ]);

        Flux::toast(
            variant: 'success',
            text: __('Inasistencia justificada correctamente.')
        );

        $this->redirect(route('admin.teacher.tutor-justifications.show', $this->id), navigate: true);
    }

    public function justifyMultiple(): void
    {
        $selectedIds = collect($this->attendanceRecords)
            ->pluck('id')
            ->toArray();

        $count = 0;

        foreach ($selectedIds as $attendanceId) {
            $attendance = Attendance::find($attendanceId);
            if (!$attendance || trim($attendance->status) !== 'I') continue;

            $rules = [
                "justifications.$attendanceId" => ['nullable', 'string', 'max:500'],
                "files.$attendanceId" => ['nullable', 'file', 'max:2048', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
            ];

            $this->validate($rules);

            $path = $attendance->justification_file_path;

            if ($this->files[$attendanceId]) {
                $path = $this->files[$attendanceId]->store('justifications', 'public');
            }

            $attendance->update([
                'status' => 'J',
                'justification' => $this->justifications[$attendanceId] ?: null,
                'justification_file_path' => $path,
                'recorded_by' => auth()->id(),
            ]);

            $count++;
        }

        Flux::toast(
            variant: 'success',
            text: "{$count} inasistencia(s) justificada(s) correctamente."
        );

        $this->redirect(route('admin.teacher.tutor-justifications.show', $this->id), navigate: true);
    }

    public function getStudentName(): string
    {
        return $this->student?->user?->fullname ?? '-';
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Justificar Inasistencias') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ $this->getStudentName() }}</flux:text>
        </div>
        <flux:button href="{{ route('admin.teacher.tutor-justifications.index') }}" wire:navigate variant="ghost">
            <flux:icon.arrow-left /> {{ __('Volver') }}
        </flux:button>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <a href="{{ route('admin.teacher.tutor-justifications.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Justificaciones') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Justificar') }}</span>
    </nav>

    @if(count($attendanceRecords) > 0)
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 mb-6">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Fecha') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Justificacion') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Documento') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Accion') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach($attendanceRecords as $attendance)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ \Carbon\Carbon::parse($attendance['date'])->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                    F. Injustificada
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <flux:input wire:model="justifications.{{ $attendance['id'] }}" placeholder="{{ __('Motivo de la justificacion...') }}" class="text-sm" />
                            </td>
                            <td class="px-4 py-3">
                                <flux:input type="file" wire:model="files.{{ $attendance['id'] }}" class="text-sm" />
                                @error("files.{$attendance['id']}") <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:button wire:click="justify({{ $attendance['id'] }})" size="sm" variant="primary" icon="check">
                                    {{ __('Justificar') }}
                                </flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex justify-end">
            <flux:button wire:click="justifyMultiple" variant="primary" icon="check-badge">
                {{ __('Justificar todas') }}
            </flux:button>
        </div>
    @else
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.check-badge class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('Sin inasistencias pendientes') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('Este estudiante no tiene faltas injustificadas pendientes de justificar.') }}</p>
        </div>
    @endif
</div>

