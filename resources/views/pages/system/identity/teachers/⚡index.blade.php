<?php

declare(strict_types=1);

use App\Models\Identity\Users\Teacher;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Docentes')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int $perPage = 15;
    public string $sortField = 'id';
    public string $sortDirection = 'desc';

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
            message: 'Esta seguro de cambiar el estado de este docente?',
            eventName: 'execute-toggle-teacher',
            eventParams: ['id' => $id]
        )->to('confirm-action');
    }

    #[On('execute-toggle-teacher')]
    public function executeToggleTeacher(array $params): void
    {
        $teacher = Teacher::find($params['id']);
        if ($teacher) {
            $this->toggleStatus($teacher);
        }
    }

    public function getRecordsProperty()
    {
        $sortColumn = match($this->sortField) {
            'id' => 'teachers.id',
            'lastname' => 'users.lastname',
            'name' => 'users.name',
            'dni' => 'users.dni',
            default => 'teachers.id',
        };
        $search = strtoupper($this->search);
        return Teacher::query()
            ->select('teachers.*')
            ->leftJoin('users', 'teachers.user_id', '=', 'users.id')
            ->with(['user'])
            ->when($this->search, fn ($q) =>
                $q->where('teacher_code', 'like', "%{$this->search}%")
                    ->orWhere('users.name', 'like', "%{$this->search}%")
                    ->orWhere('users.lastname', 'like', "%{$this->search}%")
                    ->orWhere('users.dni', 'like', "%{$this->search}%")
            )
            ->orderBy($sortColumn, $this->sortDirection)
            ->paginate($this->perPage);
    }

    public function toggleStatus(Teacher $teacher): void
    {
        $user = $teacher->user;
        if ($user) {
            $user->status = $user->status === 1 ? 0 : 1;
            $user->save();

            Flux::toast(
                variant: 'success',
                text: "Docente {$user->fullname} " . ($user->status === 1 ? 'activado' : 'desactivado') . ' correctamente.'
            );
        }
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Docentes') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Gestion de docentes del sistema') }}</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button href="{{ route('system.identity.teachers.import') }}" wire:navigate variant="outline">
                <flux:icon.arrow-up-tray /> {{ __('Importar') }}
            </flux:button>
            <flux:button href="{{ route('system.identity.teachers.create') }}" wire:navigate variant="primary">
                <flux:icon.plus /> {{ __('Nuevo Docente') }}
            </flux:button>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Docentes') }}</span>
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
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100" wire:click="sortBy('lastname')">
                            {{ __('Nombre') }}
                            @if($this->sortField === 'lastname' && $this->sortDirection === 'asc') <flux:icon.chevron-up class="size-3 inline" /> @elseif($this->sortField === 'lastname') <flux:icon.chevron-down class="size-3 inline" /> @endif
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400 cursor-pointer hover:text-zinc-900 dark:hover:text-zinc-100" wire:click="sortBy('dni')">
                            {{ __('DNI') }}
                            @if($this->sortField === 'dni' && $this->sortDirection === 'asc') <flux:icon.chevron-up class="size-3 inline" /> @elseif($this->sortField === 'dni') <flux:icon.chevron-down class="size-3 inline" /> @endif
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Especializacion') }}</th>
                        <th class="px-4 py-3 text-left font-medium text-zinc-600 dark:text-zinc-400">{{ __('Nivel') }}</th>
                        <th class="px-4 py-3 text-center font-medium text-zinc-600 dark:text-zinc-400">{{ __('Estado') }}</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-600 dark:text-zinc-400">{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->records as $teacher)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                            <td class="px-4 py-3"><flux:badge color="blue">{{ $teacher->teacher_code }}</flux:badge></td>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $teacher->user?->fullname ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $teacher->user?->dni }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $teacher->specialization ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $teacher->education_level ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="confirmToggle({{ $teacher->id }})"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium cursor-pointer transition
                                            {{ $teacher->user?->status === 1
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 hover:bg-emerald-100'
                                                : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-100' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $teacher->user?->status === 1 ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                    {{ $teacher->user?->status === 1 ? __('Activo') : __('Inactivo') }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item href="{{ route('system.identity.teachers.show', $teacher->id) }}" wire:navigate icon="eye">{{ __('Ver') }}</flux:menu.item>
                                        <flux:menu.item href="{{ route('system.identity.teachers.edit', $teacher->id) }}" wire:navigate icon="pencil">{{ __('Editar') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center">
                                <flux:icon.user-group class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                                <flux:text variant="subtle" class="text-sm">{{ __('No se encontraron docentes.') }}</flux:text>
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
