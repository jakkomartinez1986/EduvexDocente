<?php

use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\TeacherManagement\Attendances\ClassObservation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

function attendanceContext(): array
{
    $context = academicContext();

    $students = Student::factory()->count(3)->create();
    $students->each(fn (Student $student) => StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'year_id' => $context['year']->id,
        'academic_year' => $context['year']->year_name,
    ]));

    return [...$context, 'students' => $students];
}

function registerUrl(int $scheduleId, string $date): string
{
    return '/api/v1/teachermanagement/attendances/register?'.http_build_query([
        'schedule_id' => $scheduleId,
        'date' => $date,
    ]);
}

it('muestra el detalle de registro con presente implícito y sin observación', function (): void {
    $context = attendanceContext();
    [$studentA] = $context['students'];
    $teacherUser = $context['teacher']->user;
    $today = now()->toDateString();

    $response = $this->getJson(
        registerUrl($context['schedule']->id, $today),
        bearerTokenFor($teacherUser),
    );

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.schedule.id', $context['schedule']->id)
        ->assertJsonPath('data.date', $today)
        ->assertJsonPath('data.observation', null);

    expect($response->json('data.students'))->toHaveCount(3);

    foreach ($response->json('data.students') as $row) {
        expect($row['status'])->toBe('P');
        expect($row['has_record'])->toBeFalse();
    }

    expect($response->json('data.summary'))->toMatchArray([
        'total' => 3,
        'recorded' => 0,
        'absent' => 0,
        'late' => 0,
        'present' => 3,
    ]);

    // El presente implícito no crea registros en la base de datos.
    expect(Attendance::query()->count())->toBe(0);
    expect(ClassObservation::query()->count())->toBe(0);
});

it('registra el snapshot del día creando filas solo para estados distintos de P', function (): void {
    $context = attendanceContext();
    [$studentA, $studentB, $studentC] = $context['students'];
    $teacherUser = $context['teacher']->user;
    $headers = bearerTokenFor($teacherUser);
    $today = now()->toDateString();

    $payload = [
        'schedule_id' => $context['schedule']->id,
        'date' => $today,
        'classtopic' => 'Ecuaciones cuadráticas',
        'observation' => 'Participación activa del curso.',
        'statuses' => [
            (string) $studentA->id => 'A',
            (string) $studentB->id => 'I',
            (string) $studentC->id => 'P',
        ],
    ];

    $response = $this->putJson('/api/v1/teachermanagement/attendances/register', $payload, $headers);

    $response->assertOk()
        ->assertJsonPath('data.observation.classtopic', 'Ecuaciones cuadráticas');

    // Solo A (atraso) e I (injustificada) generan fila; P queda implícito.
    expect(Attendance::query()->count())->toBe(2);
    expect(ClassObservation::query()->count())->toBe(1);

    $summary = $response->json('data.summary');
    expect($summary['late'])->toBe(1);
    expect($summary['absent'])->toBe(1);
    expect($summary['present'])->toBe(1);

    $statusOf = fn (Student $student): ?string => Attendance::query()
        ->where('student_id', $student->id)
        ->value('status');

    expect($statusOf($studentA))->toBe('A');
    expect($statusOf($studentB))->toBe('I');
    expect($statusOf($studentC))->toBeNull();

    $observation = ClassObservation::query()->first();
    expect($observation->teacher_id)->toBe($context['teacher']->id);
    expect($observation->year_id)->toBe($context['year']->id);
});

it('es idempotente ante reenvíos del mismo snapshot', function (): void {
    $context = attendanceContext();
    [$studentA] = $context['students'];
    $headers = bearerTokenFor($context['teacher']->user);
    $today = now()->toDateString();

    $snapshot = fn (): array => [
        'schedule_id' => $context['schedule']->id,
        'date' => $today,
        'classtopic' => 'Tema estable',
        'statuses' => [(string) $studentA->id => 'J'],
    ];

    $this->putJson('/api/v1/teachermanagement/attendances/register', $snapshot(), $headers)->assertOk();
    $this->putJson('/api/v1/teachermanagement/attendances/register', $snapshot(), $headers)->assertOk();

    expect(Attendance::query()->count())->toBe(1);
    expect(ClassObservation::query()->count())->toBe(1);
});

