<?php

namespace App\Http\Controllers\Web\System\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Academic\GradeBook\Cualitatives\CareerGuidance\CareerGuidance;
use App\Models\Academic\GradeBook\Cualitatives\CareerGuidance\CareerGuidanceIndicator;
use App\Models\Academic\GradeBook\Cualitatives\ClassroomSupport\IntegralClassroomSupport;
use App\Models\Academic\GradeBook\Cualitatives\ClassroomSupport\IntegralClassroomSupportIndicator;
use App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion\ReadingPromotion;
use App\Models\Academic\GradeBook\Cualitatives\ReadingPromotion\ReadingPromotionIndicator;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentExam;
use App\Models\Academic\GradeBook\Summaries\Subjects\StudentProject;
use App\Models\Academic\GradeBook\Summaries\Supplementary\SupplementaryExam;
use App\Models\Identity\Users\Student;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\GradingScheme;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Models\User;
use App\Services\Academic\GradebookReportComputer;
use App\Services\AcademicYearService;
use App\Services\SchoolConfigService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GradebookPdfController extends Controller
{
    public function __construct(private readonly GradebookReportComputer $reportComputer) {}

    public function formative(Request $request)
    {
        $data = $this->buildContext($request);

        $studentIds = collect($data['students'])->pluck('id')->all();

        $blocks = AssessmentBlock::where('year_id', $data['year_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('grade_id', $data['grade_id'])
            ->where('trimester_id', $data['trimester_id'])
            ->where('teacher_id', $data['teacher_id'])
            ->with(['activities.grades' => function ($q) use ($studentIds) {
                $q->whereIn('student_id', $studentIds);
            }])
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();

        $pdf = Pdf::loadView('pdf.gradebook-formative', $data + [
            'blocks' => $blocks,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '2.5mm');
        $pdf->setOption('marginBottom', '2.5mm');
        $pdf->setOption('marginLeft', '3mm');
        $pdf->setOption('marginRight', '3mm');

        $filename = 'Notas_Formativas_'.str_replace(['/', '\\', ':'], '-', $data['gradeName'] ?? '').'_'.($data['trimesterName'] ?? '').'.pdf';

        return $pdf->download($filename);
    }

    public function summative(Request $request)
    {
        $data = $this->buildContext($request);

        $studentIds = collect($data['students'])->pluck('id')->all();

        $exams = StudentExam::where('year_id', $data['year_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('grade_id', $data['grade_id'])
            ->where('trimester_id', $data['trimester_id'])
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $projects = StudentProject::where('year_id', $data['year_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('grade_id', $data['grade_id'])
            ->where('trimester_id', $data['trimester_id'])
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        $blocks = AssessmentBlock::where('year_id', $data['year_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('grade_id', $data['grade_id'])
            ->where('trimester_id', $data['trimester_id'])
            ->where('teacher_id', $data['teacher_id'])
            ->with(['activities.grades' => function ($q) use ($studentIds) {
                $q->whereIn('student_id', $studentIds);
            }])
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();

        $pdf = Pdf::loadView('pdf.gradebook-summative', $data + [
            'exams' => $exams,
            'projects' => $projects,
            'blocks' => $blocks,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '2.5mm');
        $pdf->setOption('marginBottom', '2.5mm');
        $pdf->setOption('marginLeft', '3mm');
        $pdf->setOption('marginRight', '3mm');

        $filename = 'Notas_Sumativas_'.str_replace(['/', '\\', ':'], '-', $data['gradeName'] ?? '').'_'.($data['trimesterName'] ?? '').'.pdf';

        return $pdf->download($filename);
    }

    public function tutorStudentReport(Request $request)
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
        ]);

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        abort_if(! $tutorSchedule, 404, __('No se encontró asignación de tutoría.'));

        $gradeId = $tutorSchedule->grade_id;

        $student = Student::where('id', $validated['student_id'])
            ->whereHas('enrollments', fn ($q) => $q->where('grade_id', $gradeId)->where('year_id', $yearId))
            ->with('user')
            ->first();

        abort_if(! $student, 404, __('Estudiante no encontrado en el grado del tutor.'));

        $studentId = $validated['student_id'];
        $subjectIds = ClassSchedule::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->pluck('subject_id');

        $trimesters = AcademicPeriod::where('year_id', $yearId)
            ->where('status', 1)
            ->where('is_supletorio', false)
            ->orderBy('id')
            ->get();

        $subjectsById = Subject::whereIn('id', $subjectIds)->get()->keyBy('id');
        $loaded = $this->reportComputer->loadClassData($yearId, $gradeId, $subjectIds->all(), $trimesters->pluck('id')->all(), [$studentId]);

        $subjectsData = [];
        foreach ($subjectsById as $subjectId => $subject) {
            $subjectGrades = [];
            foreach ($trimesters as $period) {
                $cell = $subjectId.'|'.$period->id;
                $formative = $this->reportComputer->formativeByStudent(
                    $loaded->blocks->get($cell) ?? collect(),
                    [$studentId],
                )[$studentId];

                $exam = $loaded->exams->get($cell)?->get($studentId);
                $project = $loaded->projects->get($cell)?->get($studentId);

                $subjectGrades[] = [
                    'trimester' => $period->trimester_name,
                    'formative' => $formative,
                    'exam' => $exam?->grade,
                    'project' => $project?->grade,
                ];
            }

            $subjectsData[] = [
                'name' => $subject->subject_name,
                'trimesters' => $subjectGrades,
            ];
        }

        $school = app(SchoolConfigService::class)->getActiveSchool();
        $year = ScolarYear::find($yearId);
        $gradingScheme = GradingScheme::where('year_id', $yearId)->where('status', 1)->first();
        $inspector = User::whereHas('roles', fn ($q) => $q->where('name', 'INSPECTOR'))->first();

        $gradeData = $tutorSchedule->grade;
        $gradeName = ($gradeData->grade_name ?? '').' '.($gradeData->section ?? '');

        $pdf = Pdf::loadView('pdf.tutor-student-report', [
            'school' => $school,
            'student' => $student,
            'subjectsData' => $subjectsData,
            'gradeName' => $gradeName,
            'shiftName' => $gradeData->nivel?->shift?->shift_name ?? '',
            'yearName' => $year->year_name ?? '',
            'teacherName' => auth()->user()?->fullname ?? '',
            'inspectorName' => $inspector?->fullname ?? '',
            'gradingScheme' => $gradingScheme,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '2.5mm');
        $pdf->setOption('marginBottom', '2.5mm');
        $pdf->setOption('marginLeft', '3mm');
        $pdf->setOption('marginRight', '3mm');

        $studentName = trim(($student->user->lastname ?? '').' '.($student->user->name ?? ''));
        $filename = 'Reporte_Notas_'.str_replace(['/', '\\', ':'], '-', $studentName).'.pdf';

        return $pdf->download($filename);
    }

    public function tutorStudentReportByTrimester(Request $request)
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'trimester_id' => ['required', 'integer'],
        ]);

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        abort_if(! $tutorSchedule, 404, __('No se encontró asignación de tutoría.'));

        $gradeId = $tutorSchedule->grade_id;

        $student = Student::where('id', $validated['student_id'])
            ->whereHas('enrollments', fn ($q) => $q->where('grade_id', $gradeId)->where('year_id', $yearId))
            ->with('user')
            ->first();

        abort_if(! $student, 404, __('Estudiante no encontrado en el grado del tutor.'));

        $period = AcademicPeriod::find($validated['trimester_id']);
        abort_if(! $period || $period->is_supletorio, 404, __('Trimestre no encontrado.'));

        $studentId = $validated['student_id'];
        $subjectIds = ClassSchedule::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->pluck('subject_id');

        $subjectsById = Subject::whereIn('id', $subjectIds)->get()->keyBy('id');
        $loaded = $this->reportComputer->loadClassData($yearId, $gradeId, $subjectIds->all(), [$period->id], [$studentId]);

        $subjectsData = [];
        foreach ($subjectsById as $subjectId => $subject) {
            $cell = $subjectId.'|'.$period->id;
            $formative = $this->reportComputer->formativeByStudent(
                $loaded->blocks->get($cell) ?? collect(),
                [$studentId],
            )[$studentId];

            $exam = $loaded->exams->get($cell)?->get($studentId);
            $project = $loaded->projects->get($cell)?->get($studentId);

            $subjectsData[] = [
                'name' => $subject->subject_name,
                'formative' => $formative,
                'exam' => $exam?->grade,
                'project' => $project?->grade,
            ];
        }

        $school = app(SchoolConfigService::class)->getActiveSchool();
        $year = ScolarYear::find($yearId);
        $gradingScheme = GradingScheme::where('year_id', $yearId)->where('status', 1)->first();
        $inspector = User::whereHas('roles', fn ($q) => $q->where('name', 'INSPECTOR'))->first();

        $gradeData = $tutorSchedule->grade;
        $gradeName = ($gradeData->grade_name ?? '').' '.($gradeData->section ?? '');

        $pdf = Pdf::loadView('pdf.tutor-student-report-trimester', [
            'school' => $school,
            'student' => $student,
            'subjectsData' => $subjectsData,
            'trimesterName' => $period->trimester_name,
            'gradeName' => $gradeName,
            'shiftName' => $gradeData->nivel?->shift?->shift_name ?? '',
            'yearName' => $year->year_name ?? '',
            'teacherName' => auth()->user()?->fullname ?? '',
            'inspectorName' => $inspector?->fullname ?? '',
            'gradingScheme' => $gradingScheme,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '2.5mm');
        $pdf->setOption('marginBottom', '2.5mm');
        $pdf->setOption('marginLeft', '3mm');
        $pdf->setOption('marginRight', '3mm');

        $studentName = trim(($student->user->lastname ?? '').' '.($student->user->name ?? ''));
        $filename = 'Reporte_Notas_'.str_replace(['/', '\\', ':'], '-', $studentName).'_'.str_replace(['/', '\\', ':'], '-', $period->trimester_name).'.pdf';

        return $pdf->download($filename);
    }

    public function tutorStudentFormativeByTrimester(Request $request)
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'trimester_id' => ['required', 'integer'],
        ]);

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        abort_if(! $tutorSchedule, 404, __('No se encontró asignación de tutoría.'));

        $gradeId = $tutorSchedule->grade_id;

        $student = Student::where('id', $validated['student_id'])
            ->whereHas('enrollments', fn ($q) => $q->where('grade_id', $gradeId)->where('year_id', $yearId))
            ->with('user')
            ->first();

        abort_if(! $student, 404, __('Estudiante no encontrado en el grado del tutor.'));

        $period = AcademicPeriod::find($validated['trimester_id']);
        abort_if(! $period || $period->is_supletorio, 404, __('Trimestre no encontrado.'));

        $subjectIds = ClassSchedule::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->pluck('subject_id');

        $subjectsById = Subject::whereIn('id', $subjectIds->all())->get()->keyBy('id');

        $careerIndicators = CareerGuidanceIndicator::where(fn ($q) => $q->where('grade_id', $gradeId)->orWhereNull('grade_id'))->orderBy('order')->get();
        $classroomIndicators = IntegralClassroomSupportIndicator::orderBy('order')->get();
        $readingIndicators = ReadingPromotionIndicator::orderBy('order')->get();

        $numericSubjectIds = [];
        $qualSubjectEntries = [];

        foreach ($subjectIds as $subjectId) {
            $subject = $subjectsById->get($subjectId);
            if (! $subject) {
                continue;
            }

            $nameLower = strtolower($subject->subject_name);
            $qualType = null;

            if (str_contains($nameLower, 'orientacion vocacional') || str_contains($nameLower, 'ovp')) {
                $qualType = 'career_guidance';
            } elseif (str_contains($nameLower, 'acompañamiento integral') || str_contains($nameLower, 'aiac') || str_contains($nameLower, 'civica')) {
                $qualType = 'classroom_support';
            } elseif (str_contains($nameLower, 'animacion a la lectura') || str_contains($nameLower, 'animación a la lectura')) {
                $qualType = 'reading_promotion';
            }

            if ($qualType) {
                $qualSubjectEntries[] = ['type' => $qualType, 'subjectId' => $subjectId, 'subjectName' => $subject->subject_name];
            } else {
                $numericSubjectIds[] = $subjectId;
            }
        }

        $studentId = $validated['student_id'];
        $loaded = $numericSubjectIds !== []
            ? $this->reportComputer->loadClassData($yearId, $gradeId, $numericSubjectIds, [$period->id], [$studentId])
            : null;

        $subjectsData = [];

        foreach ($qualSubjectEntries as $entry) {
            $subjectId = $entry['subjectId'];
            $qualType = $entry['type'];

            $data = [
                'name' => $entry['subjectName'],
                'blocks' => collect(),
                'qualType' => $qualType,
            ];

            if ($qualType === 'career_guidance') {
                $data['indicators'] = $careerIndicators;
                $data['grades'] = CareerGuidance::where('subject_id', $subjectId)
                    ->where('grade_id', $gradeId)
                    ->where('trimester_id', $period->id)
                    ->where('year_id', $yearId)
                    ->where('student_id', $studentId)
                    ->get()
                    ->keyBy('indicator_id');
            } elseif ($qualType === 'classroom_support') {
                $data['indicators'] = $classroomIndicators;
                $data['grades'] = IntegralClassroomSupport::where('subject_id', $subjectId)
                    ->where('grade_id', $gradeId)
                    ->where('trimester_id', $period->id)
                    ->where('year_id', $yearId)
                    ->where('student_id', $studentId)
                    ->get()
                    ->keyBy('skill_id');
            } elseif ($qualType === 'reading_promotion') {
                $data['indicators'] = $readingIndicators;
                $data['grades'] = ReadingPromotion::where('subject_id', $subjectId)
                    ->where('grade_id', $gradeId)
                    ->where('trimester_id', $period->id)
                    ->where('year_id', $yearId)
                    ->where('student_id', $studentId)
                    ->get()
                    ->keyBy('indicator_id');
            }

            $subjectsData[] = $data;
        }

        foreach ($numericSubjectIds as $subjectId) {
            $subject = $subjectsById->get($subjectId);
            if (! $subject) {
                continue;
            }

            $cell = $subjectId.'|'.$period->id;
            $subjectsData[] = [
                'name' => $subject->subject_name,
                'blocks' => $loaded?->blocks->get($cell) ?? collect(),
                'qualType' => null,
            ];
        }

        $school = app(SchoolConfigService::class)->getActiveSchool();
        $year = ScolarYear::find($yearId);
        $gradingScheme = GradingScheme::where('year_id', $yearId)->where('status', 1)->first();
        $inspector = User::whereHas('roles', fn ($q) => $q->where('name', 'INSPECTOR'))->first();

        $gradeData = $tutorSchedule->grade;
        $gradeName = ($gradeData->grade_name ?? '').' '.($gradeData->section ?? '');

        $pdf = Pdf::loadView('pdf.tutor-student-formative-trimester', [
            'school' => $school,
            'student' => $student,
            'subjectsData' => $subjectsData,
            'trimesterName' => $period->trimester_name,
            'gradeName' => $gradeName,
            'shiftName' => $gradeData->nivel?->shift?->shift_name ?? '',
            'yearName' => $year->year_name ?? '',
            'teacherName' => auth()->user()?->fullname ?? '',
            'inspectorName' => $inspector?->fullname ?? '',
            'gradingScheme' => $gradingScheme,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'landscape');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '2.5mm');
        $pdf->setOption('marginBottom', '2.5mm');
        $pdf->setOption('marginLeft', '3mm');
        $pdf->setOption('marginRight', '3mm');

        $studentName = trim(($student->user->lastname ?? '').' '.($student->user->name ?? ''));
        $filename = 'Formativas_'.str_replace(['/', '\\', ':'], '-', $studentName).'_'.str_replace(['/', '\\', ':'], '-', $period->trimester_name).'.pdf';

        return $pdf->download($filename);
    }

    public function qualitativeReport(Request $request)
    {
        $data = $this->buildContext($request);

        $subjectName = $data['subjectName'];
        $nameLower = strtolower(Str::ascii($subjectName));

        if (str_contains($nameLower, 'orientacion vocacional') || str_contains($nameLower, 'ovp')) {
            $qualType = 'career_guidance';
            $eje = $this->getEjeForGrade($data['grade_id']);
            $indicators = CareerGuidanceIndicator::where(fn ($q) => $q->where('grade_id', $data['grade_id'])->orWhereNull('grade_id'))
                ->when($eje, fn ($q) => $q->where('eje', $eje))
                ->orderBy('order')->get()->toArray();
            $grades = CareerGuidance::where('subject_id', $data['subject_id'])
                ->where('grade_id', $data['grade_id'])
                ->where('trimester_id', $data['trimester_id'])
                ->where('year_id', $data['year_id'])
                ->get()
                ->pluck('value', fn ($g) => $g->student_id.'_'.$g->indicator_id)
                ->toArray();
        } elseif (str_contains($nameLower, 'acompanamiento integral') || str_contains($nameLower, 'aiac') || str_contains($nameLower, 'civica')) {
            $qualType = 'classroom_support';
            $indicators = IntegralClassroomSupportIndicator::orderBy('order')->get()->toArray();
            $grades = IntegralClassroomSupport::where('subject_id', $data['subject_id'])
                ->where('grade_id', $data['grade_id'])
                ->where('trimester_id', $data['trimester_id'])
                ->where('year_id', $data['year_id'])
                ->get()
                ->pluck('value', fn ($g) => $g->student_id.'_'.$g->skill_id)
                ->toArray();
        } elseif (str_contains($nameLower, 'animacion a la lectura')) {
            $qualType = 'reading_promotion';
            $indicators = ReadingPromotionIndicator::orderBy('order')->get()->toArray();
            $grades = ReadingPromotion::where('subject_id', $data['subject_id'])
                ->where('grade_id', $data['grade_id'])
                ->where('trimester_id', $data['trimester_id'])
                ->where('year_id', $data['year_id'])
                ->get()
                ->pluck('value', fn ($g) => $g->student_id.'_'.$g->indicator_id)
                ->toArray();
        } else {
            abort(404, __('Esta asignatura no es cualitativa.'));
        }

        $pdf = Pdf::loadView('pdf.qualitative-report', $data + [
            'indicators' => $indicators,
            'grades' => $grades,
            'qualType' => $qualType,
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '2.5mm');
        $pdf->setOption('marginBottom', '2.5mm');
        $pdf->setOption('marginLeft', '3mm');
        $pdf->setOption('marginRight', '3mm');

        $filename = "Reporte_Cualitativo_{$data['subjectName']}_{$data['gradeName']}_{$data['trimesterName']}.pdf";

        return $pdf->download($filename);
    }

    private function getEjeForGrade(?int $gradeId): ?string
    {
        if (! $gradeId) {
            return null;
        }

        $grade = Grade::find($gradeId);
        if (! $grade) {
            return null;
        }

        $name = strtolower($grade->grade_name);

        if (str_contains($name, '8')) {
            return 'Autoconocimiento';
        }
        if (str_contains($name, '9')) {
            return 'Informacion';
        }
        if (str_contains($name, '10')) {
            return 'Toma de decisiones';
        }

        return null;
    }

    public function subjectAnnualReport(Request $request)
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'grade_id' => ['required', 'integer'],
        ]);

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $schedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('subject_id', $validated['subject_id'])
            ->where('grade_id', $validated['grade_id'])
            ->where('year_id', $yearId)
            ->with('grade.nivel.shift', 'subject')
            ->first();

        abort_if(! $schedule, 404, __('No se encontró el horario.'));

        $students = Student::whereHas('enrollments', fn ($q) => $q->where('grade_id', $validated['grade_id'])->where('year_id', $yearId))
            ->with('user')
            ->orderBy(User::select('lastname')->whereColumn('users.id', 'students.user_id'))
            ->get();

        $subjectId = $validated['subject_id'];
        $gradeId = $validated['grade_id'];

        $trimesters = AcademicPeriod::where('year_id', $yearId)
            ->where('status', 1)
            ->where('is_supletorio', false)
            ->orderBy('id')
            ->get();

        $trimesterInfo = $trimesters->map(fn ($t) => ['id' => $t->id, 'name' => $t->trimester_name])->toArray();

        $gradingScheme = GradingScheme::where('year_id', $yearId)->where('status', 1)->first();

        $studentIds = $students->pluck('id')->all();

        $totalsByStudent = [];
        foreach ($trimesters as $period) {
            $aggregates = $this->reportComputer->classTrimesterAggregates(
                $yearId,
                $subjectId,
                $gradeId,
                $teacherId,
                (int) $period->id,
                $studentIds,
                $gradingScheme,
            );

            foreach ($studentIds as $studentId) {
                $totalsByStudent[$studentId][] = $aggregates[$studentId]['total'] ?? null;
            }
        }

        $studentsData = $students->map(function ($student) use ($totalsByStudent): array {
            $studentName = trim(($student->user->lastname ?? '').' '.($student->user->name ?? ''));

            return [
                'name' => $studentName,
                'trimesters' => $totalsByStudent[$student->id] ?? [],
            ];
        })->values()->all();

        $school = app(SchoolConfigService::class)->getActiveSchool();
        $year = ScolarYear::find($yearId);
        $gradeData = $schedule->grade;
        $gradeName = ($gradeData->grade_name ?? '').' '.($gradeData->section ?? '');

        $pdf = Pdf::loadView('pdf.subject-annual-report', [
            'school' => $school,
            'subjectName' => $schedule->subject->subject_name ?? '',
            'gradeName' => $gradeName,
            'shiftName' => $gradeData->nivel?->shift?->shift_name ?? '',
            'yearName' => $year->year_name ?? '',
            'teacherName' => auth()->user()?->fullname ?? '',
            'gradingScheme' => $gradingScheme,
            'trimesters' => $trimesterInfo,
            'studentsData' => $studentsData,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '2.5mm');
        $pdf->setOption('marginBottom', '2.5mm');
        $pdf->setOption('marginLeft', '3mm');
        $pdf->setOption('marginRight', '3mm');

        $subjectName = $schedule->subject->subject_name ?? '';
        $filename = 'Informe_Anual_'.str_replace(['/', '\\', ':'], '-', $subjectName).'_'.str_replace(['/', '\\', ':'], '-', $gradeName).'.pdf';

        return $pdf->download($filename);
    }

    public function supletorioReport(Request $request)
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'grade_id' => ['required', 'integer'],
        ]);

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $schedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('subject_id', $validated['subject_id'])
            ->where('grade_id', $validated['grade_id'])
            ->where('year_id', $yearId)
            ->with('grade.nivel.shift', 'subject')
            ->first();

        abort_if(! $schedule, 404, __('No se encontró el horario.'));

        $students = Student::whereHas('enrollments', fn ($q) => $q->where('grade_id', $validated['grade_id'])->where('year_id', $yearId))
            ->with('user')
            ->orderBy(User::select('lastname')->whereColumn('users.id', 'students.user_id'))
            ->get();

        $subjectId = $validated['subject_id'];
        $gradeId = $validated['grade_id'];

        $trimesters = AcademicPeriod::where('year_id', $yearId)
            ->where('status', 1)
            ->where('is_supletorio', false)
            ->orderBy('id')
            ->get();

        $gradingScheme = GradingScheme::where('year_id', $yearId)->where('status', 1)->first();

        $studentIds = $students->pluck('id')->all();

        $totalsByStudent = [];
        foreach ($trimesters as $period) {
            $aggregates = $this->reportComputer->classTrimesterAggregates(
                $yearId,
                $subjectId,
                $gradeId,
                $teacherId,
                (int) $period->id,
                $studentIds,
                $gradingScheme,
            );

            foreach ($studentIds as $studentId) {
                $totalsByStudent[$studentId][] = $aggregates[$studentId]['total'] ?? null;
            }
        }

        $supExams = $studentIds === []
            ? collect()
            : SupplementaryExam::query()
                ->where('subject_id', $subjectId)
                ->where('grade_id', $gradeId)
                ->where('year_id', $yearId)
                ->whereIn('student_id', $studentIds)
                ->get()
                ->keyBy('student_id');

        $studentsData = $students->map(function ($student) use ($totalsByStudent, $supExams): array {
            $studentName = trim(($student->user->lastname ?? '').' '.($student->user->name ?? ''));

            return [
                'name' => $studentName,
                'trimesters' => $totalsByStudent[$student->id] ?? [],
                'supletorio' => $supExams->get($student->id)?->grade,
            ];
        })->values()->all();

        $school = app(SchoolConfigService::class)->getActiveSchool();
        $year = ScolarYear::find($yearId);
        $gradeData = $schedule->grade;
        $gradeName = ($gradeData->grade_name ?? '').' '.($gradeData->section ?? '');

        $pdf = Pdf::loadView('pdf.supletorio-report', [
            'school' => $school,
            'subjectName' => $schedule->subject->subject_name ?? '',
            'gradeName' => $gradeName,
            'shiftName' => $gradeData->nivel?->shift?->shift_name ?? '',
            'yearName' => $year->year_name ?? '',
            'teacherName' => auth()->user()?->fullname ?? '',
            'gradingScheme' => $gradingScheme,
            'studentsData' => $studentsData,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '2.5mm');
        $pdf->setOption('marginBottom', '2.5mm');
        $pdf->setOption('marginLeft', '3mm');
        $pdf->setOption('marginRight', '3mm');

        $subjectName = $schedule->subject->subject_name ?? '';
        $filename = 'Supletorio_'.str_replace(['/', '\\', ':'], '-', $subjectName).'_'.str_replace(['/', '\\', ':'], '-', $gradeName).'.pdf';

        return $pdf->download($filename);
    }

    protected function buildContext(Request $request): array
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
            'grade_id' => ['required', 'integer'],
            'trimester_id' => ['required', 'integer'],
            'student_id' => ['nullable', 'integer'],
        ]);

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $schedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('subject_id', $validated['subject_id'])
            ->where('grade_id', $validated['grade_id'])
            ->where('year_id', $yearId)
            ->with('grade.nivel.shift', 'subject')
            ->first();

        abort_if(! $schedule, 404, __('No se encontró el horario.'));

        $period = AcademicPeriod::find($validated['trimester_id']);
        abort_if(! $period || $period->is_supletorio, 404, __('No se encontró el trimestre.'));

        $studentQuery = Student::whereHas('enrollments', fn ($q) => $q->where('grade_id', $validated['grade_id'])->where('year_id', $yearId))
            ->with('user')
            ->orderBy(User::select('lastname')->whereColumn('users.id', 'students.user_id'))
            ->orderBy(User::select('name')->whereColumn('users.id', 'students.user_id'));

        if (! empty($validated['student_id'])) {
            $studentQuery->where('id', $validated['student_id']);
        }

        $students = $studentQuery->get();

        $school = app(SchoolConfigService::class)->getActiveSchool();
        $year = ScolarYear::find($yearId);

        $gradingScheme = GradingScheme::where('year_id', $yearId)
            ->where('status', 1)
            ->first();

        $inspector = User::whereHas('roles', fn ($q) => $q->where('name', 'INSPECTOR'))->first();

        $gradeData = $schedule->grade;
        $gradeName = ($gradeData->grade_name ?? '').' '.($gradeData->section ?? '');

        return [
            'school' => $school,
            'year_id' => $yearId,
            'subject_id' => $validated['subject_id'],
            'grade_id' => $validated['grade_id'],
            'trimester_id' => $validated['trimester_id'],
            'teacher_id' => $teacherId,
            'subjectName' => $schedule->subject->subject_name ?? '',
            'gradeName' => $gradeName,
            'shiftName' => $gradeData->nivel?->shift?->shift_name ?? '',
            'trimesterName' => $period->trimester_name ?? '',
            'yearName' => $year->year_name ?? '',
            'teacherName' => auth()->user()?->fullname ?? '',
            'inspectorName' => $inspector?->fullname ?? '',
            'gradingScheme' => $gradingScheme,
            'students' => $students,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ];
    }

    public function tutorAllStudentsTrimesterReport(Request $request)
    {
        $validated = $request->validate([
            'trimester_id' => ['required', 'integer'],
        ]);

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        abort_if(! $tutorSchedule, 404, __('No se encontró asignación de tutoría.'));

        $gradeId = $tutorSchedule->grade_id;
        $selectedPeriod = AcademicPeriod::find($validated['trimester_id']);
        abort_if(! $selectedPeriod || $selectedPeriod->is_supletorio, 404, __('Trimestre no encontrado.'));

        $allPeriods = AcademicPeriod::where('year_id', $yearId)
            ->where('status', 1)
            ->where('is_supletorio', false)
            ->orderBy('id')
            ->get();

        $periodsToShow = $allPeriods->filter(fn ($p) => $p->id <= $selectedPeriod->id)->values();

        $studentIds = StudentEnrollment::where('year_id', $yearId)
            ->where('grade_id', $gradeId)
            ->where('status', 'active')
            ->pluck('student_id');

        $students = Student::whereIn('id', $studentIds)
            ->with('user')
            ->orderByRaw("COALESCE(NULLIF((SELECT u2.lastname FROM users u2 WHERE u2.id = students.user_id), ''), 'zzz')")
            ->get();

        $subjectIds = ClassSchedule::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->pluck('subject_id');

        $subjectsById = Subject::whereIn('id', $subjectIds->all())->get()->keyBy('id');

        $subjects = [];
        $qualSubjectIds = [];
        $numericSubjectIds = [];
        foreach ($subjectIds as $subjectId) {
            $subject = $subjectsById->get($subjectId);
            if (! $subject) {
                continue;
            }
            $nameLower = strtolower($subject->subject_name);
            $isQual = str_contains($nameLower, 'orientacion vocacional') || str_contains($nameLower, 'ovp')
                || str_contains($nameLower, 'acompañamiento integral') || str_contains($nameLower, 'aiac') || str_contains($nameLower, 'civica')
                || str_contains($nameLower, 'animacion a la lectura') || str_contains($nameLower, 'animación a la lectura');
            if ($isQual) {
                $qualSubjectIds[] = $subjectId;
                $subjects[] = ['id' => $subjectId, 'name' => $subject->subject_name, 'isQual' => true, 'qualType' => $nameLower];
            } else {
                $numericSubjectIds[] = $subjectId;
                $subjects[] = ['id' => $subjectId, 'name' => $subject->subject_name, 'isQual' => false];
            }
        }

        $sfoValues = ['S' => 4, 'F' => 3, 'O' => 2, 'N' => 1];
        $sfoLetters = ['S', 'F', 'O', 'N'];
        $readingScores = [1 => 'E-', 2 => 'E+', 3 => 'D-', 4 => 'D+', 5 => 'C-', 6 => 'C+', 7 => 'B-', 8 => 'B+', 9 => 'A-', 10 => 'A+'];
        $qualLetterMap = [
            'A+' => [35, 36], 'A-' => [33, 34], 'B+' => [30, 32], 'B-' => [27, 29],
            'C+' => [20, 26], 'C-' => [18, 19], 'D+' => [15, 17], 'D-' => [13, 14],
            'E+' => [11, 12], 'E-' => [0, 10],
        ];

        $gradingScheme = GradingScheme::where('year_id', $yearId)->where('status', 1)->first();

        $periodIds = $periodsToShow->pluck('id')->all();
        $studentIdArr = $studentIds->all();

        $allReadingGrades = ReadingPromotion::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->whereIn('subject_id', $qualSubjectIds)
            ->whereIn('student_id', $studentIdArr)
            ->whereIn('trimester_id', $periodIds)
            ->get()
            ->groupBy(fn ($g) => $g->student_id.'|'.$g->subject_id.'|'.$g->trimester_id);

        $allCareerGuidance = CareerGuidance::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->whereIn('subject_id', $qualSubjectIds)
            ->whereIn('student_id', $studentIdArr)
            ->whereIn('trimester_id', $periodIds)
            ->get()
            ->groupBy(fn ($g) => $g->student_id.'|'.$g->subject_id.'|'.$g->trimester_id);

        $allClassroomSupport = IntegralClassroomSupport::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->whereIn('subject_id', $qualSubjectIds)
            ->whereIn('student_id', $studentIdArr)
            ->whereIn('trimester_id', $periodIds)
            ->get()
            ->groupBy(fn ($g) => $g->student_id.'|'.$g->subject_id.'|'.$g->trimester_id);

        $loaded = $numericSubjectIds !== []
            ? $this->reportComputer->loadClassData($yearId, $gradeId, $numericSubjectIds, $periodIds, $studentIdArr)
            : null;

        $formativeByStudent = [];
        if ($loaded) {
            foreach ($studentIdArr as $sId) {
                $formativeByStudent[$sId] = $this->reportComputer->formativeByStudent(
                    collect(),
                    [$sId],
                );
            }
        }

        $studentsData = [];
        foreach ($students as $student) {
            $sId = $student->id;
            $subjectResults = [];

            foreach ($subjects as $subj) {
                $trimesterTotals = [];

                foreach ($periodsToShow as $period) {
                    if ($subj['isQual']) {
                        $letter = null;
                        $obs = '';
                        $cellKey = $sId.'|'.$subj['id'].'|'.$period->id;

                        if (str_contains($subj['qualType'], 'animacion') || str_contains($subj['qualType'], 'animación')) {
                            $grades = $allReadingGrades->get($cellKey) ?? collect();
                            if ($grades->count() > 0) {
                                $totalVal = $grades->sum(fn ($g) => (int) $g->value);
                                $numVal = min(10, (int) ceil($totalVal / $grades->count()));
                                $letter = $readingScores[$numVal] ?? '—';
                            }
                        } else {
                            $isCareer = str_contains($subj['qualType'], 'orientacion') || str_contains($subj['qualType'], 'ovp');
                            $grades = ($isCareer ? $allCareerGuidance : $allClassroomSupport)->get($cellKey) ?? collect();
                            if ($grades->count() > 0) {
                                $counts = ['S' => 0, 'F' => 0, 'O' => 0, 'N' => 0];
                                $sumScore = 0;
                                foreach ($grades as $g) {
                                    if ($g->value && isset($counts[$g->value])) {
                                        $counts[$g->value]++;
                                        $sumScore += $sfoValues[$g->value];
                                    }
                                }
                                foreach ($qualLetterMap as $qLetter => $range) {
                                    if ($sumScore >= $range[0] && $sumScore <= $range[1]) {
                                        $letter = $qLetter;
                                        break;
                                    }
                                }
                                $maxCount = max($counts);
                                if ($maxCount > 0) {
                                    foreach ($sfoLetters as $l) {
                                        if ($counts[$l] === $maxCount) {
                                            $obs = $l;
                                            break;
                                        }
                                    }
                                }
                            }
                        }

                        $trimesterTotals[] = ['grade' => $letter, 'obs' => $obs];
                    } else {
                        $cell = $subj['id'].'|'.$period->id;
                        $blocks = $loaded?->blocks->get($cell) ?? collect();

                        $blockAverages = [];
                        foreach ($blocks as $block) {
                            $totalActivities = $block->activities->count();
                            if ($totalActivities === 0) {
                                continue;
                            }
                            $total = 0;
                            foreach ($block->activities as $activity) {
                                $grade = $activity->grades->firstWhere('student_id', $sId);
                                if ($grade && $grade->grade !== null) {
                                    $total += $grade->grade;
                                }
                            }
                            $blockAverages[] = floor($total / $totalActivities * 100) / 100;
                        }
                        $formativeAvg = count($blockAverages) > 0
                            ? floor(array_sum($blockAverages) / count($blockAverages) * 100) / 100
                            : null;

                        $exam = $loaded?->exams->get($cell)?->get($sId);
                        $project = $loaded?->projects->get($cell)?->get($sId);

                        $total = null;
                        if ($gradingScheme && $formativeAvg !== null) {
                            $total = round(
                                ($formativeAvg * $gradingScheme->formative_percentage / 100)
                                + (($exam?->grade ?? 0) * $gradingScheme->exam_percentage / 100)
                                + (($project?->grade ?? 0) * $gradingScheme->project_percentage / 100),
                                2
                            );
                        } elseif ($exam?->grade !== null && $formativeAvg === null) {
                            $total = $exam->grade;
                        }

                        $trimesterTotals[] = $total;
                    }
                }

                $subjectResults[$subj['id']] = [
                    'name' => $subj['name'],
                    'trimesters' => $trimesterTotals,
                    'isQual' => $subj['isQual'] ?? false,
                ];
            }

            $studentsData[] = [
                'name' => $student->user->full_name ?? trim(($student->user->lastname ?? '').' '.($student->user->name ?? '')),
                'student_code' => $student->student_code,
                'subjects' => $subjectResults,
            ];
        }

        $school = app(SchoolConfigService::class)->getActiveSchool();
        $year = ScolarYear::find($yearId);

        $gradeData = $tutorSchedule->grade;
        $gradeName = ($gradeData->grade_name ?? '').' '.($gradeData->section ?? '');

        $pdf = Pdf::loadView('pdf.tutor-all-students-trimester', [
            'school' => $school,
            'subjects' => $subjects,
            'periodsToShow' => $periodsToShow,
            'studentsData' => $studentsData,
            'trimesterName' => $selectedPeriod->trimester_name,
            'gradeName' => $gradeName,
            'shiftName' => $gradeData->nivel?->shift?->shift_name ?? '',
            'yearName' => $year->year_name ?? '',
            'teacherName' => auth()->user()?->fullname ?? '',
            'gradingScheme' => $gradingScheme,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '2.5mm');
        $pdf->setOption('marginBottom', '2.5mm');
        $pdf->setOption('marginLeft', '3mm');
        $pdf->setOption('marginRight', '3mm');

        $filename = 'Notas_Todos_'.str_replace(['/', '\\', ':'], '-', $gradeName).'_'.str_replace(['/', '\\', ':'], '-', $selectedPeriod->trimester_name).'.pdf';

        return $pdf->download($filename);
    }

    public function studentTrimesterReport(Request $request)
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
            'trimester_id' => ['required', 'integer'],
        ]);

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        abort_if(! $tutorSchedule, 404, __('No se encontró asignación de tutoría.'));

        $gradeId = $tutorSchedule->grade_id;

        $student = Student::where('id', $validated['student_id'])
            ->whereHas('enrollments', fn ($q) => $q->where('grade_id', $gradeId)->where('year_id', $yearId))
            ->with('user')
            ->first();

        abort_if(! $student, 404, __('Estudiante no encontrado en el grado del tutor.'));

        $period = AcademicPeriod::find($validated['trimester_id']);
        abort_if(! $period || $period->is_supletorio, 404, __('Trimestre no encontrado.'));

        $studentId = $validated['student_id'];
        $subjectIds = ClassSchedule::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->pluck('subject_id');

        $gradingScheme = GradingScheme::where('year_id', $yearId)->where('status', 1)->first();
        $formativePct = ($gradingScheme->formative_percentage ?? 70) / 100;
        $examPct = ($gradingScheme->exam_percentage ?? 20) / 100;
        $projectPct = ($gradingScheme->project_percentage ?? 10) / 100;
        $sumativePct = $examPct + $projectPct;

        $subjectsData = [];
        $qualSubjectsData = [];

        $subjectsById = Subject::whereIn('id', $subjectIds)->get()->keyBy('id');
        $numericSubjectIds = [];

        foreach ($subjectsById as $subjectId => $subject) {
            $subjectNameLower = strtolower(Str::ascii($subject->subject_name));

            if (str_contains($subjectNameLower, 'orientacion vocacional') || str_contains($subjectNameLower, 'ovp')) {
                $qualSubjectsData[] = $this->buildQualitativeData('career_guidance', $subject, $gradeId, $studentId, $yearId, collect([$period]));

                continue;
            }

            if (str_contains($subjectNameLower, 'acompanamiento integral') || str_contains($subjectNameLower, 'aiac') || str_contains($subjectNameLower, 'civica')) {
                $qualSubjectsData[] = $this->buildQualitativeData('classroom_support', $subject, $gradeId, $studentId, $yearId, collect([$period]));

                continue;
            }

            if (str_contains($subjectNameLower, 'animacion a la lectura')) {
                $qualSubjectsData[] = $this->buildQualitativeData('reading_promotion', $subject, $gradeId, $studentId, $yearId, collect([$period]));

                continue;
            }

            $numericSubjectIds[] = $subjectId;
        }

        $loaded = $this->reportComputer->loadClassData($yearId, $gradeId, $numericSubjectIds ?: [0], [$period->id], [$studentId]);

        foreach ($numericSubjectIds as $subjectId) {
            $subject = $subjectsById[$subjectId];
            $cell = $subjectId.'|'.$period->id;

            $formative = $this->reportComputer->formativeByStudent(
                $loaded->blocks->get($cell) ?? collect(),
                [$studentId],
            )[$studentId];

            $exam = $loaded->exams->get($cell)?->get($studentId);
            $project = $loaded->projects->get($cell)?->get($studentId);

            $formativeWeighted = $formative !== null ? round($formative * $formativePct, 2) : null;

            $sumativeRaw = 0;
            $hasSumative = false;
            if ($exam?->grade !== null) {
                $sumativeRaw += $exam->grade * $examPct;
                $hasSumative = true;
            }
            if ($project?->grade !== null) {
                $sumativeRaw += $project->grade * $projectPct;
                $hasSumative = true;
            }
            $sumativeWeighted = $hasSumative ? round($sumativeRaw, 2) : null;

            $nota = null;
            if ($formativeWeighted !== null && $sumativeWeighted !== null) {
                $nota = round($formativeWeighted + $sumativeWeighted, 2);
            } elseif ($formativeWeighted !== null) {
                $nota = $formativeWeighted;
            }

            $subjectsData[] = [
                'name' => $subject->subject_name,
                'formative' => $formative,
                'formativeWeighted' => $formativeWeighted,
                'sumativeWeighted' => $sumativeWeighted,
                'nota' => $nota,
            ];
        }

        $school = app(SchoolConfigService::class)->getActiveSchool();
        $year = ScolarYear::find($yearId);
        $gradeData = $tutorSchedule->grade;
        $gradeName = ($gradeData->grade_name ?? '').' '.($gradeData->section ?? '');

        $pdf = Pdf::loadView('pdf.student-trimester-report', [
            'school' => $school,
            'student' => $student,
            'subjectsData' => $subjectsData,
            'qualSubjectsData' => $qualSubjectsData,
            'trimesterName' => $period->trimester_name,
            'trimesterId' => $period->id,
            'gradeName' => $gradeName,
            'gradeId' => $gradeId,
            'yearId' => $yearId,
            'shiftName' => $gradeData->nivel?->shift?->shift_name ?? '',
            'yearName' => $year->year_name ?? '',
            'teacherName' => auth()->user()?->fullname ?? '',
            'gradingScheme' => $gradingScheme,
            'formativePct' => $gradingScheme->formative_percentage ?? 70,
            'sumativePct' => round($sumativePct * 100),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '2.5mm');
        $pdf->setOption('marginBottom', '2.5mm');
        $pdf->setOption('marginLeft', '3mm');
        $pdf->setOption('marginRight', '3mm');

        $studentName = trim(($student->user->lastname ?? '').' '.($student->user->name ?? ''));
        $filename = 'Reporte_Trimestre_'.str_replace(['/', '\\', ':'], '-', $studentName).'_'.str_replace(['/', '\\', ':'], '-', $period->trimester_name).'.pdf';

        return $pdf->download($filename);
    }

    public function studentAnnualReport(Request $request)
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer'],
        ]);

        $teacherId = auth()->user()->teacher?->id;
        $yearId = app(AcademicYearService::class)->getActiveYearId();

        $tutorSchedule = ClassSchedule::where('teacher_id', $teacherId)
            ->where('year_id', $yearId)
            ->whereHas('subject', fn ($q) => $q->where('subject_name', 'like', '%Acompañamiento integral en el aula%'))
            ->with('grade.nivel.shift')
            ->first();

        abort_if(! $tutorSchedule, 404, __('No se encontró asignación de tutoría.'));

        $gradeId = $tutorSchedule->grade_id;

        $student = Student::where('id', $validated['student_id'])
            ->whereHas('enrollments', fn ($q) => $q->where('grade_id', $gradeId)->where('year_id', $yearId))
            ->with('user')
            ->first();

        abort_if(! $student, 404, __('Estudiante no encontrado en el grado del tutor.'));

        $studentId = $validated['student_id'];
        $subjectIds = ClassSchedule::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->pluck('subject_id');

        $periods = AcademicPeriod::where('year_id', $yearId)
            ->where('status', 1)
            ->where('is_supletorio', false)
            ->orderBy('id')
            ->get();

        $supletorioPeriod = AcademicPeriod::where('year_id', $yearId)
            ->where('status', 1)
            ->where('is_supletorio', true)
            ->first();

        $gradingScheme = GradingScheme::where('year_id', $yearId)->where('status', 1)->first();

        $subjectsById = Subject::whereIn('id', $subjectIds->all())->get()->keyBy('id');

        $periodIds = $periods->pluck('id')->all();
        $numericSubjectIds = [];
        $qualSubjectEntries = [];

        foreach ($subjectIds as $subjectId) {
            $subject = $subjectsById->get($subjectId);
            if (! $subject) {
                continue;
            }

            $subjectNameLower = strtolower(Str::ascii($subject->subject_name));

            if (str_contains($subjectNameLower, 'orientacion vocacional') || str_contains($subjectNameLower, 'ovp')) {
                $qualSubjectEntries[] = ['type' => 'career_guidance', 'subject' => $subject];
            } elseif (str_contains($subjectNameLower, 'acompañamiento integral') || str_contains($subjectNameLower, 'aiac') || str_contains($subjectNameLower, 'civica')) {
                $qualSubjectEntries[] = ['type' => 'classroom_support', 'subject' => $subject];
            } elseif (str_contains($subjectNameLower, 'animacion a la lectura')) {
                $qualSubjectEntries[] = ['type' => 'reading_promotion', 'subject' => $subject];
            } else {
                $numericSubjectIds[] = $subjectId;
            }
        }

        $loaded = $numericSubjectIds !== []
            ? $this->reportComputer->loadClassData($yearId, $gradeId, $numericSubjectIds, $periodIds, [$studentId])
            : null;

        $allSupExams = $supletorioPeriod
            ? SupplementaryExam::where('year_id', $yearId)
                ->where('grade_id', $gradeId)
                ->where('student_id', $studentId)
                ->whereIn('subject_id', $numericSubjectIds)
                ->get()
                ->keyBy('subject_id')
            : collect();

        $allClassScheduleIds = ClassSchedule::where('grade_id', $gradeId)
            ->where('year_id', $yearId)
            ->whereIn('trimester_id', $periodIds)
            ->pluck('id');

        $allAttendances = Attendance::where('student_id', $studentId)
            ->where('year_id', $yearId)
            ->whereIn('class_schedule_id', $allClassScheduleIds)
            ->get();

        $scheduleIdToPeriod = ClassSchedule::whereIn('id', $allClassScheduleIds)
            ->get()
            ->mapWithKeys(fn ($cs) => [(int) $cs->id => (int) $cs->trimester_id]);

        $subjectsData = [];
        $qualSubjectsData = [];

        foreach ($qualSubjectEntries as $entry) {
            $qualSubjectsData[] = $this->buildQualitativeData($entry['type'], $entry['subject'], $gradeId, $studentId, $yearId, $periods);
        }

        foreach ($numericSubjectIds as $subjectId) {
            $subject = $subjectsById->get($subjectId);
            if (! $subject) {
                continue;
            }

            $periodGrades = [];
            foreach ($periods as $period) {
                $cell = $subjectId.'|'.$period->id;
                $blocks = $loaded?->blocks->get($cell) ?? collect();

                $blockAverages = [];
                foreach ($blocks as $block) {
                    $totalActivities = $block->activities->count();
                    if ($totalActivities === 0) {
                        continue;
                    }
                    $total = 0;
                    foreach ($block->activities as $activity) {
                        $grade = $activity->grades->first();
                        if ($grade && $grade->grade !== null) {
                            $total += $grade->grade;
                        }
                    }
                    $blockAverages[] = floor($total / $totalActivities * 100) / 100;
                }
                $formativeAvg = count($blockAverages) > 0
                    ? floor(array_sum($blockAverages) / count($blockAverages) * 100) / 100
                    : null;

                $exam = $loaded?->exams->get($cell)?->get($studentId);
                $project = $loaded?->projects->get($cell)?->get($studentId);

                $fw = $formativeAvg !== null ? $formativeAvg * (($gradingScheme->formative_percentage ?? 70) / 100) : 0;
                $ew = $exam?->grade !== null ? $exam->grade * (($gradingScheme->exam_percentage ?? 20) / 100) : 0;
                $pw = $project?->grade !== null ? $project->grade * (($gradingScheme->project_percentage ?? 10) / 100) : 0;
                $total = ($fw + $ew + $pw) > 0 ? round($fw + $ew + $pw, 2) : null;

                $periodGrades[] = [
                    'formative' => $formativeAvg,
                    'exam' => $exam?->grade,
                    'project' => $project?->grade,
                    'total' => $total,
                ];
            }

            $supTotal = $allSupExams->get($subjectId)?->grade;

            $annualTotal = null;
            $validTotals = array_column(array_filter($periodGrades, fn ($pg) => $pg['total'] !== null), 'total');
            if (count($validTotals) > 0) {
                $annualTotal = round(array_sum($validTotals) / count($validTotals), 2);
            }

            $subjectsData[] = [
                'name' => $subject->subject_name,
                'periods' => $periodGrades,
                'supletorio' => $supTotal,
                'annual' => $annualTotal,
            ];
        }

        $attendanceSummary = [
            'justified' => 0,
            'unjustified' => 0,
            'total' => 0,
        ];

        foreach ($allAttendances as $att) {
            $periodId = $scheduleIdToPeriod->get((int) $att->class_schedule_id);
            if ($periodId === null) {
                continue;
            }
            $status = $att->status;
            if ($status === 'J') {
                $attendanceSummary['justified']++;
            } elseif ($status === 'I' || $status === 'AI' || $status === 'AA') {
                $attendanceSummary['unjustified']++;
            }
            $attendanceSummary['total']++;
        }

        $school = app(SchoolConfigService::class)->getActiveSchool();
        $year = ScolarYear::find($yearId);
        $gradeData = $tutorSchedule->grade;
        $gradeName = ($gradeData->grade_name ?? '').' '.($gradeData->section ?? '');

        $pdf = Pdf::loadView('pdf.student-annual-report', [
            'school' => $school,
            'student' => $student,
            'subjectsData' => $subjectsData,
            'qualSubjectsData' => $qualSubjectsData,
            'periods' => $periods,
            'attendanceSummary' => $attendanceSummary,
            'gradeName' => $gradeName,
            'gradeId' => $gradeId,
            'yearId' => $yearId,
            'shiftName' => $gradeData->nivel?->shift?->shift_name ?? '',
            'yearName' => $year->year_name ?? '',
            'teacherName' => auth()->user()?->fullname ?? '',
            'gradingScheme' => $gradingScheme,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('defaultFont', 'sans-serif');
        $pdf->setOption('marginTop', '2.5mm');
        $pdf->setOption('marginBottom', '2.5mm');
        $pdf->setOption('marginLeft', '3mm');
        $pdf->setOption('marginRight', '3mm');

        $studentName = trim(($student->user->lastname ?? '').' '.($student->user->name ?? ''));
        $filename = 'Reporte_Anual_'.$studentName.'.pdf';

        return $pdf->download($filename);
    }

    private function buildQualitativeData(string $type, Subject $subject, int $gradeId, int $studentId, int $yearId, $periods): array
    {
        $indicators = match ($type) {
            'career_guidance' => CareerGuidanceIndicator::where(fn ($q) => $q->where('grade_id', $gradeId)->orWhereNull('grade_id'))->orderBy('order')->get(),
            'classroom_support' => IntegralClassroomSupportIndicator::orderBy('order')->get(),
            default => ReadingPromotionIndicator::orderBy('order')->get(),
        };

        $sfoMap = ['S' => 4, 'F' => 3, 'O' => 2, 'N' => 1];
        $qualLetterMap = [
            ['min' => 35, 'max' => 36, 'letter' => 'A+'],
            ['min' => 33, 'max' => 34, 'letter' => 'A-'],
            ['min' => 30, 'max' => 32, 'letter' => 'B+'],
            ['min' => 27, 'max' => 29, 'letter' => 'B-'],
            ['min' => 20, 'max' => 26, 'letter' => 'C+'],
            ['min' => 18, 'max' => 19, 'letter' => 'C-'],
            ['min' => 15, 'max' => 17, 'letter' => 'D+'],
            ['min' => 13, 'max' => 14, 'letter' => 'D-'],
            ['min' => 11, 'max' => 12, 'letter' => 'E+'],
            ['min' => 0, 'max' => 10, 'letter' => 'E-'],
        ];
        $readingScores = [1 => 'E-', 2 => 'E+', 3 => 'D-', 4 => 'D+', 5 => 'C-', 6 => 'C+', 7 => 'B-', 8 => 'B+', 9 => 'A-', 10 => 'A+'];

        $periodGrades = [];

        foreach ($periods as $period) {
            if ($type === 'career_guidance') {
                $grades = CareerGuidance::where('subject_id', $subject->id)
                    ->where('grade_id', $gradeId)
                    ->where('trimester_id', $period->id)
                    ->where('year_id', $yearId)
                    ->where('student_id', $studentId)
                    ->get()
                    ->keyBy('indicator_id');
            } elseif ($type === 'classroom_support') {
                $grades = IntegralClassroomSupport::where('subject_id', $subject->id)
                    ->where('grade_id', $gradeId)
                    ->where('trimester_id', $period->id)
                    ->where('year_id', $yearId)
                    ->where('student_id', $studentId)
                    ->get()
                    ->keyBy('skill_id');
            } else {
                $grades = ReadingPromotion::where('subject_id', $subject->id)
                    ->where('grade_id', $gradeId)
                    ->where('trimester_id', $period->id)
                    ->where('year_id', $yearId)
                    ->where('student_id', $studentId)
                    ->get()
                    ->keyBy('indicator_id');
            }

            $totalScore = 0;
            $count = 0;
            $freqs = ['S' => 0, 'F' => 0, 'O' => 0, 'N' => 0];

            foreach ($indicators as $indicator) {
                $key = $type === 'classroom_support' ? $indicator->id : $indicator->id;
                $gradeVal = $grades->get($key)?->value;

                if ($type === 'reading_promotion') {
                    if ($gradeVal !== null) {
                        $totalScore += (int) $gradeVal;
                        $count++;
                    }
                } else {
                    if ($gradeVal && isset($sfoMap[$gradeVal])) {
                        $totalScore += $sfoMap[$gradeVal];
                        $freqs[$gradeVal]++;
                        $count++;
                    }
                }
            }

            $letter = '—';
            if ($type === 'reading_promotion') {
                if ($count > 0) {
                    $avg = min(10, (int) ceil($totalScore / $count));
                    $letter = $readingScores[$avg] ?? '—';
                }
            } else {
                foreach ($qualLetterMap as $range) {
                    if ($totalScore >= $range['min'] && $totalScore <= $range['max']) {
                        $letter = $range['letter'];
                        break;
                    }
                }
                arsort($freqs);
                $obsKey = array_key_first(array_filter($freqs, fn ($v) => $v > 0));
            }

            $periodGrades[] = [
                'letter' => $letter,
                'obs' => $obsKey ?? null,
                'score' => $totalScore,
            ];
        }

        return [
            'name' => $subject->subject_name,
            'type' => $type,
            'periods' => $periodGrades,
        ];
    }
}
