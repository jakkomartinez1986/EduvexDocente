<?php

declare(strict_types=1);

use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\Setting\YearSettings\ScolarYear;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Calendario Escolar')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $yearFilter = null;

    public int $perPage = 15;

    // Variables para vista tipo Google Calendar
    public string $currentDate = '';

    public string $viewMode = 'month'; // month, week, day

    public function mount(): void
    {
        $this->currentDate = Carbon::now()->format('Y-m-d');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedYearFilter(): void
    {
        $this->resetPage();
    }

    // Navegación del calendario
    public function previousPeriod(): void
    {
        $date = Carbon::parse($this->currentDate);
        $this->currentDate = match ($this->viewMode) {
            'month' => $date->subMonth()->format('Y-m-d'),
            'week' => $date->subWeek()->format('Y-m-d'),
            'day' => $date->subDay()->format('Y-m-d'),
            default => $date->subMonth()->format('Y-m-d'),
        };
    }

    public function nextPeriod(): void
    {
        $date = Carbon::parse($this->currentDate);
        $this->currentDate = match ($this->viewMode) {
            'month' => $date->addMonth()->format('Y-m-d'),
            'week' => $date->addWeek()->format('Y-m-d'),
            'day' => $date->addDay()->format('Y-m-d'),
            default => $date->addMonth()->format('Y-m-d'),
        };
    }

    public function goToToday(): void
    {
        $this->currentDate = Carbon::now()->format('Y-m-d');
    }

    public function changeView(string $view): void
    {
        $this->viewMode = $view;
    }

    public function getYearsProperty()
    {
        return ScolarYear::query()
            ->where('status', 1)
            ->orderByDesc('year_name')
            ->get();
    }

    public function getCurrentPeriodLabelProperty(): string
    {
        $date = Carbon::parse($this->currentDate);

        return match ($this->viewMode) {
            'month' => $date->format('F Y'),
            'week' => $date->startOfWeek()->format('d M').' - '.$date->endOfWeek()->format('d M Y'),
            'day' => $date->format('l, d F Y'),
            default => $date->format('F Y'),
        };
    }

    public function getCalendarDaysProperty()
    {
        $date = Carbon::parse($this->currentDate);

        if ($this->viewMode === 'month') {
            return $this->getMonthDays($date);
        } elseif ($this->viewMode === 'week') {
            return $this->getWeekDays($date);
        } else {
            return $this->getDayDetails($date);
        }
    }

    private function getMonthDays($date)
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        $startOfWeek = $startOfMonth->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $endOfMonth->copy()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        $current = $startOfWeek->copy();

        // Obtener días del calendario con eventos
        $events = CalendarDay::query()
            ->with(['year', 'trimester'])
            ->when($this->yearFilter, fn ($q) => $q->where('year_id', $this->yearFilter))
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->get()
            ->groupBy(fn ($item) => Carbon::parse($item->date)->format('Y-m-d'));

        while ($current <= $endOfWeek) {
            $dateKey = $current->format('Y-m-d');
            $days[] = [
                'date' => $current->copy(),
                'isCurrentMonth' => $current->month === $date->month,
                'isToday' => $current->isToday(),
                'events' => $events->get($dateKey, collect()),
            ];
            $current = $current->addDay();
        }

        return $days;
    }

    private function getWeekDays($date)
    {
        $startOfWeek = $date->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $date->copy()->endOfWeek(Carbon::SUNDAY);

        $events = CalendarDay::query()
            ->with(['year', 'trimester'])
            ->when($this->yearFilter, fn ($q) => $q->where('year_id', $this->yearFilter))
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->get()
            ->groupBy(fn ($item) => Carbon::parse($item->date)->format('Y-m-d'));

        $days = [];
        $current = $startOfWeek->copy();

        while ($current <= $endOfWeek) {
            $dateKey = $current->format('Y-m-d');
            $days[] = [
                'date' => $current->copy(),
                'isToday' => $current->isToday(),
                'events' => $events->get($dateKey, collect()),
            ];
            $current = $current->addDay();
        }

        return $days;
    }

    private function getDayDetails($date)
    {
        $dayEvents = CalendarDay::query()
            ->with(['year', 'trimester'])
            ->when($this->yearFilter, fn ($q) => $q->where('year_id', $this->yearFilter))
            ->whereDate('date', $date)
            ->get();

        return [
            'date' => $date->copy(),
            'isToday' => $date->isToday(),
            'events' => $dayEvents,
        ];
    }

    public function canManage(): bool
    {
        return auth()->user()->hasRole(['SUPER-ADMIN', 'ADMIN']);
    }

    public function getRecordsProperty()
    {
        return CalendarDay::query()
            ->with(['year', 'trimester'])
            ->when($this->yearFilter, fn ($q) => $q->where('year_id', $this->yearFilter))
            ->when($this->search, fn ($q) => $q->where(function ($q2) {
                $q2->where('day_name', 'ilike', "%{$this->search}%")
                    ->orWhere('activity', 'ilike', "%{$this->search}%")
                    ->orWhere('month_name', 'ilike', "%{$this->search}%");
            })
            )
            ->orderBy('date')
            ->paginate($this->perPage);
    }
}; ?>

