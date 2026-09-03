<?php

declare(strict_types=1);

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Services\Academic\GradebookReportComputer;

function reportContext(): array
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

    [$activityA, $activityB] = Activity::factory()->count(2)->create([
        'assessment_block_id' => $block->id,
        'max_score' => 10,
    ]);

    ActivityGrade::factory()->create([
        'activity_id' => $activityA->id,
        'student_id' => $students[0]->id,
        'grade' => 8.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);
    ActivityGrade::factory()->create([
        'activity_id' => $activityB->id,
        'student_id' => $students[0]->id,
        'grade' => 6.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    StudentExam::create([
        'student_id' => $students[0]->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'grade' => 9.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    StudentProject::create([
        'student_id' => $students[0]->id,
        'subject_id' => $context['subject']->id,
        'grade_id' => $context['grade']->id,
        'trimester_id' => $context['trimester']->id,
        'year_id' => $context['year']->id,
        'grade' => 5.0,
        'recorded_by' => $context['teacher']->user_id,
    ]);

    return [...$context, 'students' => $students, 'block' => $block];
}

it('compute los agregados del reporte preservando la semántica de los reportes', function (): void {
    $context = reportContext();
    $student = $context['students'][0];

    // Esquema 80/14/6 del academicContext.
    $result = app(GradebookReportComputer::class)->classTrimesterAggregates(
        (int) $context['year']->id,
        (int) $context['subject']->id,
        (int) $context['grade']->id,
        (int) $context['teacher']->id,
        (int) $context['trimester']->id,
        [(int) $student->id],
        $context['scheme'],
    );

    $row = $result[$student->id];

    // Bloque 1 con 2 actividades: (8 + 6) / 2 = 7 → floor(7*100)/100 = 7.0.
    expect($row['formative'])->toBe(7.0)
        ->and($row['exam'])->toBe(9.0)
        ->and($row['project'])->toBe(5.0)
        // total = 7*0.80 + 9*0.14 + 5*0.06 = 5.6 + 1.26 + 0.3 = 7.16
        ->and($row['total'])->toBe(7.16);
});

it('devuelve total null cuando la clase no tiene ningún dato', function (): void {
    // Clase sin bloques (y sin exámenes/proyectos) para el caso "sin datos":
    // alumno matriculado pero sin estructura de notas.
    $noDataContext = academicContext();
    $student = Student::factory()->create();
    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $noDataContext['grade']->id,
        'year_id' => $noDataContext['year']->id,
        'academic_year' => $noDataContext['year']->year_name,
    ]);

    $result = app(GradebookReportComputer::class)->classTrimesterAggregates(
        (int) $noDataContext['year']->id,
        (int) $noDataContext['subject']->id,
        (int) $noDataContext['grade']->id,
        (int) $noDataContext['teacher']->id,
        (int) $noDataContext['trimester']->id,
        [(int) $student->id],
        $noDataContext['scheme'],
    );

    expect($result[$student->id]['formative'])->toBeNull()
        ->and($result[$student->id]['total'])->toBeNull();
});
