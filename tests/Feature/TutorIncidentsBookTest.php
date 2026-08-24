<?php

use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

const TUTOR_INCIDENTS_COMPONENT = 'pages::system.teachers-management.tutors.incidents.index';

function seedTutoriaSubject(): int
{
    return DB::table('subjects')->insertGetId([
        'area_id' => DB::table('areas')->insertGetId([
            'area_name' => 'Tutoría',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'subject_name' => 'Acompañamiento integral en el aula',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedTutorAssignment(array $ctx): void
{
    DB::table('class_schedules')->insertGetId([
        'year_id' => $ctx['yearId'],
        'teacher_id' => DB::table('teachers')->where('user_id', auth()->id())->value('id'),
        'subject_id' => seedTutoriaSubject(),
        'grade_id' => $ctx['gradeId'],
        'schedule_type' => 'clase',
        'day' => 'Lunes',
        'start_time' => '10:00',
        'end_time' => '11:00',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function resolveCompiledTutorIncidentsClass(): object
{
    $compiled = collect(glob(storage_path('framework/views/livewire/classes/*.php')))
        ->filter(fn ($f) => str_contains((string) file_get_contents($f), 'Libro de Incidencias de Tutoría'))
        ->sortByDesc(fn ($f) => filemtime($f))
        ->first();

    expect($compiled)->not->toBeNull();

    return require $compiled;
}

test('el enlace de whatsapp usa el celular del representante y el mensaje incluye nombre y codigo', function () {
    $ctx = seedIncidentsSchoolContext();
    $tutorUser = seedIncidentsTeacher();
    $this->actingAs($tutorUser);
    seedTutorAssignment($ctx);

    $studentId = seedIncidentsStudent('TUT-WA-1');
    enrollIncidentsStudent($studentId, $ctx);

    $repUser = User::factory()->create([
        'name' => 'María',
        'lastname' => 'Pérez',
        'cellphone' => '0991 234-567',
        'phone' => '022 345-678',
    ]);
    DB::table('representatives')->insert([
        'user_id' => $repUser->id,
        'student_id' => $studentId,
        'relationship' => 'Madre',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $teacherId = DB::table('teachers')->where('user_id', $tutorUser->id)->value('id');
    seedAcademicNotification($studentId, $teacherId, $ctx);

    $this->get('/system/teacher/tutor-incidents')->assertOk();

    $instance = resolveCompiledTutorIncidentsClass();
    $instance->mount();

    $method = new ReflectionMethod($instance, 'buildWhatsAppUrl');
    $notification = AcademicNotification::where('student_id', $studentId)->first();
    $url = $method->invoke($instance, $notification);

    expect($url)->toStartWith('https://wa.me/593991234567?text=')
        ->and(urldecode($url))->toContain('Estimado(a) '.$repUser->fresh()->full_name)
        ->and(urldecode($url))->toContain($notification->code)
        ->and(urldecode($url))->toContain('Adjunto');
});

test('la pagina de incidencias de tutoria carga para el tutor y el no tutor ve aviso', function () {
    $ctx = seedIncidentsSchoolContext();
    $tutorUser = seedIncidentsTeacher();
    $this->actingAs($tutorUser);
    seedTutorAssignment($ctx);

    enrollIncidentsStudent(seedIncidentsStudent('TUT-PAGE-1'), $ctx);

    $this->actingAs($tutorUser)
        ->get('/system/teacher/tutor-incidents')
        ->assertOk()
        ->assertSee('Libro de Incidencias de Tutoría')
        ->assertSee(__('Estudiantes de tutoría: :grado', ['grado' => '1° BT A']))
        ->assertSee(__('Modo Tutoría'));

    $otherUser = seedIncidentsTeacher();

    $this->actingAs($otherUser)
        ->get('/system/teacher/tutor-incidents')
        ->assertOk()
        ->assertSee(__('No tiene asignación de tutoría'));
});

test('detectar muestra solo estudiantes del curso donde es tutor', function () {
    $ctx = seedIncidentsSchoolContext();
    $tutorUser = seedIncidentsTeacher();
    $this->actingAs($tutorUser);
    seedTutorAssignment($ctx);

    // Otro docente dicta Programación en el MISMO grado
    $otherUser = seedIncidentsTeacher();
    $otherTeacherId = DB::table('teachers')->where('user_id', $otherUser->id)->value('id');
    seedIncidentsSchedule($otherUser->id, $otherTeacherId, $ctx);

    // Tutorado en el grado de tutoría + estudiante ajeno en otro grado
    $tuteeId = seedIncidentsStudent('TUT-1');
    enrollIncidentsStudent($tuteeId, $ctx);
    seedPendingHomework($tuteeId, $otherTeacherId, $ctx);

    $otherGradeId = DB::table('grades')->insertGetId([
        'nivel_id' => DB::table('nivels')->insertGetId([
            'shift_id' => DB::table('shifts')->insertGetId([
                'shift_name' => 'Vespertina',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'nivel_name' => 'Bachillerato Vespertino',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'grade_name' => '2° BT',
        'section' => 'B',
        'status' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $outsiderId = seedIncidentsStudent('OTR-1');
    enrollIncidentsStudent($outsiderId, array_merge($ctx, ['gradeId' => $otherGradeId]));

    $this->get('/system/teacher/tutor-incidents')->assertOk();

    $instance = resolveCompiledTutorIncidentsClass();
    $instance->mount();

    $codes = $instance->detectStudents->pluck('student.student_code');

    expect($codes)->toContain('TUT-1')
        ->and($codes)->not->toContain('OTR-1');
});

test('intervenir evidenciar e informar muestran registros del curso aunque los cree otro docente', function () {
    $ctx = seedIncidentsSchoolContext();
    $tutorUser = seedIncidentsTeacher();
    $this->actingAs($tutorUser);
    seedTutorAssignment($ctx);

    $otherTeacherId = DB::table('teachers')->where('user_id', seedIncidentsTeacher()->id)->value('id');

    $tuteeId = seedIncidentsStudent('TUT-2');
    enrollIncidentsStudent($tuteeId, $ctx);

    $interventionId = seedIncidentIntervention($tuteeId, $otherTeacherId, $ctx);

    $now = now();
    $letterId = DB::table('incident_commitment_letters')->insertGetId([
        'code' => 'ACT-TEST-1',
        'sequential_number' => 1,
        'type' => 'comportamental',
        'student_id' => $tuteeId,
        'grade_id' => $ctx['gradeId'],
        'teacher_id' => $otherTeacherId,
        'year_id' => $ctx['yearId'],
        'date' => $now->toDateString(),
        'commitments' => 'Compromiso de prueba generado por otro docente',
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $reportId = DB::table('incident_reports')->insertGetId([
        'code' => 'INF-TEST-1',
        'sequential_number' => 1,
        'type' => 'comportamental',
        'student_id' => $tuteeId,
        'grade_id' => $ctx['gradeId'],
        'teacher_id' => $otherTeacherId,
        'year_id' => $ctx['yearId'],
        'date' => $now->toDateString(),
        'conclusion' => 'Conclusión de prueba generada por otro docente',
        'status' => 'draft',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->get('/system/teacher/tutor-incidents')->assertOk();

    $instance = resolveCompiledTutorIncidentsClass();
    $instance->mount();

    expect($instance->interventions->pluck('id'))->toContain($interventionId);

    $instance->setCategory('comportamentales');
    $instance->setTab('evidenciar');
    expect($instance->commitmentLetters->pluck('id'))->toContain($letterId);

    $instance->setTab('informar');
    expect($instance->reports->pluck('id'))->toContain($reportId);
});

test('asistencia agrupa las inasistencias por asignatura hoy y en la semana', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-19 10:00:00'));

    try {
        $ctx = seedIncidentsSchoolContext();
        $tutorUser = seedIncidentsTeacher();
        $this->actingAs($tutorUser);
        seedTutorAssignment($ctx);

        $areaId = DB::table('subjects')->where('id', $ctx['subjectId'])->value('area_id');
        $designSubjectId = DB::table('subjects')->insertGetId([
            'area_id' => $areaId,
            'subject_name' => 'Diseño Web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $teacherId = DB::table('teachers')->where('user_id', $tutorUser->id)->value('id');
        $programacionScheduleId = DB::table('class_schedules')->insertGetId([
            'year_id' => $ctx['yearId'],
            'teacher_id' => $teacherId,
            'subject_id' => $ctx['subjectId'],
            'grade_id' => $ctx['gradeId'],
            'schedule_type' => 'clase',
            'day' => 'Miércoles',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $designScheduleId = DB::table('class_schedules')->insertGetId([
            'year_id' => $ctx['yearId'],
            'teacher_id' => $teacherId,
            'subject_id' => $designSubjectId,
            'grade_id' => $ctx['gradeId'],
            'schedule_type' => 'clase',
            'day' => 'Martes',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentId = seedIncidentsStudent('TUT-ASIS-1');
        enrollIncidentsStudent($studentId, $ctx);
        $absentStudentId = seedIncidentsStudent('TUT-ASIS-2');
        enrollIncidentsStudent($absentStudentId, $ctx);

        foreach ([
            [$programacionScheduleId, '2026-08-19'],
            [$designScheduleId, '2026-08-19'],
            [$designScheduleId, '2026-08-18'],
        ] as [$scheduleId, $date]) {
            DB::table('attendances')->insert([
                'class_schedule_id' => $scheduleId,
                'year_id' => $ctx['yearId'],
                'student_id' => $studentId,
                'recorded_by' => $tutorUser->id,
                'date' => $date,
                'status' => 'I',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->get('/system/teacher/tutor-incidents')->assertOk();

        $instance = resolveCompiledTutorIncidentsClass();
        $instance->mount();
        $instance->setCategory('asistencia');

        $row = $instance->attendanceRows->first(fn ($r) => $r->student->id === $studentId);
        $absentRow = $instance->attendanceRows->first(fn ($r) => $r->student->id === $absentStudentId);

        expect($row)->not->toBeNull()
            ->and($row->todayCounts)->toBe(['Diseño Web' => 1, 'Programación' => 1])
            ->and($row->weekCounts)->toBe(['Diseño Web' => 2, 'Programación' => 1])
            ->and($instance->attendanceRows)->toHaveCount(2)
            ->and($absentRow)->not->toBeNull()
            ->and($absentRow->todayCounts)->toBe([])
            ->and($absentRow->weekCounts)->toBe([]);
    } finally {
        Carbon::setTestNow();
    }
});

test('el mensaje de inasistencia incluye el dia y las asignaturas y preselecciona motivos', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-19 10:00:00'));

    try {
        $ctx = seedIncidentsSchoolContext();
        $tutorUser = seedIncidentsTeacher();
        $this->actingAs($tutorUser);
        seedTutorAssignment($ctx);

        $teacherId = DB::table('teachers')->where('user_id', $tutorUser->id)->value('id');
        $scheduleAId = DB::table('class_schedules')->insertGetId([
            'year_id' => $ctx['yearId'],
            'teacher_id' => $teacherId,
            'subject_id' => $ctx['subjectId'],
            'grade_id' => $ctx['gradeId'],
            'schedule_type' => 'clase',
            'day' => 'Miércoles',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $studentId = seedIncidentsStudent('TUT-MSG-1');
        enrollIncidentsStudent($studentId, $ctx);

        foreach ([['2026-08-19'], ['2026-08-18']] as [$date]) {
            DB::table('attendances')->insert([
                'class_schedule_id' => $scheduleAId,
                'year_id' => $ctx['yearId'],
                'student_id' => $studentId,
                'recorded_by' => $tutorUser->id,
                'date' => $date,
                'status' => 'I',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->get('/system/teacher/tutor-incidents')->assertOk();

        $instance = resolveCompiledTutorIncidentsClass();
        $instance->mount();
        $message = $instance->buildAttendanceAutoMessage($studentId);

        expect($message)->toContain('Total de inasistencias: 2')
            ->and($message)->toContain('Detalle por día y asignatura:')
            ->and($message)->toContain('Miércoles 19/08/2026')
            ->and($message)->toContain('Martes 18/08/2026')
            ->and($message)->toContain('Programación');

        $fresh = resolveCompiledTutorIncidentsClass();
        $fresh->mount();
        $fresh->setCategory('asistencia');
        $fresh->openNotificationModal($studentId);

        expect($fresh->notifForm['motives'])->toContain('Inasistencia')
            ->and($fresh->notifForm['type'])->toBe('asistencia');
    } finally {
        Carbon::setTestNow();
    }
});