it('vuelve al presente implícito borrando la fila cuando se reenvía P', function (): void {
    $context = attendanceContext();
    [$studentA] = $context['students'];
    $headers = bearerTokenFor($context['teacher']->user);
    $today = now()->toDateString();

    $base = [
        'schedule_id' => $context['schedule']->id,
        'date' => $today,
        'classtopic' => 'Tema',
    ];

    $this->putJson('/api/v1/teachermanagement/attendances/register', [
        ...$base,
        'statuses' => [(string) $studentA->id => 'I'],
    ], $headers)->assertOk();

    expect(Attendance::query()->count())->toBe(1);

    // Corrección offline: el estudiante estuvo presente (P explícito;
    // los demás estudiantes sin estado quedan como presentes implícitos).
    $response = $this->putJson('/api/v1/teachermanagement/attendances/register', [
        ...$base,
        'statuses' => [(string) $studentA->id => 'P'],
    ], $headers);

    $response->assertOk();

    expect(Attendance::query()->count())->toBe(0);
    expect($response->json('data.summary.present'))->toBe(3);

    // H-07: la corrección a P deja tombstone (soft delete), no borra físico.
    $tombstones = Attendance::withTrashed()->get();
    expect($tombstones)->toHaveCount(1);
    expect($tombstones->first()->trashed())->toBeTrue();
});

