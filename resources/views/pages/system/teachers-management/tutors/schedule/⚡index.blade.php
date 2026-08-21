<?php

declare(strict_types=1);

use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Horario del Grado')] class extends Component
{
    public ?int $yearId = null;
    public string $gradeName = '';
    public string $shiftName = '';
    public array $schedules = [];
    public array $timeSlots = [];
    public array $grid = [];
    public array $subjectColors = [];
    public int $blockDuration = 45;
    public bool $found = false;

    public function mount(): void
    {
        $this->yearId = app(AcademicYearService::class)->getActiveYearId();
        $this->subjectColors = $this->buildSubjectColors();
        $this->loadSchedule();
    }

    public function loadSchedule(): void
    {
        $teacherId = auth()->user()->teacher?->id;

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $this->yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        if (!$tutorSchedule) {
            $this->found = false;
            return;
        }

        $gradeId = $tutorSchedule->grade_id;
        $this->gradeName = trim(($tutorSchedule->grade->grade_name ?? '') . ' ' . ($tutorSchedule->grade->section ?? ''));
        $this->shiftName = $tutorSchedule->grade->nivel->shift->shift_name ?? '';

        $nivelName = $tutorSchedule->grade->nivel->nivel_name ?? '';
        $normalized = mb_strtolower(str_replace('_', ' ', \Transliterator::create('Any-Latin; Latin-ASCII; Lower')->transliterate($nivelName)));
        $this->blockDuration = str_contains($normalized, 'bachillerato tecnico') ? 40 : 45;

        $this->schedules = ClassSchedule::where('year_id', $this->yearId)
            ->where('grade_id', $gradeId)
            ->where('is_active', true)
            ->with(['teacher.user', 'subject'])
            ->get()
            ->toArray();

        $this->found = true;
        $this->buildGrid();
    }

    protected function buildGrid(): void
    {
        $dias = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];

        $blocks = collect();
        foreach ($this->schedules as $schedule) {
            $inicio = \Carbon\Carbon::parse($schedule['start_time']);
            $fin = \Carbon\Carbon::parse($schedule['end_time']);
            $limite = 0;

            while ($inicio < $fin && $limite < 20) {
                $blocks->push([
                    'day' => strtoupper($schedule['day']),
                    'start' => $inicio->format('H:i'),
                    'end' => $inicio->copy()->addMinutes($this->blockDuration)->format('H:i'),
                    'subject' => $schedule['subject']['subject_name'] ?? 'Sin materia',
                    'teacher' => trim(($schedule['teacher']['user']['lastname'] ?? '') . ' ' . ($schedule['teacher']['user']['name'] ?? '')),
                    'classroom' => $schedule['classroom'] ?? '',
                    'type' => $schedule['schedule_type'] ?? 'OFFICIAL',
                ]);
                $inicio->addMinutes($this->blockDuration);
                $limite++;
            }
        }

        $index = [];
        foreach ($blocks as $b) {
            $index[$b['day']][$b['start']] = $b;
        }

        $this->timeSlots = $blocks->pluck('start')->unique()->sort()->values()->toArray();

        $this->grid = [];
        foreach ($dias as $dia) {
            $this->grid[$dia] = [];
            foreach ($this->timeSlots as $time) {
                $this->grid[$dia][$time] = $index[$dia][$time] ?? null;
            }
        }
    }

    public function getDisplayDays(): array
    {
        return ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'];
    }

    public function getDayLabel(string $day): string
    {
        return match ($day) {
            'LUNES' => 'Lunes',
            'MARTES' => 'Martes',
            'MIERCOLES' => 'Miércoles',
            'JUEVES' => 'Jueves',
            'VIERNES' => 'Viernes',
            'SABADO' => 'Sábado',
            default => $day,
        };
    }

    public function getSubjectColor(string $subject): array
    {
        return $this->subjectColors[$subject] ?? [
            'bg' => '#f1f5f9',
            'text' => '#334155',
            'border' => '#cbd5e1',
        ];
    }

    public function getTypeBadge(string $type): array
    {
        return match ($type) {
            'EVALUATION' => ['label' => 'Eval.', 'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'],
            'TEST' => ['label' => 'Prueba', 'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'],
            'MAKEUP' => ['label' => 'Recup.', 'class' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400'],
            default => ['label' => '', 'class' => ''],
        };
    }

    private function buildSubjectColors(): array
    {
        return [
            'Matemáticas' => ['bg' => '#dbeafe', 'text' => '#1d4ed8', 'border' => '#93c5fd'],
            'Matematica Superior' => ['bg' => '#bfdbfe', 'text' => '#1e40af', 'border' => '#60a5fa'],
            'Lengua y Literatura' => ['bg' => '#fee2e2', 'text' => '#b91c1c', 'border' => '#fca5a5'],
            'Animación a la lectura' => ['bg' => '#fecaca', 'text' => '#991b1b', 'border' => '#f87171'],
            'Inglés' => ['bg' => '#e0e7ff', 'text' => '#4338ca', 'border' => '#a5b4fc'],
            'Ciencias Naturales' => ['bg' => '#dcfce7', 'text' => '#15803d', 'border' => '#86efac'],
            'Biología' => ['bg' => '#bbf7d0', 'text' => '#166534', 'border' => '#4ade80'],
            'Química' => ['bg' => '#d1fae5', 'text' => '#065f46', 'border' => '#6ee7b7'],
            'Física' => ['bg' => '#f3e8ff', 'text' => '#7e22ce', 'border' => '#d8b4fe'],
            'Estudios Sociales' => ['bg' => '#ffedd5', 'text' => '#c2410c', 'border' => '#fdba74'],
            'Historia' => ['bg' => '#fed7aa', 'text' => '#9a3412', 'border' => '#fb923c'],
            'Filosofía' => ['bg' => '#fdba74', 'text' => '#7c2d12', 'border' => '#f97316'],
            'Educación para la Ciudadanía' => ['bg' => '#fed7aa', 'text' => '#c2410c', 'border' => '#fb923c'],
            'Educación Cultural y Artística' => ['bg' => '#fce7f3', 'text' => '#be185d', 'border' => '#f9a8d4'],
            'Emprendimiento y Gestión' => ['bg' => '#fef9c3', 'text' => '#854d0e', 'border' => '#fde047'],
            'Programación Estructurada' => ['bg' => '#cffafe', 'text' => '#0e7490', 'border' => '#67e8f9'],
            'Programación Orientada a Objetos' => ['bg' => '#a5f3fc', 'text' => '#155e75', 'border' => '#22d3ee'],
            'Base de Datos' => ['bg' => '#67e8f9', 'text' => '#083344', 'border' => '#06b6d4'],
            'Soporte Técnico' => ['bg' => '#e5e7eb', 'text' => '#1f2937', 'border' => '#9ca3af'],
            'Sistemas Operativos y Redes' => ['bg' => '#d1d5db', 'text' => '#111827', 'border' => '#6b7280'],
            'Formación y Orientación Laboral' => ['bg' => '#ccfbf1', 'text' => '#0f766e', 'border' => '#5eead4'],
            'Acompañamiento integral en el aula' => ['bg' => '#e2e8f0', 'text' => '#1e293b', 'border' => '#94a3b8'],
            'Cívica' => ['bg' => '#cbd5e1', 'text' => '#0f172a', 'border' => '#64748b'],
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Horario del Grado') }}</flux:heading>
            <flux:text variant="subtle" class="mt-1">{{ __('Vista semanal de clases del grado tutorado') }}</flux:text>
        </div>
    </div>

    <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300 transition">{{ __('Dashboard') }}</a>
        <span>/</span>
        <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ __('Horario del Grado') }}</span>
    </nav>

    @if($this->found)
        {{-- Grade Info --}}
        <div class="flex items-center gap-4 mb-6 px-5 py-4 bg-fuchsia-50 dark:bg-fuchsia-900/20 border border-fuchsia-200 dark:border-fuchsia-800 rounded-xl">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-fuchsia-100 dark:bg-fuchsia-900/40">
                    <flux:icon.academic-cap class="size-5 text-fuchsia-600 dark:text-fuchsia-400" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $this->gradeName }}</h2>
                    <div class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                        @if($this->shiftName)
                            <span>{{ $this->shiftName }}</span>
                        @endif
                        <span>{{ count($this->schedules) }} {{ __('asignaturas') }}</span>
                        <span>{{ $this->blockDuration }} {{ __('min/bloque') }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($this->timeSlots))
            {{-- Weekly Grid --}}
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-zinc-50 dark:bg-zinc-800/80">
                            <th class="sticky left-0 bg-zinc-50 dark:bg-zinc-800/80 z-20 px-4 py-4 text-left font-semibold text-zinc-700 dark:text-zinc-200 border-b border-r border-zinc-200 dark:border-zinc-700 min-w-[90px]">
                                Hora
                            </th>
                            @foreach($this->getDisplayDays() as $day)
                                <th class="px-4 py-4 text-center font-semibold text-zinc-700 dark:text-zinc-200 min-w-[160px] border-b border-zinc-200 dark:border-zinc-700">
                                    {{ $this->getDayLabel($day) }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($this->timeSlots as $time)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50 transition">
                                <td class="sticky left-0 z-10 bg-white dark:bg-zinc-900 px-3 py-4 text-center min-w-[90px] border-r border-zinc-200 dark:border-zinc-700">
                                    <div class="font-semibold text-zinc-700 dark:text-zinc-200 text-sm">{{ $time }}</div>
                                    <div class="text-[11px] text-zinc-400 mt-0.5">
                                        {{ \Carbon\Carbon::createFromFormat('H:i', $time)->addMinutes($this->blockDuration)->format('H:i') }}
                                    </div>
                                </td>
                                @foreach($this->getDisplayDays() as $day)
                                    @php
                                        $bloque = $this->grid[$day][$time] ?? null;
                                        $color = $bloque ? $this->getSubjectColor($bloque['subject']) : null;
                                        $badge = $bloque ? $this->getTypeBadge($bloque['type']) : null;
                                    @endphp
                                    <td class="p-1.5 align-top border-b border-zinc-100 dark:border-zinc-800">
                                        @if($bloque)
                                            <div
                                                style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }}; border: 1px solid {{ $color['border'] }};"
                                                class="rounded-xl p-3 shadow-sm hover:shadow-md transition min-h-[70px]"
                                            >
                                                <div class="font-semibold text-sm leading-tight">
                                                    {{ $bloque['subject'] }}
                                                </div>
                                                <div class="text-xs opacity-75 mt-1">
                                                    {{ $bloque['teacher'] }}
                                                </div>
                                                @if($bloque['classroom'])
                                                    <div class="text-[10px] opacity-60 mt-1">
                                                        📍 {{ $bloque['classroom'] }}
                                                    </div>
                                                @endif
                                                @if($badge && $badge['label'])
                                                    <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-[9px] font-bold {{ $badge['class'] }}">
                                                        {{ $badge['label'] }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="min-h-[70px]"></div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Subject Legend --}}
            @php
                $usedSubjects = collect($this->schedules)->pluck('subject.subject_name')->unique()->filter()->values();
            @endphp
            @if($usedSubjects->isNotEmpty())
                <div class="mt-6 px-4 py-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400 block mb-2">{{ __('Asignaturas:') }}</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach($usedSubjects as $subject)
                            @php $color = $this->getSubjectColor($subject); @endphp
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold"
                                style="background-color: {{ $color['bg'] }}; color: {{ $color['text'] }}; border: 1px solid {{ $color['border'] }};"
                            >
                                {{ $subject }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700 border-dashed">
                <flux:icon.calendar-days class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
                <p class="text-base font-semibold">{{ __('No hay clases programadas para este grado') }}</p>
                <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontraron horarios activos.') }}</p>
            </div>
        @endif
    @else
        {{-- No Tutor Assignment --}}
        <div class="text-center py-16 text-zinc-400 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon.academic-cap class="mx-auto mb-4 size-12 text-zinc-300 dark:text-zinc-600" />
            <p class="text-base font-semibold">{{ __('No se encontró asignación de tutoría') }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ __('No se encontró una asignatura de Acompañamiento integral en el aula asociada a su usuario.') }}</p>
            <p class="text-xs text-zinc-400 mt-1">{{ __('Contacte al administrador para asignar su tutoría.') }}</p>
        </div>
    @endif
</div>

