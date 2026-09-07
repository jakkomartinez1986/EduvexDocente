<?php

namespace App\Http\Controllers\Web\System\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\System\Teacher\Concerns\DispatchesAsyncPdfReports;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use App\Services\Reports\PdfReportRenderer;
use App\Services\SchoolConfigService;

class CarnetController extends Controller
{
    use DispatchesAsyncPdfReports;

    public function individual(int $id)
    {
        $student = Student::query()
            ->with(['user', 'enrollments.grade.nivel.shift'])
            ->findOrFail($id);

        $school = app(SchoolConfigService::class)->getActiveSchool();
        $year = app(AcademicYearService::class)->getActiveYear();
        $yearId = app(AcademicYearService::class)->getActiveYearId();
        $enrollment = $student->enrollments()
            ->where('year_id', $yearId)
            ->with('grade.nivel.shift')
            ->first();

        return view('pages.system.identity.students.carnet.index', [
            'student' => $student,
            'school' => $school,
            'year' => $year,
            'enrollment' => $enrollment,
        ]);
    }

    public function bulkPdf()
    {
        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $hasTutorAssignment = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->exists();

        if (! $hasTutorAssignment) {
            return redirect()->back()->with('error', 'No se encontró asignación de tutoría.');
        }

        return $this->asyncPdf(
            PdfReportRenderer::CARNET_BULK,
            null,
            [
                'teacher_id' => $teacherId,
                'teacher_name' => auth()->user()?->fullname ?? '',
            ],
            'Carnets del grado',
        );
    }

    /**
     * Generar carnet individual en PDF (async).
     */
    public function individualPdf(int $id)
    {
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $hasEnrollment = StudentEnrollment::where('student_id', $id)
            ->where('year_id', $yearId)
            ->where('status', 'active')
            ->exists();

        if (! $hasEnrollment) {
            return redirect()->back()->with('error', 'El estudiante no tiene matrícula activa.');
        }

        return $this->asyncPdf(
            PdfReportRenderer::CARNET_INDIVIDUAL,
            $id,
            [
                'student_id' => $id,
                'teacher_id' => auth()->user()->teacher?->id,
                'teacher_name' => auth()->user()?->fullname ?? '',
            ],
            'Carnet estudiantil',
        );
    }
}
