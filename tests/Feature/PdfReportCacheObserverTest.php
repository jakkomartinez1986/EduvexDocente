<?php

declare(strict_types=1);

use App\Models\Academic\GradeBook\Cualitatives\CareerGuidance\CareerGuidance;
use App\Models\Academic\GradeBook\Cualitatives\CareerGuidance\CareerGuidanceIndicator;
use App\Models\Academic\GradeBook\Cualitatives\ClassroomSupport\IntegralClassroomSupport;
use App\Models\Academic\GradeBook\Cualitatives\ClassroomSupport\IntegralClassroomSupportIndicator;
use App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion\ReadingPromotion;
use App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion\ReadingPromotionIndicator;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Academic\GradeBook\Summaries\Supplementary\SupplementaryExam;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Services\Academic\PdfReportCache;
use App\Services\Reports\GradebookPdfService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * El observer central (PdfReportCacheObserver) debe subir los buckets correctos
 * en cada escritura Eloquent para que el nombre versionado del reporte cambie
 * y el flujo async no sirva un PDF desactualizado.
 */

/**
 * @param  array<string, mixed>  $context
 * @return array<string, mixed>
 */
function pdfObserverContext(int $studentCount = 2): array
{
    $context = academicContext();

    $students = Student::factory()->count($studentCount)->create();
    $students->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]));

    return [...$context, 'students' => $students];
}

/**
 * @param  array<string, mixed>  $context
 */
function pdfObserverBlock(array $context): AssessmentBlock
{
    return AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
    ]);
}

/**
 * Captura las versiones de los buckets de un estudiante en su asignatura-grado.
 *
 * @return array{subject: int, teacher: int, student: int}
 */
function pdfObserverVersions(int $subjectId, int $gradeId, int $teacherId, int $studentId): array
{
    $cache = app(PdfReportCache::class);

    return [
        'subject' => (int) $cache->version("subject-grade:{$subjectId}:{$gradeId}"),
        'teacher' => (int) $cache->version("teacher:{$teacherId}"),
        'student' => (int) $cache->version("student:{$studentId}"),
    ];
}

