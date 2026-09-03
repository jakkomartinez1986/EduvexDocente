<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

function filtersAdminUser(): User
{
    $role = Role::firstOrCreate(
        ['name' => 'SUPER-ADMIN', 'guard_name' => 'web'],
        ['description' => 'Super Administrador'],
    );

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @return array<string, int>
 */
function seedFilterSchoolContext(): array
{
    $now = now();

    $shiftId = DB::table('shifts')->insertGetId([
        'shift_name' => 'Matutina Filtros',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $nivelBachilleratoId = DB::table('nivels')->insertGetId([
        'shift_id' => $shiftId,
        'nivel_name' => 'Bachillerato',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $nivelPrimariaId = DB::table('nivels')->insertGetId([
        'shift_id' => $shiftId,
        'nivel_name' => 'Primaria',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $nivelSecundariaId = DB::table('nivels')->insertGetId([
        'shift_id' => $shiftId,
        'nivel_name' => 'Secundaria',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $gradeBachAId = DB::table('grades')->insertGetId([
        'nivel_id' => $nivelBachilleratoId,
        'grade_name' => '1° BT',
        'section' => 'A',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $gradeBachBId = DB::table('grades')->insertGetId([
        'nivel_id' => $nivelBachilleratoId,
        'grade_name' => '1° BT',
        'section' => 'B',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $gradePrimariaAId = DB::table('grades')->insertGetId([
        'nivel_id' => $nivelPrimariaId,
        'grade_name' => '8vo EGB',
        'section' => 'A',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $gradeBachCId = DB::table('grades')->insertGetId([
        'nivel_id' => $nivelBachilleratoId,
        'grade_name' => '2° BT',
        'section' => 'A',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $gradeSecundariaAId = DB::table('grades')->insertGetId([
        'nivel_id' => $nivelSecundariaId,
        'grade_name' => '1° EGB',
        'section' => 'A',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $areaId = DB::table('areas')->insertGetId([
        'area_name' => 'Ciencias',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $subjectMatematicaId = DB::table('subjects')->insertGetId([
        'area_id' => $areaId,
        'subject_name' => 'Matemática',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $subjectSistemasId = DB::table('subjects')->insertGetId([
        'area_id' => $areaId,
        'subject_name' => 'Sistemas Operativos',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $subjectQuimicaId = DB::table('subjects')->insertGetId([
        'area_id' => $areaId,
        'subject_name' => 'Química',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $teacherAnaUserId = User::factory()->create(['name' => 'ANA', 'lastname' => 'GARCIA'])->id;
    $teacherLuisUserId = User::factory()->create(['name' => 'LUIS', 'lastname' => 'PEREZ'])->id;

    $teacherAnaId = DB::table('teachers')->insertGetId([
        'user_id' => $teacherAnaUserId,
        'teacher_code' => 'DOC-FILT-'.Str::random(6),
        'hire_date' => $now->toDateString(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $teacherLuisId = DB::table('teachers')->insertGetId([
        'user_id' => $teacherLuisUserId,
        'teacher_code' => 'DOC-FILT-'.Str::random(6),
        'hire_date' => $now->toDateString(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $yearId = DB::table('scolar_years')->insertGetId([
        'year_name' => '20'.Str::random(4),
        'start_date' => $now->copy()->subDays(120)->toDateString(),
        'end_date' => $now->copy()->addDays(200)->toDateString(),
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $periodoActualId = DB::table('academic_periods')->insertGetId([
        'year_id' => $yearId,
        'trimester_name' => 'Periodo Actual',
        'start_date' => $now->copy()->subDays(10)->toDateString(),
        'end_date' => $now->copy()->addDays(20)->toDateString(),
        'is_supletorio' => false,
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $periodoPasadoId = DB::table('academic_periods')->insertGetId([
        'year_id' => $yearId,
        'trimester_name' => 'Periodo Pasado',
        'start_date' => $now->copy()->subDays(90)->toDateString(),
        'end_date' => $now->copy()->subDays(30)->toDateString(),
        'is_supletorio' => false,
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    foreach ([
        ['grade_id' => $gradeBachAId, 'subject_id' => $subjectMatematicaId, 'teacher_id' => $teacherAnaId],
        ['grade_id' => $gradeBachAId, 'subject_id' => $subjectSistemasId, 'teacher_id' => $teacherLuisId],
        ['grade_id' => $gradeBachCId, 'subject_id' => $subjectSistemasId, 'teacher_id' => $teacherLuisId],
        ['grade_id' => $gradeSecundariaAId, 'subject_id' => $subjectMatematicaId, 'teacher_id' => $teacherLuisId],
        ['grade_id' => $gradePrimariaAId, 'subject_id' => $subjectQuimicaId, 'teacher_id' => $teacherAnaId],
    ] as $schedule) {
        DB::table('class_schedules')->insert(array_merge($schedule, [
            'year_id' => $yearId,
            'schedule_type' => 'OFFICIAL',
            'day' => 'lunes',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    return [
        'nivel_bachillerato_id' => $nivelBachilleratoId,
        'nivel_primaria_id' => $nivelPrimariaId,
        'nivel_secundaria_id' => $nivelSecundariaId,
        'grade_bach_a_id' => $gradeBachAId,
        'grade_bach_b_id' => $gradeBachBId,
        'grade_bach_c_id' => $gradeBachCId,
        'grade_primaria_a_id' => $gradePrimariaAId,
        'grade_secundaria_a_id' => $gradeSecundariaAId,
        'subject_matematica_id' => $subjectMatematicaId,
        'subject_sistemas_id' => $subjectSistemasId,
        'subject_quimica_id' => $subjectQuimicaId,
        'teacher_ana_id' => $teacherAnaId,
        'teacher_ana_user_id' => $teacherAnaUserId,
        'teacher_luis_id' => $teacherLuisId,
        'teacher_luis_user_id' => $teacherLuisUserId,
        'year_id' => $yearId,
        'periodo_actual_id' => $periodoActualId,
        'periodo_pasado_id' => $periodoPasadoId,
    ];
}

/**
 * @param  array<string, int>  $ctx
 * @param  array<string, mixed>  $overrides
 */
function seedFilteredNotification(array $ctx, array $overrides = []): int
{
    $now = now();

    $studentUserId = User::factory()->create([
        'name' => $overrides['student_name'] ?? 'ESTUDIANTE',
        'lastname' => $overrides['student_lastname'] ?? Str::random(8),
    ])->id;

    $studentId = DB::table('students')->insertGetId([
        'user_id' => $studentUserId,
        'student_code' => 'STU-FILT-'.Str::random(6),
        'enrollment_date' => $now->toDateString(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return DB::table('academic_notifications')->insertGetId(array_merge([
        'code' => 'NOT-FILT-'.Str::random(6),
        'type' => 'incidencia',
        'channel' => 'email',
        'message' => 'Mensaje de prueba',
        'student_id' => $studentId,
        'grade_id' => $ctx['grade_bach_a_id'],
        'subject_id' => $ctx['subject_matematica_id'],
        'teacher_id' => $ctx['teacher_ana_id'],
        'year_id' => $ctx['year_id'],
        'trimester_id' => $ctx['periodo_actual_id'],
        'generated_date' => $now->toDateString(),
        'parent_attended' => null,
        'printed_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], $overrides));
}

function filterComponent()
{
    return Livewire\Livewire::test('pages::system.teachers-management.teachers.notifications.index');
}

test('el periodo actual se preselecciona automaticamente en el componente', function () {
    $ctx = seedFilterSchoolContext();
    $this->actingAs(filtersAdminUser());

    $component = filterComponent();

    expect($component->instance()->selectedTrimesterId)->toBe($ctx['periodo_actual_id']);
});

test('sin periodo actual ningun periodo queda preseleccionado', function () {
    $now = now();

    $yearId = DB::table('scolar_years')->insertGetId([
        'year_name' => '20'.Str::random(4),
        'start_date' => $now->copy()->addDays(300)->toDateString(),
        'end_date' => $now->copy()->addDays(600)->toDateString(),
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    foreach ([
        [$now->copy()->addDays(310)->toDateString(), $now->copy()->addDays(380)->toDateString()],
        [$now->copy()->addDays(400)->toDateString(), $now->copy()->addDays(470)->toDateString()],
    ] as [$start, $end]) {
        DB::table('academic_periods')->insert([
            'year_id' => $yearId,
            'trimester_name' => 'Futuro '.Str::random(4),
            'start_date' => $start,
            'end_date' => $end,
            'is_supletorio' => false,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $this->actingAs(filtersAdminUser());

    expect(filterComponent()->instance()->selectedTrimesterId)->toBeNull();
});

test('seleccionar nivel carga solo sus grados', function () {
    $ctx = seedFilterSchoolContext();
    $this->actingAs(filtersAdminUser());

    $component = filterComponent()->instance();
    $component->updatedSelectedNivelId((string) $ctx['nivel_bachillerato_id']);

    $grados = $component->grados;

    expect($grados)->toContain('1° BT')
        ->and($grados)->not->toContain('8vo EGB');
});

test('seleccionar grado carga solo los paralelos de ese grado', function () {
    $ctx = seedFilterSchoolContext();
    $this->actingAs(filtersAdminUser());

    $component = filterComponent()->instance();
    $component->updatedSelectedNivelId((string) $ctx['nivel_bachillerato_id']);
    $component->updatedSelectedGradeName('1° BT');

    $paraleloIds = $component->paralelos->pluck('id')->all();

    expect($paraleloIds)->toContain($ctx['grade_bach_a_id'])
        ->and($paraleloIds)->toContain($ctx['grade_bach_b_id'])
        ->and($paraleloIds)->not->toContain($ctx['grade_primaria_a_id']);
});

test('seleccionar paralelo carga solo las asignaturas dictadas en el ano activo', function () {
    $ctx = seedFilterSchoolContext();
    $this->actingAs(filtersAdminUser());

    $component = filterComponent()->instance();
    $component->updatedSelectedNivelId((string) $ctx['nivel_bachillerato_id']);
    $component->updatedSelectedGradeName('1° BT');
    $component->updatedSelectedGradeId((string) $ctx['grade_bach_a_id']);

    $asignaturas = $component->asignaturas->pluck('id')->all();

    expect($asignaturas)->toContain($ctx['subject_matematica_id'])
        ->and($asignaturas)->toContain($ctx['subject_sistemas_id'])
        ->and($asignaturas)->not->toContain($ctx['subject_quimica_id']);
});

test('un docente autenticado solo ve niveles grados y asignaturas de sus clases', function () {
    $ctx = seedFilterSchoolContext();
    $this->actingAs(User::find($ctx['teacher_ana_user_id']));

    $component = filterComponent()->instance();

    $niveles = $component->niveis->pluck('id')->all();

    expect($niveles)->toContain($ctx['nivel_bachillerato_id'])
        ->and($niveles)->toContain($ctx['nivel_primaria_id'])
        ->and($niveles)->not->toContain($ctx['nivel_secundaria_id']);

    $component->updatedSelectedNivelId((string) $ctx['nivel_bachillerato_id']);

    expect($component->grados)->toContain('1° BT')
        ->and($component->grados)->not->toContain('2° BT');

    $component->updatedSelectedGradeName('1° BT');
    $component->updatedSelectedGradeId((string) $ctx['grade_bach_a_id']);

    expect($component->asignaturas->pluck('id')->all())->toBe([$ctx['subject_matematica_id']]);
});

test('las notificaciones del docente se filtran automaticamente a las suyas', function () {
    $ctx = seedFilterSchoolContext();
    seedFilteredNotification($ctx);
    seedFilteredNotification($ctx, [
        'grade_id' => $ctx['grade_bach_a_id'],
        'subject_id' => $ctx['subject_sistemas_id'],
        'teacher_id' => $ctx['teacher_luis_id'],
    ]);

    $this->actingAs(filtersAdminUser());
    $component = filterComponent();

    expect($component->instance()->stats['total'])->toBe(2)
        ->and(count($component->instance()->studentGroups))->toBe(2);

    $this->actingAs(User::find($ctx['teacher_ana_user_id']));
    $component = filterComponent();

    expect($component->instance()->stats['total'])->toBe(1)
        ->and(count($component->instance()->studentGroups))->toBe(1);
});

test('cambiar el nivel limpia en cascada las selecciones dependientes', function () {
    $ctx = seedFilterSchoolContext();
    $this->actingAs(filtersAdminUser());

    $component = filterComponent();

    $component->set('selectedNivelId', (string) $ctx['nivel_bachillerato_id'])
        ->set('selectedGradeName', '1° BT')
        ->set('selectedGradeId', (string) $ctx['grade_bach_a_id'])
        ->set('selectedSubjectId', (string) $ctx['subject_matematica_id'])
        ->set('selectedNivelId', (string) $ctx['nivel_primaria_id']);

    expect($component->instance()->selectedGradeName)->toBeNull()
        ->and($component->instance()->selectedGradeId)->toBeNull()
        ->and($component->instance()->selectedSubjectId)->toBeNull();
});

test('un identificador fuera del contexto del padre es rechazado por el backend', function () {
    $ctx = seedFilterSchoolContext();
    $this->actingAs(filtersAdminUser());

    $component = filterComponent();

    $component->set('selectedNivelId', (string) $ctx['nivel_bachillerato_id']);

    $component->set('selectedGradeId', (string) $ctx['grade_primaria_a_id']);

    expect($component->instance()->selectedGradeId)->toBeNull();

    $component->set('selectedGradeName', '1° BT');
    $component->set('selectedSubjectId', (string) $ctx['subject_quimica_id']);

    expect($component->instance()->selectedSubjectId)->toBeNull();
});

test('la cadena de filtros aplica al listado de estudiantes y a las estadisticas', function () {
    $ctx = seedFilterSchoolContext();
    $this->actingAs(filtersAdminUser());

    seedFilteredNotification($ctx);
    seedFilteredNotification($ctx, [
        'grade_id' => $ctx['grade_primaria_a_id'],
        'subject_id' => $ctx['subject_quimica_id'],
        'teacher_id' => $ctx['teacher_ana_id'],
    ]);
    seedFilteredNotification($ctx, [
        'trimester_id' => $ctx['periodo_pasado_id'],
    ]);

    $component = filterComponent();

    expect(count($component->instance()->studentGroups))->toBe(2)
        ->and($component->instance()->stats['total'])->toBe(2);

    $component->set('selectedNivelId', (string) $ctx['nivel_bachillerato_id']);

    expect(count($component->instance()->studentGroups))->toBe(1)
        ->and($component->instance()->stats['total'])->toBe(1);

    $component->set('selectedGradeName', '1° BT')
        ->set('selectedGradeId', (string) $ctx['grade_bach_a_id'])
        ->set('selectedSubjectId', (string) $ctx['subject_sistemas_id']);

    expect(count($component->instance()->studentGroups))->toBe(0)
        ->and($component->instance()->stats['total'])->toBe(0);

    $component->set('selectedSubjectId', (string) $ctx['subject_matematica_id']);

    expect(count($component->instance()->studentGroups))->toBe(1)
        ->and($component->instance()->stats['total'])->toBe(1);
});

test('el listado pagina las notificaciones agrupadas por estudiante', function () {
    $ctx = seedFilterSchoolContext();

    foreach (range(1, 30) as $i) {
        seedFilteredNotification($ctx, [
            'code' => 'NOT-PAG-'.$i,
            'generated_date' => now()->subDays(30 - $i)->toDateString(),
        ]);
    }

    $this->actingAs(filtersAdminUser());
    $component = filterComponent();

    $pager = $component->instance()->studentPager();

    expect($pager->hasPages())->toBeTrue()
        ->and($pager->currentPage())->toBe(1)
        ->and($pager->lastPage())->toBe(2)
        ->and(count($component->instance()->studentGroups))->toBe(25);

    $component->call('gotoPage', 2);

    $pager2 = $component->instance()->studentPager();

    expect($pager2->currentPage())->toBe(2)
        ->and($pager2->hasMorePages())->toBeFalse()
        ->and(count($component->instance()->studentGroups))->toBe(5);
});

test('cambiar un filtro vuelve a la primera pagina del listado', function () {
    $ctx = seedFilterSchoolContext();

    foreach (range(1, 30) as $i) {
        seedFilteredNotification($ctx, [
            'code' => 'NOT-RES-'.$i,
            'generated_date' => now()->subDays(30 - $i)->toDateString(),
        ]);
    }

    $this->actingAs(filtersAdminUser());
    $component = filterComponent();

    $component->call('gotoPage', 2);

    expect($component->instance()->studentPager()->currentPage())->toBe(2);

    $component->set('selectedNivelId', (string) $ctx['nivel_bachillerato_id']);

    expect($component->instance()->studentPager()->currentPage())->toBe(1);
});
