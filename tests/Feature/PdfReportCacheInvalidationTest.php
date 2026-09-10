<?php

declare(strict_types=1);

use App\Actions\Academic\SaveGradeAction;
use App\Actions\TeacherManagement\SaveQuickGradesAction;
use App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion\ReadingPromotionIndicator;
use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Services\Academic\PdfReportCache;
use App\Services\Academic\QualitativeGradeService;
use Illuminate\Support\Facades\Cache;

function pdfInvalidationContext(): array
{
    $context = academicContext();

    $students = Student::factory()->count(2)->create();
    $students->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]));

    $block = AssessmentBlock::factory()->create([
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'teacher_id' => $context['teacher']->id,
        'name' => 'Bloque 1',
    ]);

    $activity = Activity::factory()->create([
        'assessment_block_id' => $block->id,
        'max_score' => 10,
    ]);

    return [...$context, 'students' => $students, 'block' => $block, 'activity' => $activity];
}

function pdfKey(PdfReportCache $cache, string $type, array $params, array $buckets): string
{
    return $cache->key($type, $params, $buckets);
}

it('guardar una nota invalida el caché de PDFs del docente, materia y grado', function (): void {
    Cache::flush();
    $context = pdfInvalidationContext();
    $this->actingAs($context['teacher']->user);

    $cache = app(PdfReportCache::class);
    $gradeId = (int) $context['grade']->id;
    $subjectId = (int) $context['subject']->id;
    $teacherId = (int) $context['teacher']->id;

    $params = ['subject' => $subjectId, 'grade' => $gradeId];
    $buckets = ["teacher:{$teacherId}", "subject-grade:{$subjectId}:{$gradeId}"];
    $before = pdfKey($cache, 'subject-annual', $params, $buckets);

    app(SaveGradeAction::class)((int) $context['activity']->id, (int) $context['students'][0]->id, 9);

    $after = pdfKey($cache, 'subject-annual', $params, $buckets);

    expect($after)->not->toBe($before);
});

it('guardar notas en lote invalida el caché de PDFs de los estudiantes afectados', function (): void {
    Cache::flush();
    $context = pdfInvalidationContext();
    $this->actingAs($context['teacher']->user);

    $cache = app(PdfReportCache::class);
    $studentA = (int) $context['students'][0]->id;
    $studentB = (int) $context['students'][1]->id;

    $beforeA = pdfKey($cache, 'student-annual', ['student' => $studentA], ['student:'.$studentA]);
    $beforeB = pdfKey($cache, 'student-annual', ['student' => $studentB], ['student:'.$studentB]);

    app(SaveQuickGradesAction::class)->handle(
        (int) $context['activity']->id,
        [$studentA => '9', $studentB => '7'],
        (int) $context['teacher']->user->id,
    );

    $afterA = pdfKey($cache, 'student-annual', ['student' => $studentA], ['student:'.$studentA]);
    $afterB = pdfKey($cache, 'student-annual', ['student' => $studentB], ['student:'.$studentB]);

    expect($afterA)->not->toBe($beforeA)
        ->and($afterB)->not->toBe($beforeB);
});

it('invalidar un estudiante no afecta los PDFs de otros estudiantes', function (): void {
    Cache::flush();
    $context = pdfInvalidationContext();
    $this->actingAs($context['teacher']->user);

    $cache = app(PdfReportCache::class);
    $studentA = (int) $context['students'][0]->id;
    $studentB = (int) $context['students'][1]->id;

    $beforeB = pdfKey($cache, 'student-annual', ['student' => $studentB], ['student:'.$studentB]);

    $cache->invalidateForStudent($studentA);

    $afterB = pdfKey($cache, 'student-annual', ['student' => $studentB], ['student:'.$studentB]);

    expect($afterB)->toBe($beforeB);
});

it('guardar una nota cualitativa invalida el caché de PDFs del grado y estudiante', function (): void {
    Cache::flush();
    $context = pdfInvalidationContext();
    $this->actingAs($context['teacher']->user);

    $cache = app(PdfReportCache::class);
    $gradeId = (int) $context['grade']->id;
    $subjectId = (int) $context['subject']->id;
    $studentId = (int) $context['students'][0]->id;

    $before = pdfKey($cache, 'qualitative', ['subject' => $subjectId, 'grade' => $gradeId], ["subject-grade:{$subjectId}:{$gradeId}"]);

    $indicator = ReadingPromotionIndicator::create([
        'name' => 'Indicador de prueba',
        'order' => 1,
    ]);

    app(QualitativeGradeService::class)->saveGrade(
        'reading_promotion',
        $studentId,
        (int) $indicator->id,
        '5',
        (int) $context['year']->id,
        $subjectId,
        $gradeId,
        (int) $context['trimester']->id,
    );

    $after = pdfKey($cache, 'qualitative', ['subject' => $subjectId, 'grade' => $gradeId], ["subject-grade:{$subjectId}:{$gradeId}"]);

    expect($after)->not->toBe($before);
});
