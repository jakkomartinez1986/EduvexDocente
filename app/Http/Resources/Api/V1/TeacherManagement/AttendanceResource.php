<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\TeacherManagement;

use App\Models\TeacherManagement\Attendances\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Attendance
 */
final class AttendanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Attendance $attendance */
        $attendance = $this->resource;

        return [
            'id' => $attendance->id,
            'class_observation_id' => $attendance->class_observation_id,
            'class_schedule_id' => $attendance->class_schedule_id,
            'calendarday_id' => $attendance->calendarday_id,
            'year_id' => $attendance->year_id,
            'tutor_id' => $attendance->tutor_id,
            'student_id' => $attendance->student_id,
            'date' => Carbon::parse($attendance->date)->toDateString(),
            'status' => $attendance->status,
            'arrival_time' => $attendance->arrival_time
                ? Carbon::parse($attendance->arrival_time)->format('H:i')
                : null,
            'justification' => $attendance->justification,
            'justification_file_path' => $attendance->justification_file_path,
            'observation' => $attendance->observation,
            'recorded_by' => $attendance->recorded_by,
            'notification_data' => $attendance->notification_data,
            'notification_sent_at' => $attendance->notification_sent_at?->toISOString(),
        ];
    }
}
