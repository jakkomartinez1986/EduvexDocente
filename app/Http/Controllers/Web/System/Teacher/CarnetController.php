<?php

namespace App\Http\Controllers\Web\System\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Services\AcademicYearService;
use App\Services\SchoolConfigService;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class CarnetController extends Controller
{
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

    private function generateQrSvg(string $data): string
    {
        $options = new QROptions([
            'outputBase64' => false,
            'drawLightModules' => false,
            'moduleValues' => [
                'light' => '#ffffff',
                'dark' => '#374151',
            ],
        ]);

        return (new QRCode($options))->render($data);
    }

    public function bulkPdf()
    {
        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        if (! $tutorSchedule) {
            return redirect()->back()->with('error', 'No se encontró asignación de tutoría.');
        }

        $gradeId = $tutorSchedule->grade_id;
        $gradeName = trim(($tutorSchedule->grade->grade_name ?? '').' '.($tutorSchedule->grade->section ?? ''));
        $shiftName = $tutorSchedule->grade->nivel->shift->shift_name ?? 'MATUTINA';

        $studentIds = StudentEnrollment::where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->where('status', 'active')
            ->pluck('student_id');

        $students = Student::whereIn('id', $studentIds)
            ->with(['user'])
            ->orderByRaw("COALESCE(NULLIF((SELECT u.lastname FROM users u WHERE u.id = students.user_id), ''), 'zzz')")
            ->get();

        $school = app(SchoolConfigService::class)->getActiveSchool();
        $year = app(AcademicYearService::class)->getActiveYear();
        $schoolName = $school?->name_school ?? 'Institución Educativa';
        $yearName = $year?->year_name ?? '2026-2027';

        $carnets = $students->map(function ($student) use ($school, $schoolName, $yearName, $gradeName) {
            $name = $student->user?->name ?? '';
            $lastname = $student->user?->lastname ?? '';
            $initials = mb_strtoupper(mb_substr($lastname, 0, 1)).mb_strtoupper(mb_substr($name, 0, 1));

            // Si no hay iniciales, usar las primeras letras del nombre completo
            if (empty($initials) || $initials == '') {
                $fullname = $student->user?->fullname ?? $student->full_name ?? '';
                $parts = explode(' ', trim($fullname));
                $initials = '';
                foreach ($parts as $part) {
                    if (! empty($part)) {
                        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                    }
                }
                $initials = substr($initials, 0, 2);
            }

            $qrData = $schoolName.' | '.$student->student_code.' | '.$student->user?->fullname.' | '.$gradeName.' | '.$yearName;

            return [
                'student' => $student,
                'initials' => $initials ?: '??',
                'photoUrl' => $student->user?->profile_photo_path
                    ? asset('storage/'.$student->user->profile_photo_path)
                    : null,
                'logoUrl' => $school?->logo_path
                    ? asset('storage/'.$school->logo_path)
                    : null,
                'verificationCode' => strtoupper($student->student_code.'-'.$yearName),
                'qrSvg' => $this->generateQrSvg($qrData),
            ];
        })->toArray();

        // ===== CONFIGURACIÓN PARA PDF =====
        $pdf = Pdf::loadView('pages.system.identity.students.carnet.pdf-bulk', [
            'carnets' => $carnets,
            'school' => $school,
            'year' => $year,
            'gradeName' => $gradeName,
            'shiftName' => $shiftName,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '4mm');
        $pdf->setOption('marginBottom', '4mm');
        $pdf->setOption('marginLeft', '6mm');
        $pdf->setOption('marginRight', '6mm');

        return $pdf->download("carnets-{$gradeName}-{$year->year_name}.pdf");
    }

    /**
     * Generar carnet individual en PDF
     */
    public function individualPdf(int $id)
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

        if (! $enrollment) {
            return redirect()->back()->with('error', 'El estudiante no tiene matrícula activa.');
        }

        $gradeName = trim(($enrollment->grade->grade_name ?? '').' '.($enrollment->grade->section ?? ''));
        $shiftName = $enrollment->grade->nivel->shift->shift_name ?? '';
        $yearName = $year?->year_name ?? '-';
        $schoolName = $school?->name_school ?? 'Institución Educativa';

        $name = $student->user?->name ?? '';
        $lastname = $student->user?->lastname ?? '';
        $initials = mb_strtoupper(mb_substr($lastname, 0, 1)).mb_strtoupper(mb_substr($name, 0, 1));

        $qrData = $schoolName.' | '.$student->student_code.' | '.$student->user?->fullname.' | '.$gradeName.' | '.$yearName;

        $carnet = [
            'student' => $student,
            'initials' => $initials,
            'photoUrl' => $student->user?->profile_photo_path
                ? asset('storage/'.$student->user->profile_photo_path)
                : null,
            'logoUrl' => $school?->logo_path
                ? asset('storage/'.$school->logo_path)
                : null,
            'verificationCode' => strtoupper($student->student_code.'-'.$yearName),
            'qrSvg' => $this->generateQrSvg($qrData),
        ];

        $pdf = Pdf::loadView('pages.system.identity.students.carnet.pdf-individual', [
            'carnet' => $carnet,
            'school' => $school,
            'year' => $year,
            'gradeName' => $gradeName,
            'shiftName' => $shiftName,
        ]);

        // TAMAÑO PARA CARNET INDIVIDUAL (estándar 85.6mm x 54mm)
        $pdf->setPaper([0, 0, 101.6, 66.0], 'portrait'); // 101.6mm x 66mm (con márgenes)
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('marginTop', '0');
        $pdf->setOption('marginBottom', '0');
        $pdf->setOption('marginLeft', '0');
        $pdf->setOption('marginRight', '0');

        return $pdf->download("carnet-{$student->student_code}.pdf");
    }
}
