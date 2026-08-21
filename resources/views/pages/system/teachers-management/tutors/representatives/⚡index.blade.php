<?php

declare(strict_types=1);

use App\Models\Identity\Users\Representative;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Representantes')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 15;
    public string $gradeName = '';
    public string $shiftName = '';
    public bool $found = false;
    public int $toggleId = 0;

    public function mount(): void
    {
        $this->findTutorGrade();
    }

    protected function findTutorGrade(): void
    {
        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        if (!$tutorSchedule) {
            $this->found = false;
            return;
        }

        $this->gradeName = trim(($tutorSchedule->grade->grade_name ?? '') . ' / ' . ($tutorSchedule->grade->section ?? ''));
        $this->shiftName = $tutorSchedule->grade->nivel->shift->shift_name ?? '';
        $this->found = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function confirmToggle(int $id): void
    {
        $this->toggleId = $id;
        $this->dispatch('showConfirm',
            message: 'Esta seguro de cambiar el estado de este representante?',
            eventName: 'execute-toggle-tutor-representative',
            eventParams: []
        )->to('confirm-action');
    }

    #[On('execute-toggle-tutor-representative')]
    public function executeToggleRepresentative(): void
    {
        $representative = Representative::find($this->toggleId);
        if ($representative) {
            $this->toggleStatus($representative);
        }
    }

    public function toggleStatus(Representative $representative): void
    {
        $user = $representative->user;
        if ($user) {
            $user->status = $user->status === 1 ? 0 : 1;
            $user->save();

            Flux::toast(
                variant: 'success',
                text: "Representante {$user->fullname} " . ($user->status === 1 ? 'activado' : 'desactivado') . ' correctamente.'
            );
        }
    }

    public function confirmActivateAll(): void
    {
        $this->dispatch('showConfirm',
            message: 'Esta seguro de activar a todos los representantes de este grado?',
            eventName: 'execute-activate-all-representatives',
            eventParams: []
        )->to('confirm-action');
    }

    #[On('execute-activate-all-representatives')]
    public function executeActivateAll(): void
    {
        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->first();

        if (!$tutorSchedule) return;

        $studentIds = StudentEnrollment::where('year_id', $yearId)
            ->where('grade_id', $tutorSchedule->grade_id)
            ->where('status', 'active')
            ->pluck('student_id');

        $count = Representative::whereIn('student_id', $studentIds)
            ->whereHas('user', fn ($q) => $q->where('status', '!=', 1))
            ->count();

        Representative::whereIn('student_id', $studentIds)
            ->whereHas('user', fn ($q) => $q->where('status', '!=', 1))
            ->each(fn ($representative) => $representative->user?->update(['status' => 1]));

        Flux::toast(
            variant: 'success',
            text: "{$count} representante(s) activado(s) correctamente."
        );
    }

    public function getRecordsProperty()
    {
        if (!$this->found) {
            return collect();
        }

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->first();

        if (!$tutorSchedule) {
            return collect();
        }

        $gradeId = $tutorSchedule->grade_id;

        $studentIds = StudentEnrollment::where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->where('status', 'active')
            ->pluck('student_id');

        $query = Representative::query()
            ->with(['user', 'student.user'])
            ->whereIn('representatives.student_id', $studentIds);

        return $query
            ->when($this->search, fn ($q) =>
                $q->orWhereHas('user', fn ($u) =>
                    $u->where('name', 'like', "%{$this->search}%")
                        ->orWhere('lastname', 'like', "%{$this->search}%")
                        ->orWhere('dni', 'like', "%{$this->search}%")
                )
                ->orWhereHas('student', fn ($s) =>
                    $s->where('student_code', 'like', "%{$this->search}%")
                        ->orWhereHas('user', fn ($su) =>
                            $su->where('name', 'like', "%{$this->search}%")
                                ->orWhere('lastname', 'like', "%{$this->search}%")
                                ->orWhere('dni', 'like', "%{$this->search}%")
                        )
                )
            )
            ->orderByRaw("COALESCE(NULLIF((SELECT u.lastname FROM users u WHERE u.id = representatives.user_id), ''), 'zzz')")
            ->paginate($this->perPage);
    }

    public function getRepresentativeCount(): int
    {
        if (!$this->found) return 0;

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->first();

        if (!$tutorSchedule) return 0;

        $studentIds = StudentEnrollment::where('year_id', $yearId)
            ->where('grade_id', $tutorSchedule->grade_id)
            ->where('status', 'active')
            ->pluck('student_id');

        return Representative::whereIn('student_id', $studentIds)->count();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Representantes') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Representantes de los estudiantes del grado tutorado') }}</flux:text>
        </div>
        @if($found)
            <div class="flex gap-2">
                <flux:button href="{{ route('system.identity.representatives.import') }}" wire:navigate variant="outline" icon="arrow-up-tray">
                    {{ __('Importar') }}
                </flux:button>
                <flux:button wire:click="confirmActivateAll" icon="check-badge" variant="primary">
                    {{ __('Activar todos') }}
                </flux:button>              
            </div>
        @endif
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Representantes') }}</span>
    </nav>

    @if($found)
        {{-- Grade Info --}}
        <div class="flex items-center gap-4 mb-6 px-5 py-4 bg-fuchsia-50 dark:bg-fuchsia-900/20 border border-fuchsia-200 dark:border-fuchsia-800 rounded-xl">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-fuchsia-100 dark:bg-fuchsia-900/40">
                    <flux:icon.user-group class="size-5 text-fuchsia-600 dark:text-fuchsia-400" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $gradeName }}</h2>
                    <div class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                        @if($shiftName)
                            <span>{{ $shiftName }}</span>
                        @endif
                        <span>{{ $this->getRepresentativeCount() }} {{ __('representantes') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Search --}}
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-6">
            <div class="w-full sm:w-96">
                <flux:input wire:model.live.debounce="search" :placeholder="__('Buscar por nombre, apellido, DNI o codigo de estudiante...')" icon="magnifying-glass" />
            </div>
        </div>

        {{-- Table --}}
        <div>
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Nombre') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('DNI') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Email') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Telefono') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estudiante') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Parentesco') }}</th>
                            <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estado') }}</th>
                            <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                        </tr> 
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($this->records as $representative)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <flux:avatar src="{{ $representative->user?->defaultUserPhotoUrl() }}" size="size-8" />
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $representative->user?->fullname ?? '-' }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $representative->user?->dni }}</td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $representative->user?->email }}</td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $representative->user?->phone ?? $representative->user?->cellphone ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <flux:badge color="purple">{{ $representative->student?->student_code ?? '-' }}</flux:badge>
                                    <span class="text-xs text-zinc-500 ml-1">{{ $representative->student?->user?->fullname ?? '' }}</span>
                                </td>
                                <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $representative->relationship ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button wire:click="confirmToggle({{ $representative->id }})"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium cursor-pointer transition
                                                {{ $representative->user?->status === 1
                                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 hover:bg-emerald-100'
                                                    : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-100' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $representative->user?->status === 1 ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                        {{ $representative->user?->status === 1 ? __('Activo') : __('Inactivo') }}
                                    </button>
                                </td>
                                <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item href="{{ route('system.identity.representatives.show', $representative->id) }}" wire:navigate icon="eye">{{ __('Ver') }}</flux:menu.item>
                                        <flux:menu.item href="{{ route('system.identity.representatives.edit', $representative->id) }}" wire:navigate icon="pencil">{{ __('Editar') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <flux:icon.user-group class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                    <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron representantes para este grado.') }}</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $this->records->links() }}</div>
        </div>
    @else
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.user-group class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('No se encontró asignación de tutoría') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontró una asignatura de Acompañamiento integral en el aula asociada a su usuario.') }}</p>
        </div>
    @endif

    <livewire:confirm-action />
</div>

