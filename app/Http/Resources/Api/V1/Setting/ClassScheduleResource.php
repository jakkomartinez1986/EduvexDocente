<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Setting;

use App\Models\TeacherManagement\Academics\ClassSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin ClassSchedule
 */
final class ClassScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ClassSchedule $schedule */
        $schedule = $this->resource;

        return [
            'id' => $schedule->id,
            'year_id' => $schedule->year_id,
            'teacher_id' => $schedule->teacher_id,
            'subject_id' => $schedule->subject_id,
            'grade_id' => $schedule->grade_id,
            'trimester_id' => $schedule->trimester_id,
            'calendarday_id' => $schedule->calendarday_id,
            'schedule_type' => $schedule->schedule_type,
            'day' => $schedule->day,
            'start_time' => Carbon::parse($schedule->start_time)->format('H:i'),
            'end_time' => Carbon::parse($schedule->end_time)->format('H:i'),
            'classroom' => $schedule->classroom,
            'is_active' => (bool) $schedule->is_active,
            'notes' => $schedule->notes,
            'subject' => $this->whenLoaded('subject', fn () => new SubjectResource($schedule->subject)),
            'grade' => $this->whenLoaded('grade', fn () => new GradeResource($schedule->grade)),
            'trimester' => $this->whenLoaded('trimester', fn () => new AcademicPeriodResource($schedule->trimester)),
            'calendar_day' => $this->whenLoaded('calendarDay', fn () => new CalendarDayResource($schedule->calendarDay)),
        ];
    }
}
