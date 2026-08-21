<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\TeacherManagement;

use App\Models\TeacherManagement\Academics\ClassSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Horario de clase del docente (consulta).
 *
 * @mixin ClassSchedule
 */
final class ScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ClassSchedule $schedule */
        $schedule = $this->resource;

        $subject = $schedule->subject;
        $grade = $schedule->grade;

        return [
            'id' => $schedule->id,
            'year_id' => $schedule->year_id,
            'teacher_id' => $schedule->teacher_id,
            'schedule_type' => $schedule->schedule_type,
            'day' => $schedule->day,
            'start_time' => $schedule->start_time ? Carbon::parse($schedule->start_time)->format('H:i') : null,
            'end_time' => $schedule->end_time ? Carbon::parse($schedule->end_time)->format('H:i') : null,
            'classroom' => $schedule->classroom,
            'is_active' => (bool) $schedule->is_active,
            'notes' => $schedule->notes,
            'subject' => $subject ? [
                'id' => $subject->id,
                'area_id' => $subject->area_id,
                'area_name' => $subject->area?->area_name,
                'subject_name' => $subject->subject_name,
            ] : null,
            'grade' => $grade ? [
                'id' => $grade->id,
                'grade_name' => $grade->grade_name,
                'section' => $grade->section,
                'nivel_name' => $grade->nivel?->nivel_name,
                'shift_name' => $grade->nivel?->shift?->shift_name,
            ] : null,
            'trimester' => $schedule->trimester ? [
                'id' => $schedule->trimester->id,
                'trimester_name' => $schedule->trimester->trimester_name,
            ] : null,
            'calendar_day' => $schedule->calendarDay ? [
                'id' => $schedule->calendarDay->id,
                'date' => $schedule->calendarDay->date ? Carbon::parse($schedule->calendarDay->date)->toDateString() : null,
                'activity' => $schedule->calendarDay->activity,
                'is_holiday' => (bool) $schedule->calendarDay->is_holiday,
            ] : null,
        ];
    }
}
