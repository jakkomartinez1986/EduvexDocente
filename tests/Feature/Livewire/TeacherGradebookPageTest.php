<?php

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Render del libro de calificaciones (SFC de página) como docente.
 * Verifica que la carga inicial sea correcta y que la memoización de
 * períodos académicos evite el N+1 por fila de estudiante (C-01).
 */
it('renderiza el libro de calificaciones sin errores y controla el número de consultas', function (): void {
    $context = academicContext();

    Student::factory()->count(5)->create()->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]));

    $teacher = $context['teacher'];

    DB::enableQueryLog();

    Livewire::actingAs($teacher->user)
        ->test('pages::system.teachers-management.teachers.gradebook.index')
        ->assertOk()
        ->assertSet('selectedSubjectId', $context['subject']->id)
        ->assertSet('selectedGradeId', $context['grade']->id)
        ->assertSet('selectedTrimesterId', $context['trimester']->id);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // El render completo de la página (con estudiantes y bloques) usa la
    // memoización de períodos: sin ella cada isGradingOpen/isSumativaAvailable
    // por fila lanzaría una consulta SELECT a academic_periods. El umbral
    // garantiza que no haya N+1 por estudiante.
    $academicPeriodQueries = collect($queries)->filter(
        fn (array $q) => str_contains((string) $q['query'], 'academic_periods')
    )->count();

    expect($academicPeriodQueries)->toBeLessThan(3)
        ->and(count($queries))->toBeLessThan(50);
});