it('impide duplicados activos de horario, estudiante y día a nivel de BD', function (): void {
    $context = attendanceContext();
    [$studentA] = $context['students'];
    $today = now()->toDateString();

    Attendance::factory()->create([
        'class_schedule_id' => $context['schedule']->id,
        'student_id' => $studentA->id,
        'date' => $today,
    ]);

    expect(fn () => Attendance::factory()->create([
        'class_schedule_id' => $context['schedule']->id,
        'student_id' => $studentA->id,
        'date' => $today,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('permite reinsertar tras soft deletes conservando los tombstones', function (): void {
    $context = attendanceContext();
    [$studentA] = $context['students'];

    $create = fn () => Attendance::factory()->create([
        'class_schedule_id' => $context['schedule']->id,
        'student_id' => $studentA->id,
        'date' => now()->toDateString(),
    ]);

    $create()->delete();
    $create()->delete();
    $active = $create();

    $rowsForTuple = fn () => Attendance::withTrashed()
        ->where('class_schedule_id', $context['schedule']->id)
        ->where('student_id', $studentA->id)
        ->whereDate('date', now()->toDateString())
        ->get();

    // Los tombstones nunca bloquean re-inserciones: solo una fila activa.
    expect($rowsForTuple())->toHaveCount(3);
    expect($rowsForTuple()->filter(fn (Attendance $row): bool => $row->trashed()))->toHaveCount(2);
    expect($rowsForTuple()->filter(fn (Attendance $row): bool => ! $row->trashed())->pluck('id'))->toEqual(collect([$active->id]));
});

it('admite el ciclo completo A, P e I del mismo estudiante dejando fila única', function (): void {
    $context = attendanceContext();
    [$studentA] = $context['students'];
    $headers = bearerTokenFor($context['teacher']->user);
    $today = now()->toDateString();

    $base = [
        'schedule_id' => $context['schedule']->id,
        'date' => $today,
        'classtopic' => 'Tema',
    ];

    $this->putJson('/api/v1/teachermanagement/attendances/register', [
        ...$base,
        'statuses' => [(string) $studentA->id => 'A'],
    ], $headers)->assertOk();

    $this->putJson('/api/v1/teachermanagement/attendances/register', [
        ...$base,
        'statuses' => [(string) $studentA->id => 'P'],
    ], $headers)->assertOk();

    $this->putJson('/api/v1/teachermanagement/attendances/register', [
        ...$base,
        'statuses' => [(string) $studentA->id => 'I'],
    ], $headers)->assertOk();

    // Un tombstone de la corrección a P + la fila activa final con estado I.
    $all = Attendance::withTrashed()
        ->where('student_id', $studentA->id)
        ->orderBy('id')
        ->get();

    expect($all)->toHaveCount(2);
    expect($all->first()->trashed())->toBeTrue();
    expect($all->last()->status)->toBe('I');
});

it('rechaza estados de estudiantes no matriculados', function (): void {
    $context = attendanceContext();
    $outsider = Student::factory()->create();

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->putJson('/api/v1/teachermanagement/attendances/register', [
        'schedule_id' => $context['schedule']->id,
        'date' => now()->toDateString(),
        'classtopic' => 'Tema',
        'statuses' => [(string) $outsider->id => 'I'],
    ], bearerTokenFor($user))->assertStatus(422)
        ->assertJsonValidationErrors(['statuses']);
});

it('valida que los estados pertenezcan al catálogo P,A,I,J,AI,AA', function (): void {
    $context = attendanceContext();
    [$studentA] = $context['students'];

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->putJson('/api/v1/teachermanagement/attendances/register', [
        'schedule_id' => $context['schedule']->id,
        'date' => now()->toDateString(),
        'classtopic' => 'Tema',
        'statuses' => [(string) $studentA->id => 'X'],
    ], bearerTokenFor($user))->assertStatus(422);
});

it('responde 404 para horarios de otro docente', function (): void {
    $context = attendanceContext();
    $foreignSchedule = ClassSchedule::factory()->create();

    /** @var User $user */
    $user = $context['teacher']->user;

    $this->getJson(
        registerUrl($foreignSchedule->id, now()->toDateString()),
        bearerTokenFor($user),
    )->assertStatus(404);
});

it('descarga asistencias y observaciones ya registradas del docente', function (): void {
    $context = attendanceContext();
    [$studentA] = $context['students'];
    $teacherUser = $context['teacher']->user;
    $headers = bearerTokenFor($teacherUser);
    $today = now()->toDateString();

    $this->putJson('/api/v1/teachermanagement/attendances/register', [
        'schedule_id' => $context['schedule']->id,
        'date' => $today,
        'classtopic' => 'Tema descargable',
        'statuses' => [(string) $studentA->id => 'AI'],
    ], $headers)->assertOk();

    $response = $this->getJson('/api/v1/teachermanagement/attendances?'.http_build_query([
        'schedule_id' => $context['schedule']->id,
        'date' => $today,
    ]), $headers);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.observations')
        ->assertJsonCount(1, 'data.attendances');

    expect($response->json('data.attendances.0.status'))->toBe('AI');
    expect($response->json('data.attendances.0.student_id'))->toBe($studentA->id);
    expect($response->json('data.observations.0.classtopic'))->toBe('Tema descargable');
});

it('resume asistencias por período derivando presentes de clases impartidas', function (): void {
    $context = attendanceContext();
    [$studentA, $studentB, $studentC] = $context['students'];
    $teacherUser = $context['teacher']->user;
    $headers = bearerTokenFor($teacherUser);

    // Dos clases impartidas (dos observaciones) en días distintos.
    foreach ([now()->subDay()->toDateString(), now()->toDateString()] as $day) {
        $this->putJson('/api/v1/teachermanagement/attendances/register', [
            'schedule_id' => $context['schedule']->id,
            'date' => $day,
            'classtopic' => "Tema {$day}",
            'statuses' => [(string) $studentA->id => 'A'],
        ], $headers)->assertOk();
    }

    $response = $this->getJson('/api/v1/teachermanagement/attendances/summary?'.http_build_query([
        'trimester_id' => $context['trimester']->id,
    ]), $headers);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data.students');

    $rows = collect($response->json('data.students'))->keyBy('student_id');

    $a = $rows->get($studentA->id);
    expect($a['total_classes'])->toBe(2);
    expect($a['late_count'])->toBe(2);
    expect($a['present_count'])->toBe(0);
    expect($a['attendance_rate'])->toEqual(0.0);

    $b = $rows->get($studentB->id);
    expect($b['total_classes'])->toBe(2);
    expect($b['present_count'])->toBe(2);
    expect($b['attendance_rate'])->toEqual(100.0);

    $totals = $response->json('data.totals');
    expect($totals['total_classes'])->toBe(6); // 2 clases × 3 estudiantes
    expect($totals['late_count'])->toBe(2);
});
