<?php

use App\Jobs\GeneratePdfReport;
use App\Models\Identity\Users\Student;
use App\Models\StudentManagement\Academics\AcademicNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

it('encola la generación del PDF y marca la impresión tras responder (no en el GET)', function (): void {
    Bus::fake();

    $context = academicContext();
    $student = Student::factory()->create();

    $notification = AcademicNotification::create([
        'code' => 'NOT-100',
        'notification_number' => 1,
        'type' => 'academico',
        'channel' => 'sistema',
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'subject_id' => $context['subject']->id,
        'teacher_id' => $context['teacher']->id,
        'year_id' => $context['year']->id,
        'trimester_id' => $context['trimester']->id,
        'message' => 'Citación de prueba',
        'generated_date' => now()->toDateString(),
    ]);

    $user = $context['teacher']->user;
    if (! $user->email_verified_at) {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    $this->actingAs($user)
        ->get(route('admin.teacher.incidents.pdf.notification', $notification->id))
        ->assertOk()
        ->assertViewIs('reports.processing')
        ->assertSee('Generando Notificación');

    Bus::assertDispatched(GeneratePdfReport::class, fn (GeneratePdfReport $job) => $job->type === 'incident_notification'
        && $job->entityId === $notification->id
        && $job->context === []);

    // defer() difirió la escritura: printed_at queda seteado tras la respuesta.
    expect(AcademicNotification::find($notification->id)->printed_at)->not->toBeNull();
});

it('sirve la URL firmada sin re-encolar cuando el reporte ya está persistido', function (): void {
    Bus::fake();

    $context = academicContext();
    $student = Student::factory()->create();

    $notification = AcademicNotification::create([
        'code' => 'NOT-200',
        'notification_number' => 1,
        'type' => 'academico',
        'channel' => 'sistema',
        'student_id' => $student->id,
        'grade_id' => $context['grade']->id,
        'subject_id' => $context['subject']->id,
        'teacher_id' => $context['teacher']->id,
        'year_id' => $context['year']->id,
        'trimester_id' => $context['trimester']->id,
        'message' => 'Citación ya generada',
        'generated_date' => now()->toDateString(),
    ]);

    Storage::fake('local');

    Storage::disk('local')->put('reports/incidents/notificacion-NOT-200.pdf', 'pdf-contenido');

    $user = $context['teacher']->user;
    if (! $user->email_verified_at) {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    $this->actingAs($user)
        ->get(route('admin.teacher.incidents.pdf.notification', $notification->id))
        ->assertRedirect();

    Bus::assertNotDispatched(GeneratePdfReport::class);
});
