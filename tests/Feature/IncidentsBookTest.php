<?php

use App\Models\Identity\Users\Teacher;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Models\StudentManagement\Academics\HomeworkPending;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

const INCIDENTS_COMPONENT = 'pages::system.teachers-management.teachers.incidents.index';

function seedIncidentsSchoolContext(): array
{
    $now = now();

    $yearId = DB::table('scolar_years')->insertGetId([
        'year_name' => '2025-2026',
        'start_date' => $now->copy()->subDays(60)->toDateString(),
        'end_date' => $now->copy()->addDays(120)->toDateString(),
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $periodId = DB::table('academic_periods')->insertGetId([
        'year_id' => $yearId,
        'trimester_name' => 'Trimestre I',
        'start_date' => $now->copy()->subDays(30)->toDateString(),
        'end_date' => $now->copy()->addDays(60)->toDateString(),
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $shiftId = DB::table('shifts')->insertGetId([
        'shift_name' => 'Matutina',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $nivelId = DB::table('nivels')->insertGetId([
        'shift_id' => $shiftId,
        'nivel_name' => 'Bachillerato',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $gradeId = DB::table('grades')->insertGetId([
        'nivel_id' => $nivelId,
        'grade_name' => '1° BT',
        'section' => 'A',
        'status' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $areaId = DB::table('areas')->insertGetId([
        'area_name' => 'Tecnología',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $subjectId = DB::table('subjects')->insertGetId([
        'area_id' => $areaId,
        'subject_name' => 'Programación',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return compact('yearId', 'periodId', 'gradeId', 'subjectId');
}

function seedIncidentsTeacher(): User
{
    $teacherUser = User::factory()->create();

    $teacherId = DB::table('teachers')->insertGetId([
        'user_id' => $teacherUser->id,
        'teacher_code' => 'DOC-'.uniqid(),
        'hire_date' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $teacherUser->teacher_id = $teacherId;

    return $teacherUser;
}

function seedIncidentsStudent(string $code): int
{
    $studentUser = User::factory()->create();

    return DB::table('students')->insertGetId([
        'user_id' => $studentUser->id,
        'student_code' => $code,
        'enrollment_date' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedIncidentsSchedule(int $teacherUserId, int $teacherId, array $ctx): int
{
    return DB::table('class_schedules')->insertGetId([
        'year_id' => $ctx['yearId'],
        'teacher_id' => $teacherId,
        'subject_id' => $ctx['subjectId'],
        'grade_id' => $ctx['gradeId'],
        'schedule_type' => 'clase',
        'day' => 'Lunes',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function enrollIncidentsStudent(int $studentId, array $ctx): void
{
    DB::table('student_enrollments')->insert([
        'student_id' => $studentId,
        'grade_id' => $ctx['gradeId'],
        'year_id' => $ctx['yearId'],
        'enrollment_date' => now()->toDateString(),
        'status' => 'active',
        'academic_year' => '2025-2026',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedPendingHomework(int $studentId, int $teacherId, array $ctx): void
{
    DB::table('homework_pendings')->insert([
        'student_id' => $studentId,
        'subject_id' => $ctx['subjectId'],
        'grade_id' => $ctx['gradeId'],
        'teacher_id' => $teacherId,
        'year_id' => $ctx['yearId'],
        'trimester_id' => $ctx['periodId'],
        'description' => 'Tarea pendiente de prueba',
        'due_date' => now()->subDay()->toDateString(),
        'status' => 'not_submitted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedAcademicNotification(int $studentId, int $teacherId, array $ctx): void
{
    DB::table('academic_notifications')->insert([
        'code' => 'NOT-'.uniqid(),
        'notification_number' => 1,
        'type' => 'academico',
        'channel' => 'sistema',
        'student_id' => $studentId,
        'grade_id' => $ctx['gradeId'],
        'subject_id' => $ctx['subjectId'],
        'teacher_id' => $teacherId,
        'year_id' => $ctx['yearId'],
        'trimester_id' => $ctx['periodId'],
        'message' => 'Notificación de prueba',
        'generated_date' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('el libro de incidencias carga para un docente con asignaciones', function () {
    $ctx = seedIncidentsSchoolContext();
    $teacherUser = seedIncidentsTeacher();
    $teacherId = DB::table('teachers')->where('user_id', $teacherUser->id)->value('id');
    seedIncidentsSchedule($teacherUser->id, $teacherId, $ctx);

    $this->actingAs($teacherUser)
        ->get('/system/teacher/incidents')
        ->assertOk()
        ->assertSee('Libro de Incidencias');
});

test('getNextBusinessDay salta el fin de semana sin bucle infinito', function () {
    $seeded = seedIncidentsSchoolContext();
    $teacherUser = seedIncidentsTeacher();
    $this->actingAs($teacherUser);

    Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00'));

    try {
        $next = Livewire::test(INCIDENTS_COMPONENT)
            ->instance()
            ->getNextBusinessDay();

        expect($next)->toBe('2026-08-24');
    } finally {
        Carbon::setTestNow();
    }
});

test('las tres categorias renderizan sin errores incluida asistencia', function () {
    $ctx = seedIncidentsSchoolContext();
    $teacherUser = seedIncidentsTeacher();
    $teacherId = DB::table('teachers')->where('user_id', $teacherUser->id)->value('id');
    seedIncidentsSchedule($teacherUser->id, $teacherId, $ctx);

    $studentA = seedIncidentsStudent('EST-A-001');
    enrollIncidentsStudent($studentA, $ctx);
    seedPendingHomework($studentA, $teacherId, $ctx);
    seedAcademicNotification($studentA, $teacherId, $ctx);

    $this->actingAs($teacherUser);

    $component = Livewire::test(INCIDENTS_COMPONENT);

    $component->set('category', 'comportamentales')->assertSuccessful();
    $component->call('setCategory', 'asistencia')->assertSuccessful();

    expect($component->get('tab'))->toBe('asistencia_list');
});

test('detectStudents mantiene las consultas acotadas aunque crezcan los estudiantes', function () {
    $ctx = seedIncidentsSchoolContext();
    $teacherUser = seedIncidentsTeacher();
    $teacherId = DB::table('teachers')->where('user_id', $teacherUser->id)->value('id');
    seedIncidentsSchedule($teacherUser->id, $teacherId, $ctx);

    foreach (range(1, 8) as $i) {
        $studentId = seedIncidentsStudent("EST-N-{$i}");
        enrollIncidentsStudent($studentId, $ctx);
        seedPendingHomework($studentId, $teacherId, $ctx);
    }

    $this->actingAs($teacherUser);

    DB::enableQueryLog();

    Livewire::test(INCIDENTS_COMPONENT)
        ->set('search', '')
        ->assertSuccessful();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThan(45);
});