<div>
    <!-- Header estilo Google Calendar -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
        <div>
            <flux:heading size="xl" class="flex items-center gap-3">
                <span class="bg-blue-50 dark:bg-blue-900/30 p-2 rounded-xl">
                    <flux:icon.calendar-days class="size-6 text-blue-600 dark:text-blue-400" />
                </span>
                {{ __('Calendario Escolar') }}
            </flux:heading>
            <flux:text variant="subtle" class="mt-1.5 ml-1">
                {{ __('Visualización mensual del calendario académico') }}
            </flux:text>
        </div>
        @if($this->canManage())
        <div class="flex items-center gap-2 w-full md:w-auto">
            <flux:button href="{{ route('admin.settings.calendar-scolars.import') }}" wire:navigate variant="ghost" size="sm">
                <flux:icon.arrow-up-tray class="size-4" />
                <span>{{ __('Importar') }}</span>
            </flux:button>
            <flux:button href="{{ route('admin.settings.calendar-scolars.create') }}" wire:navigate variant="primary" size="sm">
                <flux:icon.plus class="size-4" />
                <span>{{ __('Nuevo') }}</span>
            </flux:button>
        </div>
        @endif
    </div>

    <!-- Controles de navegación estilo Google Calendar -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6 bg-white dark:bg-zinc-900 p-3 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="flex items-center gap-2 w-full sm:w-auto">
            <flux:button wire:click="goToToday" variant="ghost" size="sm" class="font-medium">
                {{ __('Hoy') }}
            </flux:button>
            <flux:button wire:click="previousPeriod" variant="ghost" size="sm" icon="chevron-left" />
            <flux:button wire:click="nextPeriod" variant="ghost" size="sm" icon="chevron-right" />
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 ml-2 min-w-[180px]">
                {{ $this->currentPeriodLabel }}
            </h2>
        </div>
        
        <div class="flex items-center gap-1.5 w-full sm:w-auto">
            <flux:select wire:model.live="yearFilter" class="w-40" size="sm">
                <flux:select.option value="">{{ __('Todos los años') }}</flux:select.option>
                @foreach ($this->years as $year)
                    <flux:select.option value="{{ $year->id }}">{{ $year->year_name }}</flux:select.option>
                @endforeach
            </flux:select>
            
            <div class="flex bg-zinc-100 dark:bg-zinc-800 rounded-lg p-0.5">
                <button 
                    wire:click="changeView('month')" 
                    class="px-3 py-1.5 text-xs rounded-md transition-all {{ $this->viewMode === 'month' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    {{ __('Mes') }}
                </button>
                <button 
                    wire:click="changeView('week')" 
                    class="px-3 py-1.5 text-xs rounded-md transition-all {{ $this->viewMode === 'week' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    {{ __('Semana') }}
                </button>
                <button 
                    wire:click="changeView('day')" 
                    class="px-3 py-1.5 text-xs rounded-md transition-all {{ $this->viewMode === 'day' ? 'bg-white dark:bg-zinc-700 shadow-sm text-zinc-900 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    {{ __('Día') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Vista Mes -->
    @if($this->viewMode === 'month')
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm">
        <!-- Días de la semana -->
        <div class="grid grid-cols-7 gap-px bg-zinc-200 dark:bg-zinc-700">
            @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $day)
                <div class="bg-zinc-50 dark:bg-zinc-800/50 px-2 py-3 text-center text-xs font-medium text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">
                    {{ $day }}
                </div>
            @endforeach
        </div>
        
        <!-- Días del mes -->
        <div class="grid grid-cols-7 gap-px bg-zinc-200 dark:bg-zinc-700">
            @foreach($this->calendarDays as $day)
                <div class="min-h-[100px] bg-white dark:bg-zinc-900 p-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ !$day['isCurrentMonth'] ? 'opacity-40' : '' }}">
                    <div class="flex items-start justify-between">
                        <span class="text-sm font-medium {{ $day['isToday'] ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                            {{ $day['date']->format('d') }}
                        </span>
                        @if($day['isToday'])
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mt-1"></span>
                        @endif
                    </div>
                    
                    <!-- Eventos del día -->
                    <div class="mt-1 space-y-0.5 max-h-[70px] overflow-y-auto">
                        @foreach($day['events']->take(3) as $event)
                            <div class="group/event text-xs px-1.5 py-0.5 rounded truncate flex items-center justify-between {{ $event->is_holiday ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300' : 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300' }}">
                                <span class="truncate">
                                    @if($event->is_holiday)
                                        <flux:icon.flag class="size-3 inline mr-0.5" />
                                    @endif
                                    {{ $event->activity ?? $event->day_name }}
                                </span>
                                @if($this->canManage())
                                <a href="{{ route('admin.settings.calendar-scolars.edit', $event->id) }}" wire:navigate class="shrink-0 ml-1 opacity-0 group-hover/event:opacity-100 transition-opacity hover:text-zinc-900 dark:hover:text-white">
                                    <flux:icon.pencil class="size-2.5" />
                                </a>
                                @endif
                            </div>
                        @endforeach
                        @if($day['events']->count() > 3)
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 pl-1.5">
                                +{{ $day['events']->count() - 3 }} más
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Vista Semana -->
    @if($this->viewMode === 'week')
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm">
        <!-- Encabezado de semana -->
        <div class="grid grid-cols-7 gap-px bg-zinc-200 dark:bg-zinc-700">
            @foreach($this->calendarDays as $day)
                <div class="bg-zinc-50 dark:bg-zinc-800/50 px-2 py-3 text-center">
                    <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase">
                        {{ $day['date']->format('D') }}
                    </div>
                    <div class="text-lg font-semibold {{ $day['isToday'] ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                        {{ $day['date']->format('d') }}
                    </div>
                </div>
            @endforeach
        </div>
        
        <!-- Eventos por día -->
        <div class="grid grid-cols-7 gap-px bg-zinc-200 dark:bg-zinc-700 min-h-[400px]">
            @foreach($this->calendarDays as $day)
                <div class="bg-white dark:bg-zinc-900 p-2 min-h-[200px]">
                    <div class="space-y-1.5">
                        @forelse($day['events'] as $event)
                            <div class="group/event text-xs p-1.5 rounded {{ $event->is_holiday ? 'bg-red-100 dark:bg-red-900/30 border-l-2 border-red-500' : 'bg-blue-50 dark:bg-blue-900/20 border-l-2 border-blue-500' }}">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="font-medium truncate {{ $event->is_holiday ? 'text-red-700 dark:text-red-300' : 'text-blue-700 dark:text-blue-300' }}">
                                        {{ $event->activity ?? $event->day_name }}
                                    </span>
                                    @if($this->canManage())
                                    <a href="{{ route('admin.settings.calendar-scolars.edit', $event->id) }}" wire:navigate class="shrink-0 opacity-0 group-hover/event:opacity-100 transition-opacity hover:text-zinc-900 dark:hover:text-white">
                                        <flux:icon.pencil class="size-3" />
                                    </a>
                                    @endif
                                </div>
                                @if($event->is_holiday)
                                    <div class="text-xs text-red-600 dark:text-red-400 flex items-center gap-0.5">
                                        <flux:icon.flag class="size-3" />
                                        {{ __('Feriado') }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-xs text-zinc-400 dark:text-zinc-600 text-center py-4">
                                {{ __('Sin eventos') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Vista Día -->
    @if($this->viewMode === 'day')
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm">
        <div class="p-4 border-b border-zinc-200 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $this->calendarDays['date']->format('l, d F Y') }}
                    </h3>
                    @if($this->calendarDays['isToday'])
                        <span class="text-xs text-blue-600 dark:text-blue-400 font-medium">{{ __('Hoy') }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <flux:input 
                        wire:model.live.debounce="search" 
                        :placeholder="__('Buscar...')" 
                        icon="magnifying-glass"
                        size="sm"
                        class="w-48"
                    />
                </div>
            </div>
        </div>
        
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse($this->calendarDays['events'] as $event)
                <div class="p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors group">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium {{ $event->is_holiday ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300' : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' }}">
                                    @if($event->is_holiday)
                                        <flux:icon.flag class="size-3" />
                                    @else
                                        <flux:icon.calendar class="size-3" />
                                    @endif
                                    {{ $event->day_name }}
                                </span>
                                @if($event->year)
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $event->year->year_name }}
                                    </span>
                                @endif
                            </div>
                            @if($event->activity)
                                <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">
                                    {{ $event->activity }}
                                </p>
                            @endif
                            @if($event->is_holiday)
                                <p class="mt-0.5 text-xs text-red-600 dark:text-red-400 flex items-center gap-1">
                                    <flux:icon.exclamation-circle class="size-3" />
                                    {{ __('Día feriado') }}
                                </p>
                            @endif
                        </div>
                        @if($this->canManage())
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <flux:button href="{{ route('admin.settings.calendar-scolars.show', $event->id) }}" wire:navigate variant="ghost" size="xs" icon="eye" />
                            <flux:button href="{{ route('admin.settings.calendar-scolars.edit', $event->id) }}" wire:navigate variant="ghost" size="xs" icon="pencil" />
                        </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center">
                    <div class="inline-flex p-4 rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon.calendar-days class="size-8 text-zinc-400 dark:text-zinc-600" />
                    </div>
                    <h4 class="mt-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                        {{ __('No hay eventos para este día') }}
                    </h4>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No se encontraron actividades programadas para esta fecha.') }}
                    </p>
                    @if($this->canManage())
                    <div class="mt-4">
                        <flux:button href="{{ route('admin.settings.calendar-scolars.create') }}" wire:navigate variant="primary" size="sm">
                            <flux:icon.plus class="size-4" />
                            {{ __('Agregar evento') }}
                        </flux:button>
                    </div>
                    @endif
                </div>
            @endforelse
        </div>
    </div>
    @endif

    <!-- Leyenda -->
    <div class="mt-4 flex flex-wrap items-center gap-4 p-3 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Leyenda:') }}</span>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded bg-blue-100 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800"></span>
            <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ __('Día lectivo') }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded bg-red-100 dark:bg-red-900/30 border border-red-200 dark:border-red-800"></span>
            <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ __('Feriado') }}</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-full bg-blue-500"></span>
            <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ __('Hoy') }}</span>
        </div>
        <div class="flex-1"></div>
        <span class="text-xs text-zinc-400 dark:text-zinc-500">
            {{ __('Total de días:') }} {{ $this->records->total() }}
        </span>
    </div>

    <!-- Vista de lista (oculta por defecto, se muestra al hacer clic en el ícono de lista) -->
    @if($this->search || $this->yearFilter)
    <div class="mt-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300 flex items-center gap-2">
                <flux:icon.list-bullet class="size-4" />
                {{ __('Resultados de búsqueda') }}
            </h3>
            <flux:button wire:click="$set('search', ''); $set('yearFilter', null)" variant="ghost" size="xs">
                <flux:icon.x-mark class="size-4" />
                {{ __('Limpiar') }}
            </flux:button>
        </div>
        
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700 max-h-64 overflow-y-auto">
                @forelse($this->records as $record)
                    <div class="p-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $record->date?->format('d/m/Y') }}
                                </span>
                                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $record->day_name }}</span>
                                @if($record->activity)
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">- {{ $record->activity }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @if($record->is_holiday)
                                    <flux:badge color="red" size="xs">{{ __('Feriado') }}</flux:badge>
                                @endif
                                @if($this->canManage())
                                <flux:button href="{{ route('admin.settings.calendar-scolars.edit', $record->id) }}" wire:navigate variant="ghost" size="xs" icon="pencil" />
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-center text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No se encontraron resultados') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif
</div>