it('sube subject-grade, teacher y student al guardar una nota por actividad', function (): void {
    $context = pdfObserverContext();
    $block = pdfObserverBlock($context);
    $activity = Activity::factory()->create(['assessment_block_id' => $block->id, 'max_score' => 10]);
    [$student, $other] = $context['students'];

    Cache::flush();

    $before = pdfObserverVersions((int) $context['subject']->id, (int) $context['grade']->id, (int) $context['teacher']->id, (int) $student->id);
    $otherBefore = (int) app(PdfReportCache::class)->version('student:'.$other->id);

    ActivityGrade::create([
        'activity_id' => $activity->id,
        'student_id' => $student->id,
        'grade' => 8.5,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    $after = pdfObserverVersions((int) $context['subject']->id, (int) $context['grade']->id, (int) $context['teacher']->id, (int) $student->id);

    expect($after['subject'])->toBeGreaterThan($before['subject'])
        ->and($after['teacher'])->toBeGreaterThan($before['teacher'])
        ->and($after['student'])->toBeGreaterThan($before['student'])
        ->and((int) app(PdfReportCache::class)->version('student:'.$other->id))->toBe($otherBefore);
});

it('sube los buckets de TODA la clase al crear un bloque', function (): void {
    $context = pdfObserverContext();
    [$studentA, $studentB] = $context['students'];

    Cache::flush();

    $beforeA = pdfObserverVersions((int) $context['subject']->id, (int) $context['grade']->id, (int) $context['teacher']->id, (int) $studentA->id);
    $beforeB = (int) app(PdfReportCache::class)->version('student:'.$studentB->id);

    pdfObserverBlock($context);

    $afterA = pdfObserverVersions((int) $context['subject']->id, (int) $context['grade']->id, (int) $context['teacher']->id, (int) $studentA->id);

    expect($afterA['subject'])->toBeGreaterThan($beforeA['subject'])
        ->and($afterA['teacher'])->toBeGreaterThan($beforeA['teacher'])
        ->and($afterA['student'])->toBeGreaterThan($beforeA['student'])
        ->and((int) app(PdfReportCache::class)->version('student:'.$studentB->id))->toBeGreaterThan($beforeB);
});

it('sube los buckets de TODA la clase al eliminar una actividad', function (): void {
    $context = pdfObserverContext();
    $block = pdfObserverBlock($context);
    $activity = Activity::factory()->create(['assessment_block_id' => $block->id, 'max_score' => 10]);
    [$studentA, $studentB] = $context['students'];

    Cache::flush();

    $beforeA = pdfObserverVersions((int) $context['subject']->id, (int) $context['grade']->id, (int) $context['teacher']->id, (int) $studentA->id);
    $beforeB = (int) app(PdfReportCache::class)->version('student:'.$studentB->id);

    $activity->delete();

    $afterA = pdfObserverVersions((int) $context['subject']->id, (int) $context['grade']->id, (int) $context['teacher']->id, (int) $studentA->id);

    expect($afterA['subject'])->toBeGreaterThan($beforeA['subject'])
        ->and($afterA['teacher'])->toBeGreaterThan($beforeA['teacher'])
        ->and($afterA['student'])->toBeGreaterThan($beforeA['student'])
        ->and((int) app(PdfReportCache::class)->version('student:'.$studentB->id))->toBeGreaterThan($beforeB);
});

it('sube subject-grade y student al eliminar (soft) y restaurar una nota', function (): void {
    $context = pdfObserverContext();
    $block = pdfObserverBlock($context);
    $activity = Activity::factory()->create(['assessment_block_id' => $block->id, 'max_score' => 10]);
    [$student] = $context['students'];

    $grade = ActivityGrade::create([
        'activity_id' => $activity->id,
        'student_id' => $student->id,
        'grade' => 9.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    Cache::flush();

    $beforeDelete = (int) app(PdfReportCache::class)->version('student:'.$student->id);
    $grade->delete();
    $afterDelete = (int) app(PdfReportCache::class)->version('student:'.$student->id);
    $grade->restore();
    $afterRestore = (int) app(PdfReportCache::class)->version('student:'.$student->id);

    expect($afterDelete)->toBeGreaterThan($beforeDelete)
        ->and($afterRestore)->toBeGreaterThan($afterDelete);
});

it('sube subject-grade y student al escribir cualitativas y sumas, sin tocar teacher', function (): void {
    $context = pdfObserverContext();
    [$student] = $context['students'];
    $userId = $context['teacher']->user_id;

    $ovpIndicator = CareerGuidanceIndicator::create([
        'name' => 'Autoconcepto',
        'eje' => 'Socioemocional',
        'grade_id' => $context['grade']->id,
    ]);
    $aiacIndicator = IntegralClassroomSupportIndicator::create([
        'name' => 'Comprensión lectora',
        'eje' => 'Habilidades Cognitivas',
    ]);
    $readingIndicator = ReadingPromotionIndicator::create(['name' => 'Lectura comprensiva']);

    $scopedWrite = [
        'student_id' => $student->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'value' => 'S',
        'recorded_by' => $userId,
    ];

    $writes = [
        fn (): Model => StudentExam::create([
            'subject_id' => $context['subject']->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $context['trimester']->id,
            'year_id' => $context['year']->id,
            'student_id' => $student->id,
            'grade' => 9.0,
            'recorded_by' => $userId,
        ]),
        fn (): Model => StudentProject::create([
            'subject_id' => $context['subject']->id,
            'grade_id' => $context['grade']->id,
            'trimester_id' => $context['trimester']->id,
            'year_id' => $context['year']->id,
            'student_id' => $student->id,
            'grade' => 6.0,
            'recorded_by' => $userId,
        ]),
        fn (): Model => SupplementaryExam::create([
            'student_id' => $student->id,
            'subject_id' => $context['subject']->id,
            'grade_id' => $context['grade']->id,
            'year_id' => $context['year']->id,
            'grade' => 7.0,
            'recorded_by' => $userId,
        ]),
        fn (): Model => CareerGuidance::create([...$scopedWrite, 'indicator_id' => $ovpIndicator->id]),
        fn (): Model => IntegralClassroomSupport::create([...$scopedWrite, 'skill_id' => $aiacIndicator->id]),
        fn (): Model => ReadingPromotion::create([...$scopedWrite, 'indicator_id' => $readingIndicator->id]),
    ];

    foreach ($writes as $write) {
        Cache::flush();

        $before = pdfObserverVersions((int) $context['subject']->id, (int) $context['grade']->id, (int) $context['teacher']->id, (int) $student->id);

        $write();

        $after = pdfObserverVersions((int) $context['subject']->id, (int) $context['grade']->id, (int) $context['teacher']->id, (int) $student->id);

        expect($after['subject'])->toBeGreaterThan($before['subject'])
            ->and($after['student'])->toBeGreaterThan($before['student'])
            ->and($after['teacher'])->toBe($before['teacher']);
    }
});

it('sube subject-grade, teacher y student al crear un registro de asistencia', function (): void {
    $context = pdfObserverContext();
    [$student] = $context['students'];
    $schedule = $context['schedule'];
    $teacherId = (int) $schedule->teacher_id;

    Cache::flush();

    $before = pdfObserverVersions((int) $schedule->subject_id, (int) $schedule->grade_id, $teacherId, (int) $student->id);

    Attendance::create([
        'class_schedule_id' => $schedule->id,
        'student_id' => $student->id,
        'date' => now()->toDateString(),
        'year_id' => $context['year']->id,
        'status' => 'A',
        'recorded_by' => $context['teacher']->user_id,
    ]);

    $after = pdfObserverVersions((int) $schedule->subject_id, (int) $schedule->grade_id, $teacherId, (int) $student->id);

    expect($after['subject'])->toBeGreaterThan($before['subject'])
        ->and($after['teacher'])->toBeGreaterThan($before['teacher'])
        ->and($after['student'])->toBeGreaterThan($before['student']);
});

it('cambia el nombre del reporte sumativo sin invalidación manual al guardar un examen vía Eloquent', function (): void {
    $context = pdfObserverContext();
    [$student] = $context['students'];

    $ctx = [
        'teacher_id' => $context['teacher']->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
    ];

    Cache::flush();

    $service = app(GradebookPdfService::class);
    $before = $service->filename(GradebookPdfService::SUMMATIVE, $ctx);

    StudentExam::create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'student_id' => $student->id,
        'grade' => 9.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    $after = $service->filename(GradebookPdfService::SUMMATIVE, $ctx);

    expect($before)->toEndWith('.pdf')
        ->and($after)->toEndWith('.pdf')
        ->and($before)->not->toBe($after);
});
