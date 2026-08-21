<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Setting;

use App\Http\Resources\Api\V1\Auth\UserResource;
use App\Http\Resources\Api\V1\Setting\AreaResource;
use App\Http\Resources\Api\V1\Setting\ClassroomResource;
use App\Http\Resources\Api\V1\Setting\ClassScheduleResource;
use App\Http\Resources\Api\V1\Setting\GradeResource;
use App\Http\Resources\Api\V1\Setting\NivelResource;
use App\Http\Resources\Api\V1\Setting\SchoolResource;
use App\Http\Resources\Api\V1\Setting\ScolarYearResource;
use App\Http\Resources\Api\V1\Setting\ShiftResource;
use App\Http\Resources\Api\V1\Setting\SubjectResource;
use App\Http\Resources\Api\V1\Setting\TeacherResource;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Classroom;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use App\Services\AcademicYearService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Construye el paquete de configuración que un cliente offline (móvil o
 * escritorio) descarga para poder trabajar sin conexión en los módulos de
 * asistencia, horario docente y libro de calificaciones.
 */
final class OfflinePackageService
{
    public function __construct(private readonly AcademicYearService $academicYearService) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user): array
    {
        $teacher = $user->teacher instanceof Teacher ? $user->teacher : null;
        $year = $this->academicYearService->getActiveYear();

        return [
            'generated_at' => now()->toISOString(),
            'school' => $this->school(),
            'school_year' => $this->schoolYear($year),
            'catalogs' => $this->catalogs(),
            'teacher' => $this->teacher($teacher),
            'schedules' => $this->schedules($teacher, $year),
            'students' => $this->students($teacher, $year),
        ];
    }

    private function school(): ?SchoolResource
    {
        $school = School::where('status', 1)->first();

        return $school ? new SchoolResource($school) : null;
    }

    private function schoolYear(?ScolarYear $year): ?ScolarYearResource
    {
        if ($year === null) {
            return null;
        }

        $year->loadMissing([
            'academicPeriods',
            'gradingSchemes',
            'calendarDays' => fn ($query) => $query->orderBy('date'),
        ]);

        return new ScolarYearResource($year);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogs(): array
    {
        return [
            'shifts' => ShiftResource::collection(Shift::where('status', 1)->orderBy('shift_name')->get()),
            'nivels' => NivelResource::collection(Nivel::where('status', 1)->orderBy('nivel_name')->get()),
            'grades' => GradeResource::collection(Grade::where('status', 1)->orderBy('grade_name')->get()),
            'areas' => AreaResource::collection(Area::orderBy('area_name')->get()),
            'subjects' => SubjectResource::collection(Subject::orderBy('subject_name')->get()),
            'classrooms' => ClassroomResource::collection(Classroom::where('status', 1)->orderBy('classroom_name')->get()),
        ];
    }

    private function teacher(?Teacher $teacher): ?TeacherResource
    {
        if ($teacher === null) {
            return null;
        }

        return new TeacherResource($teacher->loadMissing('user'));
    }

    private function schedules(?Teacher $teacher, ?ScolarYear $year): AnonymousResourceCollection
    {
        if ($teacher === null || $year === null) {
            return ClassScheduleResource::collection([]);
        }

        return ClassScheduleResource::collection(
            ClassSchedule::with(['subject.area', 'grade.nivel.shift', 'trimester', 'calendarDay'])
                ->where('year_id', $year->id)
                ->where('teacher_id', $teacher->id)
                ->where('is_active', true)
                ->orderBy('day')
                ->orderBy('start_time')
                ->get(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function students(?Teacher $teacher, ?ScolarYear $year): array
    {
        if ($teacher === null || $year === null) {
            return [];
        }

        $gradeIds = ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->where('year_id', $year->id)
            ->where('is_active', true)
            ->pluck('grade_id')
            ->unique()
            ->values();

        if ($gradeIds->isEmpty()) {
            return [];
        }

        $studentIds = StudentEnrollment::query()
            ->where('year_id', $year->id)
            ->whereIn('grade_id', $gradeIds)
            ->pluck('student_id')
            ->unique()
            ->values();

        if ($studentIds->isEmpty()) {
            return [];
        }

        $students = Student::with('user')
            ->whereIn('id', $studentIds)
            ->orderBy('student_code')
            ->get();

        $enrollments = StudentEnrollment::query()
            ->where('year_id', $year->id)
            ->whereIn('grade_id', $gradeIds)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        return $students->map(function (Student $student) use ($enrollments): array {
            $enrollment = $enrollments->get($student->id);

            return [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'user' => $student->user ? new UserResource($student->user) : null,
                'grade_id' => $enrollment?->grade_id,
                'enrollment_status' => $enrollment?->status,
            ];
        })->values()->all();
    }
}
