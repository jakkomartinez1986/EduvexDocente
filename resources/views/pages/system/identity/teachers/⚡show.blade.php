<?php

declare(strict_types=1);

use App\Models\Identity\Users\Teacher;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detalle de Docente')] class extends Component
{
    public int $teacherId;

    public function mount(int $id): void
    {
        $this->teacherId = $id;
    }

    public function getTeacherProperty(): Teacher
    {
        return Teacher::query()
            ->with(['user'])
            ->findOrFail($this->teacherId);
    }

    public function getTutorGradeProperty(): ?string
    {
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        if ($yearId === null) {
            return null;
        }

        $schedule = ClassSchedule::query()
            ->where('teacher_id', $this->teacherId)
            ->where('year_id', $yearId)
            ->where('subject_id', function ($query) {
                $query->select('id')
                    ->from('subjects')
                    ->where(function ($q) {
                        $q->whereRaw('LOWER(subject_name) LIKE ?', ['%acompañamiento integral%'])
                            ->orWhereRaw('LOWER(subject_name) LIKE ?', ['%aiac%'])
                            ->orWhereRaw('LOWER(subject_name) LIKE ?', ['%civica%']);
                    });
            })
            ->with('grade.nivel.shift')
            ->first();

        if (! $schedule) {
            return null;
        }

        $classroom = $schedule->classroom ?? '-';
        $shiftName = $schedule->grade?->nivel?->shift?->shift_name ?? '-';

        return "{$classroom} ({$shiftName})";
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Detalle de Docente') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Informacion completa del docente') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('system.identity.teachers.edit', $this->teacherId) }}" wire:navigate variant="primary">
                <flux:icon.pencil /> {{ __('Editar') }}
            </flux:button>
            <flux:button href="{{ route('system.identity.teachers.index') }}" wire:navigate variant="ghost">
                <flux:icon.arrow-left /> {{ __('Volver') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <a href="{{ route('system.identity.teachers.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Docentes') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $this->teacher->teacher_code }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 text-center">
                <flux:avatar src="{{ $this->teacher->user?->defaultUserPhotoUrl() }}" size="size-20" class="mx-auto mb-4" />
                <flux:heading size="lg">{{ $this->teacher->user?->fullname ?? '-' }}</flux:heading>
                <flux:text class="text-zinc-500">{{ $this->teacher->user?->email }}</flux:text>
                <div class="mt-3">
                    <flux:badge color="{{ $this->teacher->user?->status === 1 ? 'green' : 'red' }}">
                        {{ $this->teacher->user?->status === 1 ? __('Activo') : __('Inactivo') }}
                    </flux:badge>
                </div>
                <div class="mt-2">
                    <flux:badge color="blue">{{ $this->teacher->teacher_code }}</flux:badge>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion Personal') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nombre') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->user?->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Apellido') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->user?->lastname ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('DNI') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->user?->dni ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Email') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->user?->email ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Telefono') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->user?->phone ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Celular') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->user?->cellphone ?? '-' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Direccion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->user?->address ?? '-' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <flux:heading size="md" class="mb-4">{{ __('Informacion Docente') }}</flux:heading>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Codigo') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->teacher_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Especializacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->specialization ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Titulo') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->title ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Nivel Educativo') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->education_level ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Roles') }}</dt>                      
                         <div class="flex flex-wrap gap-1">
                            @forelse ($this->teacher->user->roles as $role)
                                <flux:badge size="xs" color="blue" variant="outline">{{ $role->name }}</flux:badge>
                            @empty
                                <flux:text variant="subtle" class="text-xs">{{ __('Sin roles') }}</flux:text>
                            @endforelse
                        </div>
                    </div>
                     <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Grado Tutoria') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            @if($this->tutorGrade)
                                {{ $this->tutorGrade }}
                            @else
                                <span class="text-zinc-400">{{ __('No es tutor de grado') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Ingreso') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->hire_date?->format('d/m/Y') ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-zinc-500 uppercase">{{ __('Fecha de Creacion') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">{{ $this->teacher->created_at?->format('d/m/Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
