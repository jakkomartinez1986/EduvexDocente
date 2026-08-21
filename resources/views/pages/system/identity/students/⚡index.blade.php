<?php

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Services\AcademicYearService;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Estudiantes')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 15;
    public bool $isTutor = false;
    public bool $isDocente = false;
    public bool $isAdmin = false;
    public string $sortField = 'id';
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $this->isTutor = auth()->user()->hasRole('TUTOR');
        $this->isDocente = auth()->user()->hasRole('DOCENTE');
        $this->isAdmin = auth()->user()->hasAnyRole(['ADMIN', 'SUPER-ADMIN']);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowed = ['id', 'lastname', 'name', 'dni'];
        if (!in_array($field, $allowed)) return;
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function confirmToggle(int $id): void
    {
        $this->dispatch('showConfirm',
            message: 'Esta seguro de cambiar el estado de este estudiante?',
            eventName: 'execute-toggle-student',
            eventParams: ['id' => $id]
        )->to('confirm-action');
    }

    #[On('execute-toggle-student')]
    public function executeToggleStudent(array $params): void
    {
        $student = Student::find($params['id']);
        if ($student) {
            $this->toggleStatus($student);
        }
    }

    public function getCanImportProperty()
    {
        return $this->isTutor || $this->isDocente || $this->isAdmin;
    }

    public function getRecordsProperty()
    {
        $yearId = app(AcademicYearService::class)->getActiveYearId();
        $query = Student::query()->with(['user', 'enrollments.grade']);

        if ($this->isTutor) {
            $teacher = auth()->user()->teacher;
            if ($teacher) {
                $gradeIds = $teacher->classSchedules()
                    ->where('year_id', $yearId)
                    ->pluck('grade_id')
                    ->unique();

                $studentIds = StudentEnrollment::where('year_id', $yearId)
                    ->where('status', 'active')
                    ->whereIn('grade_id', $gradeIds)
                    ->pluck('student_id');

                $query->whereIn('students.id', $studentIds);
            }
        }

        $sortColumn = match($this->sortField) {
            'id' => 'students.id',
            'lastname' => 'users.lastname',
            'name' => 'users.name',
            'dni' => 'users.dni',
            default => 'students.id',
        };
        
        return $query
            ->select('students.*')
            ->leftJoin('users', 'students.user_id', '=', 'users.id')
            ->when($this->search, fn ($q) =>
                $q->where('student_code', 'ilike', "%{$this->search}%")
                    ->orWhere('users.name', 'ilike', "%{$this->search}%")
                    ->orWhere('users.lastname', 'ilike', "%{$this->search}%")
                    ->orWhere('users.dni', 'like', "%{$this->search}%")
            )
            ->orderBy($sortColumn, $this->sortDirection)
            ->orderByRaw("(SELECT g.grade_name FROM student_enrollments se JOIN grades g ON se.grade_id = g.id WHERE se.student_id = students.id AND se.year_id = ? AND se.status = 'active' LIMIT 1) NULLS LAST", [$yearId])
            ->orderByRaw("(SELECT g.section FROM student_enrollments se JOIN grades g ON se.grade_id = g.id WHERE se.student_id = students.id AND se.year_id = ? AND se.status = 'active' LIMIT 1) NULLS LAST", [$yearId])
            ->paginate($this->perPage);
    }

    public function toggleStatus(Student $student): void
    {
        $user = $student->user;
        if ($user) {
            $user->status = $user->status === 1 ? 0 : 1;
            $user->save();

            Flux::toast(
                variant: 'success',
                text: "Estudiante {$user->fullname} " . ($user->status === 1 ? 'activado' : 'desactivado') . ' correctamente.'
            );
        }
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Estudiantes') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">
                {{ $this->isTutor ? __('Estudiantes de mis grados') : __('Gestion de estudiantes del sistema') }}
            </flux:text>
        </div>
        @if($this->canImport)
            <div class="flex gap-2">
                <flux:button href="{{ route('system.identity.students.import') }}" wire:navigate variant="outline">
                    <flux:icon.arrow-up-tray /> {{ __('Importar') }}
                </flux:button>
                <flux:button href="{{ route('system.identity.students.create') }}" wire:navigate variant="primary">
                    <flux:icon.plus /> {{ __('Nuevo Estudiante') }}
                </flux:button>
            </div>
        @endif
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Estudiantes') }}</span>
    </nav>

    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-6">
        <div class="w-full sm:w-96">
            <flux:input wire:model.live.debounce="search" :placeholder="__('Buscar por codigo, nombre, apellido o DNI...')" icon="magnifying-glass" />
        </div>
    </div>

    <div>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100" wire:click="sortBy('id')">
                            {{ __('Codigo') }}
                            @if($this->sortField === 'id' && $this->sortDirection === 'asc') <flux:icon.chevron-up class="size-3 inline" /> @elseif($this->sortField === 'id') <flux:icon.chevron-down class="size-3 inline" /> @endif
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Grado') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100" wire:click="sortBy('lastname')">
                            {{ __('Nombre') }}
                            @if($this->sortField === 'lastname' && $this->sortDirection === 'asc') <flux:icon.chevron-up class="size-3 inline" /> @elseif($this->sortField === 'lastname') <flux:icon.chevron-down class="size-3 inline" /> @endif
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100" wire:click="sortBy('dni')">
                            {{ __('DNI') }}
                            @if($this->sortField === 'dni' && $this->sortDirection === 'asc') <flux:icon.chevron-up class="size-3 inline" /> @elseif($this->sortField === 'dni') <flux:icon.chevron-down class="size-3 inline" /> @endif
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Email') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Fecha Nac.') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->records as $student)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                            <td class="px-4 py-3"><flux:badge color="blue">{{ $student->student_code }}</flux:badge></td>
                            <td class="px-4 py-3">
                                @php $enrollment = $student->enrollments->where('status', 'active')->first(); @endphp
                                @if($enrollment && $enrollment->grade)
                                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">{{ $enrollment->grade->grade_name ?? '' }} / {{ $enrollment->grade->section ?? ''}} - {{ $enrollment->grade->nivel->shift->shift_name ?? ''}}</span>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $student->user?->fullname ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $student->user?->dni }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $student->user?->email }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $student->birth_date?->format('d/m/Y') ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="confirmToggle({{ $student->id }})"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium cursor-pointer transition
                                            {{ $student->user?->status === 1
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 hover:bg-emerald-100'
                                                : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-100' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $student->user?->status === 1 ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                    {{ $student->user?->status === 1 ? __('Activo') : __('Inactivo') }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item href="{{ route('system.identity.students.show', $student->id) }}" wire:navigate icon="eye">{{ __('Ver') }}</flux:menu.item>
                                        @unless($this->isTutor)
                                            <flux:menu.item href="{{ route('system.identity.students.edit', $student->id) }}" wire:navigate icon="pencil">{{ __('Editar') }}</flux:menu.item>
                                        @endunless
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-16 text-center">
                                <flux:icon.academic-cap class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron estudiantes.') }}</flux:text>
                                <flux:text variant="subtle" class="text-xs mt-1">{{ __('Intente con otros terminos de busqueda.') }}</flux:text>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $this->records->links() }}</div>
    </div>

    <livewire:confirm-action />
</div>
