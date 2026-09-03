<?php

use App\Models\Identity\Users\Student;
use App\Models\StudentManagement\Academics\AcademicNotification;
use App\Services\Incidents\IncidentPdfService;

it('descarga el PDF de la notificación y marca la impresión tras responder (no en el GET)', function (): void {
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

    // La generación del PDF se mockea para no depender del render de DomPDF.
    $pdf = Mockery::mock();
    $pdf->shouldReceive('download')
        ->once()
        ->with('notificacion-NOT-100.pdf')
        ->andReturn(
            response('pdf')
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="notificacion-NOT-100.pdf"'),
        );

    $incidentPdf = Mockery::mock(IncidentPdfService::class);
    $incidentPdf->shouldReceive('notification')->once()->with($notification->id)->andReturn($pdf);
    $this->app->instance(IncidentPdfService::class, $incidentPdf);

    $user = $context['teacher']->user;
    if (! $user->email_verified_at) {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    $this->actingAs($user)
        ->get(route('admin.teacher.incidents.pdf.notification', $notification->id))
        ->assertOk()
        ->assertHeader('Content-Disposition');

    // defer() difirió la escritura: printed_at queda seteado tras la respuesta.
    expect(AcademicNotification::find($notification->id)->printed_at)->not->toBeNull();
});
