<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\StudentManagement\Academics\HomeworkPending;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recalcula en lote la lista de "tareas pendientes" de una actividad para todos
 * los matriculados del curso (H-07). Reemplaza el bucle de updateOrCreate por
 * estudiante que se ejecutaba de forma síncrona en cada guardado de nota
 * (2 consultas × N alumnos). Es idempotente: una sola lectura de las filas
 * existentes, un UPDATE masivo para quienes ya presentaron y un INSERT masivo
 * para los que aún no tienen nota.
 */
class SyncHomeworkPendingForActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $activityId,
    ) {}

    public function handle(): void
    {
        $activity = Activity::with('assessmentBlock')->find($this->activityId);
        if (! $activity || ! $activity->assessmentBlock) {
            return;
        }

        $block = $activity->assessmentBlock;

        $enrolledIds = StudentEnrollment::query()
            ->where('grade_id', $block->grade_id)
            ->where('year_id', $block->year_id)
            ->pluck('student_id')
            ->toArray();

        if ($enrolledIds === []) {
            return;
        }

        $gradedIds = ActivityGrade::query()
            ->where('activity_id', $this->activityId)
            ->whereIn('student_id', $enrolledIds)
            ->whereNotNull('grade')
            ->pluck('student_id')
            ->toArray();

        $gradedIds = array_unique(array_map('intval', $gradedIds));

        if ($gradedIds !== []) {
            HomeworkPending::query()
                ->where('activity_id', $this->activityId)
                ->whereIn('student_id', $gradedIds)
                ->where('status', 'not_submitted')
                ->whereNull('notified_at')
                ->update(['status' => 'submitted']);
        }

        $pendingIds = collect($enrolledIds)
            ->filter(fn ($id): bool => ! in_array($id, $gradedIds, true))
            ->toArray();

        $existingPending = $pendingIds === []
            ? collect()
            : HomeworkPending::query()
                ->where('activity_id', $this->activityId)
                ->whereIn('student_id', $pendingIds)
                ->pluck('student_id');

        if ($pendingIds !== []) {
            HomeworkPending::query()
                ->where('activity_id', $this->activityId)
                ->whereIn('student_id', $pendingIds)
                ->update([
                    'subject_id' => $block->subject_id,
                    'grade_id' => $block->grade_id,
                    'teacher_id' => $block->teacher_id,
                    'year_id' => $block->year_id,
                    'trimester_id' => $block->trimester_id,
                    'description' => 'Tarea no presentada: '.($activity->name ?? ''),
                    'due_date' => $activity->date ?? now(),
                    'status' => 'not_submitted',
                ]);
        }

        $missingIds = collect($pendingIds)
            ->filter(fn ($id): bool => ! $existingPending->contains($id))
            ->toArray();

        if ($missingIds === []) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($missingIds as $sid) {
            $rows[] = [
                'student_id' => $sid,
                'subject_id' => $block->subject_id,
                'grade_id' => $block->grade_id,
                'teacher_id' => $block->teacher_id,
                'year_id' => $block->year_id,
                'trimester_id' => $block->trimester_id,
                'activity_id' => $this->activityId,
                'description' => 'Tarea no presentada: '.($activity->name ?? ''),
                'due_date' => $activity->date ?? $now,
                'status' => 'not_submitted',
                'notified_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        HomeworkPending::insert($rows);
    }
}
