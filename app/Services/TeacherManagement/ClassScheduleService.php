<?php

declare(strict_types=1);

namespace App\Services\TeacherManagement;

use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ClassScheduleService
{
    public function getSchedules(
        int $yearId,
        int $teacherId,
        string $scheduleType,
        ?string $jornada = null,
    ): Collection {
        $query = ClassSchedule::with(['subject.area', 'grade.nivel.shift', 'trimester', 'calendarDay'])
            ->where('year_id', $yearId)
            ->where('teacher_id', $teacherId)
            ->where('schedule_type', $scheduleType)
            ->orderBy('day')
            ->orderBy('start_time');

        $schedules = $query->get();

        if ($jornada && $jornada !== 'TODAS') {
            $schedules = $schedules->filter(
                fn ($h) => optional($h->grade->nivel->shift)->shift_name === $jornada
            );
        }

        return $schedules;
    }

    public function getSchedulesByDay(
        int $yearId,
        int $teacherId,
        string $scheduleType,
        ?string $jornada = null,
    ): array {
        $schedules = $this->getSchedules($yearId, $teacherId, $scheduleType, $jornada);

        $diasMap = [
            'LUNES' => 'Lunes',
            'MARTES' => 'Martes',
            'MIERCOLES' => 'Miércoles',
            'JUEVES' => 'Jueves',
            'VIERNES' => 'Viernes',
        ];

        $result = [];
        foreach ($diasMap as $dbDia => $displayDia) {
            $result[$displayDia] = $schedules
                ->filter(fn ($h) => mb_strtoupper($h->day, 'UTF-8') === $dbDia)
                ->sortBy('start_time')
                ->values();
        }

        return $result;
    }

    public function getStats(
        int $yearId,
        int $teacherId,
        string $scheduleType,
        ?string $jornada = null,
    ): array {
        $schedules = $this->getSchedules($yearId, $teacherId, $scheduleType, $jornada);

        $gradeIds = $schedules->pluck('grade_id')->unique()->toArray();

        $studentCount = 0;
        if (! empty($gradeIds)) {
            $studentCount = StudentEnrollment::whereIn('grade_id', $gradeIds)
                ->where('year_id', $yearId)
                ->count();
        }

        $totalHours = 0;
        foreach ($schedules as $schedule) {
            $minutes = $schedule->start_time->diffInMinutes($schedule->end_time);
            $minutesPerHour = str_contains(
                strtoupper($schedule->grade->grade_name),
                'BT'
            ) ? 40 : 45;

            $totalHours += $minutes / $minutesPerHour;
        }

        return [
            'total_students' => $studentCount,
            'total_subjects' => $schedules->pluck('subject_id')->unique()->count(),
            'total_grades' => $schedules->pluck('grade_id')->unique()->count(),
            'total_slots' => $schedules->count(),
            'active_count' => $schedules->where('is_active', true)->count(),
            'inactive_count' => $schedules->where('is_active', false)->count(),
            'total_hours' => round($totalHours, 1),
        ];
    }

    public function getDistributivo(int $yearId, int $teacherId, string $scheduleType, ?string $jornada = null): array
    {
        $schedules = $this->getSchedules($yearId, $teacherId, $scheduleType, $jornada);

        $grouped = $schedules->groupBy(function ($item) {
            return $item->subject->subject_name.'|'.$item->grade->grade_name.'|'.optional($item->grade->nivel->shift)->shift_name;
        });

        $allGradeIds = $grouped->flatMap(fn ($items) => $items->pluck('grade_id'))->unique()->values()->all();

        $studentCountsByGrade = $allGradeIds !== []
            ? StudentEnrollment::whereIn('grade_id', $allGradeIds)
                ->where('year_id', $yearId)
                ->selectRaw('grade_id, COUNT(*) as total')
                ->groupBy('grade_id')
                ->pluck('total', 'grade_id')
            : collect();

        $result = [];
        foreach ($grouped as $key => $items) {
            [$subjectName, $gradeName, $shiftName] = explode('|', $key);
            $gradeIds = $items->pluck('grade_id')->unique()->toArray();
            $studentCount = 0;
            if (! empty($gradeIds)) {
                $studentCount = collect($gradeIds)
                    ->map(fn ($gid) => $studentCountsByGrade->get($gid, 0))
                    ->sum();
            }

            $result[] = [
                'subject_name' => $subjectName,
                'grade_name' => $gradeName,
                'shift_name' => $shiftName,
                'hours_per_week' => $items->count(),
                'student_count' => $studentCount,
                'schedules' => $items,
            ];
        }

        return $result;
    }

    public function getTodayAgenda(int $yearId, int $teacherId, string $scheduleType, ?string $jornada = null): Collection
    {
        $hoy = ucfirst(now()->isoFormat('dddd'));
        $schedules = $this->getSchedules($yearId, $teacherId, $scheduleType, $jornada);

        return $schedules
            ->filter(fn ($h) => mb_strtoupper($h->day, 'UTF-8') === mb_strtoupper($hoy, 'UTF-8'))
            ->sortBy('start_time')
            ->values();
    }

    public function getTeacherSchedules(int $yearId, int $teacherId): Collection
    {
        return ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->with(['subject', 'grade.nivel.shift'])
            ->orderByRaw("CASE day WHEN 'LUNES' THEN 1 WHEN 'MARTES' THEN 2 WHEN 'MIERCOLES' THEN 3 WHEN 'JUEVES' THEN 4 WHEN 'VIERNES' THEN 5 WHEN 'SABADO' THEN 6 ELSE 7 END")
            ->orderBy('start_time')
            ->get();
    }

    public function getStudentCountForGrade(int $gradeId, int $yearId): int
    {
        return StudentEnrollment::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->where('status', 'active')
            ->count();
    }

    public function loadJornadas(): array
    {
        return Shift::where('status', 1)
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->shift_name])
            ->toArray();
    }

    public function getScheduleTypes(): array
    {
        return [
            'OFFICIAL' => ['name' => 'Horario Oficial',         'color' => 'emerald', 'icon' => 'book-open'],
            'EVALUATION' => ['name' => 'Horario de Evaluacion',   'color' => 'red',     'icon' => 'document-text'],
            'TEST' => ['name' => 'Horario de Prueba',       'color' => 'amber',   'icon' => 'beaker'],
            'MAKEUP' => ['name' => 'Horario de Recuperacion', 'color' => 'purple',  'icon' => 'arrow-path'],
        ];
    }

    public function calculateWeekRange(string $weekStart): array
    {
        $start = Carbon::parse($weekStart);

        return [
            'weekStart' => $start->toDateString(),
            'weekEnd' => $start->copy()->addDays(4)->toDateString(),
        ];
    }

    public function buildWeekDays(string $weekStart): array
    {
        $weekDays = [];
        $start = Carbon::parse($weekStart);
        for ($i = 0; $i < 5; $i++) {
            $fecha = $start->copy()->addDays($i);
            $weekDays[ucfirst($fecha->isoFormat('dddd'))] = $fecha->toDateString();
        }

        return $weekDays;
    }

    public function getInitialExpandedDays(array $weekDays): array
    {
        $hoy = ucfirst(now()->isoFormat('dddd'));
        $dias = array_keys($weekDays);
        $expandedDays = [];

        foreach ($dias as $dia) {
            if ($dia === $hoy) {
                $expandedDays[] = $dia;
                $idx = array_search($dia, $dias);
                if ($idx !== false && isset($dias[$idx + 1])) {
                    $expandedDays[] = $dias[$idx + 1];
                }
                break;
            }
        }

        if (empty($expandedDays)) {
            $expandedDays = array_slice($dias, 0, 2);
        }

        return $expandedDays;
    }

    public function activateAllSchedules(int $teacherId, string $scheduleType): int
    {
        return ClassSchedule::where('teacher_id', $teacherId)
            ->where('schedule_type', $scheduleType)
            ->update(['is_active' => true]);
    }

    public function deactivateAllSchedules(int $teacherId, string $scheduleType): int
    {
        return ClassSchedule::where('teacher_id', $teacherId)
            ->where('schedule_type', $scheduleType)
            ->update(['is_active' => false]);
    }

    public function toggleScheduleActive(int $scheduleId): ?bool
    {
        $schedule = ClassSchedule::find($scheduleId);
        if (! $schedule) {
            return null;
        }
        $schedule->update(['is_active' => ! $schedule->is_active]);

        return $schedule->is_active;
    }

    public function deleteSchedule(int $scheduleId): bool
    {
        $model = ClassSchedule::find($scheduleId);
        if (! $model) {
            return false;
        }
        $model->delete();

        return true;
    }

    public function getDistributivoTotals(Collection $schedules): array
    {
        $totalMinutosBT = 0;
        $totalMinutosEGB = 0;

        foreach ($schedules as $row) {
            foreach ($row['schedules'] as $sch) {
                $mins = $sch->start_time->diffInMinutes($sch->end_time);
                $norm = mb_strtolower(str_replace('_', ' ', \Transliterator::create('Any-Latin; Latin-ASCII; Lower')->transliterate(optional($sch->grade->nivel)->nivel_name ?? '')));
                if (str_contains($norm, 'bachillerato tecnico')) {
                    $totalMinutosBT += $mins;
                } else {
                    $totalMinutosEGB += $mins;
                }
            }
        }

        $totalBloquesBT = $totalMinutosBT > 0 ? round($totalMinutosBT / 40, 1) : 0;
        $totalBloquesEGB = $totalMinutosEGB > 0 ? round($totalMinutosEGB / 45, 1) : 0;

        return [
            'total_minutos_bt' => $totalMinutosBT,
            'total_minutos_egb' => $totalMinutosEGB,
            'total_bloques_bt' => $totalBloquesBT,
            'total_bloques_egb' => $totalBloquesEGB,
            'total_horas' => $totalBloquesBT + $totalBloquesEGB,
        ];
    }

    public function getRowMinutesAndBlock(array $row): array
    {
        $minutosRow = 0;
        foreach ($row['schedules'] as $sch) {
            $minutosRow += $sch->start_time->diffInMinutes($sch->end_time);
        }

        $firstSchedule = $row['schedules']->first();
        $normRow = mb_strtolower(str_replace('_', ' ', \Transliterator::create('Any-Latin; Latin-ASCII; Lower')->transliterate(optional($firstSchedule->grade->nivel)->nivel_name ?? '')));
        $bloqueRow = str_contains($normRow, 'bachillerato tecnico') ? 40 : 45;
        $bloquesRow = intdiv($minutosRow, $bloqueRow);

        return [
            'minutos' => $minutosRow,
            'bloque' => $bloqueRow,
            'bloques' => $bloquesRow,
        ];
    }
}
