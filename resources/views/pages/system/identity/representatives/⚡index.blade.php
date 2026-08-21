<?php

declare(strict_types=1);

use App\Models\Identity\Users\Representative;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
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
    public bool $isTutor = false;
    public bool $isDocente = false;
    public bool $isAdmin = false;

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

    public function confirmToggle(int $id): void
    {
        $this->dispatch('showConfirm',
            message: 'Esta seguro de cambiar el estado de este representante?',
            eventName: 'execute-toggle-representative',
            eventParams: ['id' => $id]
        )->to('confirm-action');
    }

    #[On('execute-toggle-representative')]
    public function executeToggleRepresentative(array $params): void
    {
        $representative = Representative::find($params['id']);
        if ($representative) {
            $this->toggleStatus($representative);
        }
    }

    public function getCanImportProperty()
    {
        return $this->isTutor || $this->isDocente || $this->isAdmin;
    }

    public function getRecordsProperty()
    {
        $query = Representative::query()->with(['user', 'student.user']);

        if ($this->isTutor) {
            $teacher = auth()->user()->teacher;
            if ($teacher) {
                $yearId = app(AcademicYearService::class)->getActiveYearId();
                $gradeIds = $teacher->classSchedules()
                    ->where('year_id', $yearId)
                    ->pluck('grade_id')
                    ->unique();

                $studentIds = StudentEnrollment::where('year_id', $yearId)
                    ->where('status', 'active')
                    ->whereIn('grade_id', $gradeIds)
                    ->pluck('student_id');

                $query->whereIn('representatives.student_id', $studentIds);
            }
        }
        
        return $query
            ->when($this->search, fn ($q) =>
                $q->orWhereHas('user', fn ($u) =>
                    $u->where('name', 'ilike', "%{$this->search}%")
                        ->orWhere('lastname', 'liike', "%{$this->search}%")
                        ->orWhere('dni', 'ilike', "%{$this->search}%")
                )
                ->orWhereHas('student', fn ($s) =>
                    $s->where('student_code', 'ilike', "%{$this->search}%")
                        ->orWhereHas('user', fn ($su) =>
                            $su->where('name', 'ilike', "%{$this->search}%")
                                ->orWhere('lastname', 'ilike', "%{$this->search}%")
                                ->orWhere('dni', 'ilike', "%{$this->search}%")
                        )
                )
            )
            ->latest()
            ->paginate($this->perPage);
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
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Representantes') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">
                {{ $this->isTutor ? __('Representantes de mis estudiantes') : __('Gestion de representantes del sistema') }}
            </flux:text>
        </div>
        @if($this->canImport)
            <div class="flex gap-2">
                <flux:button href="{{ route('system.identity.representatives.import') }}" wire:navigate variant="outline">
                    <flux:icon.arrow-up-tray /> {{ __('Importar') }}
                </flux:button>
                <flux:button href="{{ route('system.identity.representatives.create') }}" wire:navigate variant="primary">
                    <flux:icon.plus /> {{ __('Nuevo Representante') }}
                </flux:button>
            </div>
        @endif
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Representantes') }}</span>
    </nav>

    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 mb-6">
        <div class="w-full sm:w-96">
            <flux:input wire:model.live.debounce="search" :placeholder="__('Buscar por nombre, apellido, DNI o codigo de estudiante...')" icon="magnifying-glass" />
        </div>
    </div>

    <div>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Nombre') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('DNI') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Email') }}</th>
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
                                        @unless($this->isTutor)
                                            <flux:menu.item href="{{ route('system.identity.representatives.edit', $representative->id) }}" wire:navigate icon="pencil">{{ __('Editar') }}</flux:menu.item>
                                        @endunless
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <flux:icon.users class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron representantes.') }}</flux:text>
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
