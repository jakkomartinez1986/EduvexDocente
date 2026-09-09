<?php

namespace Database\Seeders;

use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Security\Authorizations\Role;
use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\CalendarDay;
use App\Models\Setting\YearSettings\GradingScheme;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el volumen de datos para las pruebas de carga reales:
 * 1 colegio, 250 docentes, ~3.500 estudiantes, ~1.000 horarios,
 * ~3.000 actividades, calificaciones y asistencias de alto volumen.
 *
 * No es interactivo (a diferencia de DatabaseSeeder) y crea sus propios
 * roles si no existen. Usar:
 *
 *     php artisan db:seed --class=LoadTestDataSeeder
 *
 * Los volúmenes se ajustan con variables de entorno (LOAD_TEACHERS,
 * LOAD_STUDENTS, LOAD_SCHEDULES_PER_TEACHER, LOAD_ACTIVITIES_PER_SCHEDULE,
 * LOAD_GRADE_EVERY, LOAD_ATTENDANCE_SCHEDULES).
 */
class LoadTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $teachersCount = (int) env('LOAD_TEACHERS', 250);
        $studentsCount = (int) env('LOAD_STUDENTS', 3500);
        $schedulesPerTeacher = (int) env('LOAD_SCHEDULES_PER_TEACHER', 4);
        $activitiesPerSchedule = (int) env('LOAD_ACTIVITIES_PER_SCHEDULE', 3);
        $gradeEvery = max(1, (int) env('LOAD_GRADE_EVERY', 10));
        $attendanceSchedules = (int) env('LOAD_ATTENDANCE_SCHEDULES', 100);

        if (Teacher::query()->exists() || Student::query()->exists()) {
            if ((int) env('LOAD_FORCE', 0) !== 1) {
                $this->command?->error('El seed de carga requiere una base vacía. Reejecutar con LOAD_FORCE=1 para limpiar la carga anterior.');

                return;
            }

            $this->resetLoadData();
        }

        $school = School::factory()->create();
        $year = ScolarYear::factory()->active()->create([
            'year_name' => '2026-2027',
            'start_date' => now()->subMonths(5)->toDateString(),
            'end_date' => now()->addMonths(5)->toDateString(),
        ]);
        $trimester = AcademicPeriod::factory()->create([
            'year_id' => $year->id,
            'trimester_name' => 'Primer Trimestre',
        ]);
        GradingScheme::factory()->create([
            'year_id' => $year->id,
            'formative_percentage' => 80.0,
            'summative_percentage' => 0.0,
            'exam_percentage' => 14.0,
            'project_percentage' => 6.0,
        ]);

        $shift = Shift::factory()->create(['shift_name' => 'Matutina']);
        $nivel = Nivel::factory()->create(['shift_id' => $shift->id]);
        $names = ['8° Básica', '9° Básica', '10° Básica', '1° Bach.', '2° Bach.', '3° Bach.'];
        $grades = collect(range(0, 5))->map(fn (int $i): Grade => Grade::factory()->create([
            'nivel_id' => $nivel->id,
            'grade_name' => $names[$i],
        ]));

        $areas = collect(range(1, 4))->map(fn (): Area => Area::factory()->create([
            'area_name' => fake()->randomElement(['Matemáticas', 'Lengua y Literatura', 'Ciencias Naturales', 'Estudios Sociales']),
        ]));
        $subjects = $areas->map(fn (Area $area): Subject => Subject::factory()->create(['area_id' => $area->id]));

        $days = $this->createCalendarDays($year, $trimester, 60);
        $this->command?->info("Estructura lista: {$grades->count()} grados, {$subjects->count()} asignaturas.");

        $teacherUsers = User::factory()->count($teachersCount)->create();
        $teachers = [];
        foreach ($teacherUsers as $index => $user) {
            $teachers[] = Teacher::factory()->create([
                'user_id' => $user->id,
                'teacher_code' => 'DOC-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            ]);
        }
        $this->command?->info("Creados {$teachersCount} docentes con sus usuarios.");

        $studentUsers = User::factory()->count($studentsCount)->create();
        $enrolled = [];
        foreach ($studentUsers as $index => $user) {
            $student = Student::factory()->create([
                'user_id' => $user->id,
                'student_code' => 'EST-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                'enrollment_date' => $year->start_date,
            ]);
            $grade = $grades->get($index % $grades->count());
            $enrolled[$grade->id][] = $student->id;
            StudentEnrollment::create([
                'student_id' => $student->id,
                'grade_id' => $grade->id,
                'year_id' => $year->id,
                'enrollment_date' => $year->start_date,
                'status' => 'active',
                'academic_year' => $year->year_name,
            ]);
        }
        $this->command?->info("Creados {$studentsCount} estudiantes matriculados.");

        $this->assignRoles($teachers, $studentUsers);

        [$schedules, $activities, $recordedBy] = $this->createGradebookStructure(
            $year, $trimester, $teachers, $grades, $subjects,
            $schedulesPerTeacher, $activitiesPerSchedule,
        );
        $this->command?->info('Creados '.count($schedules).' horarios y '.count($activities).' actividades.');

        $this->seedGrades($activities, $enrolled, $recordedBy, $gradeEvery);

        $this->seedAttendance(
            $schedules, $enrolled, $days, $year, $attendanceSchedules,
        );

        $this->command?->info('Seed de carga completado.');
    }

    /**
     * Vacía las tablas tocadas por el seed. En PostgreSQL se usa TRUNCATE ...
     * CASCADE para dejar que el motor resuelva las tablas derivadas
     * (attendance_summaries, course_averages, etc.); en el resto se borran
     * en orden dependiente.
     */
    private function resetLoadData(): void
    {
        $userIds = collect()
            ->merge(Teacher::query()->pluck('user_id'))
            ->merge(Student::query()->pluck('user_id'))
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('TRUNCATE TABLE teachers, students RESTART IDENTITY CASCADE');
        } else {
            DB::table('activity_grades')->delete();
            DB::table('attendances')->delete();
            DB::table('class_observations')->delete();
            DB::table('activities')->delete();
            DB::table('assessment_blocks')->delete();
            DB::table('class_schedules')->delete();
            DB::table('student_enrollments')->delete();
            DB::table('students')->delete();
            DB::table('teachers')->delete();
        }

        DB::table('calendar_days')->delete();
        DB::table('grades')->delete();
        DB::table('nivels')->delete();
        DB::table('shifts')->delete();
        DB::table('subjects')->delete();
        DB::table('areas')->delete();
        DB::table('academic_periods')->delete();
        DB::table('grading_schemes')->delete();
        DB::table('scolar_years')->delete();
        DB::table('schools')->delete();

        if ($userIds !== []) {
            DB::table('model_has_roles')->whereIn('model_id', $userIds)->where('model_type', User::class)->delete();
            User::query()->whereIn('id', $userIds)->delete();
        }
    }

    /**
     * @param  list<Teacher>  $teachers
     * @param  Collection<int, User>  $studentUsers
     */
    private function assignRoles(array $teachers, Collection $studentUsers): void
    {
        $docente = Role::firstOrCreate(['name' => 'DOCENTE', 'guard_name' => 'web']);
        $estudiante = Role::firstOrCreate(['name' => 'ESTUDIANTE', 'guard_name' => 'web']);

        $rows = [];
        foreach ($teachers as $teacher) {
            $rows[] = ['role_id' => $docente->id, 'model_type' => User::class, 'model_id' => $teacher->user_id];
        }
        foreach ($studentUsers as $user) {
            $rows[] = ['role_id' => $estudiante->id, 'model_type' => User::class, 'model_id' => $user->id];
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('model_has_roles')->insertOrIgnore($chunk);
        }

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@eduvex.test'],
            [
                'name' => 'Super Admin Carga',
                'lastname' => 'Sistema',
                'dni' => '1700000002',
                'phone' => '0999999999',
                'cellphone' => '0999999999',
                'address' => 'Quito',
                'status' => 1,
                'password' => bcrypt('password'),
            ],
        );
        if (! $superAdmin->hasRole('SUPER-ADMIN')) {
            $adminRole = Role::firstOrCreate(['name' => 'SUPER-ADMIN', 'guard_name' => 'web']);
            $superAdmin->roles()->attach($adminRole->id);
        }
        $this->command?->info('Usuarios de prueba: superadmin@eduvex.test / password (SUPER-ADMIN).');
    }

    /**
     * @param  list<Teacher>  $teachers
     * @param  Collection<int, Grade>  $grades
     * @param  Collection<int, Subject>  $subjects
     * @return array{0: list<ClassSchedule>, 1: list<int>, 2: array<int, int>}
     */
    private function createGradebookStructure(
        ScolarYear $year, AcademicPeriod $trimester, array $teachers, Collection $grades,
        Collection $subjects, int $schedulesPerTeacher, int $activitiesPerSchedule,
    ): array {
        $daysOfWeek = ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES'];
        $gradeIds = $grades->pluck('id')->all();
        $subjectIds = $subjects->pluck('id')->all();

        $schedules = [];
        $activities = [];
        $recordedBy = [];

        foreach ($teachers as $teacherIndex => $teacher) {
            for ($s = 0; $s < $schedulesPerTeacher; $s++) {
                $schedule = ClassSchedule::create([
                    'year_id' => $year->id,
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subjectIds[($teacherIndex + $s) % count($subjectIds)],
                    'grade_id' => $gradeIds[($teacherIndex + $s * 2) % count($gradeIds)],
                    'schedule_type' => 'OFFICIAL',
                    'day' => $daysOfWeek[($teacherIndex + $s) % count($daysOfWeek)],
                    'start_time' => sprintf('%02d:00', 7 + ($s % 6)),
                    'end_time' => sprintf('%02d:00', 8 + ($s % 6)),
                    'classroom' => fake()->bothify('A-??'),
                    'is_active' => true,
                ]);
                $schedules[] = $schedule;

                $block = (new AssessmentBlock)->fill([
                    'subject_id' => $schedule->subject_id,
                    'grade_id' => $schedule->grade_id,
                    'trimester_id' => $trimester->id,
                    'year_id' => $year->id,
                    'teacher_id' => $teacher->id,
                    'name' => 'Bloque '.($s + 1),
                    'order' => $s + 1,
                    'is_active' => true,
                ]);
                $block->save();
                $recordedBy[$block->id] = (int) $teacher->user_id;

                for ($a = 0; $a < $activitiesPerSchedule; $a++) {
                    $activities[] = DB::table('activities')->insertGetId([
                        'assessment_block_id' => $block->id,
                        'name' => "Actividad {$a} - Horario {$schedule->id}",
                        'date' => now()->toDateString(),
                        'max_score' => 10.0,
                        'status' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return [$schedules, $activities, $recordedBy];
    }

    /**
     * @param  list<int>  $activities
     * @param  array<int, list<int>>  $enrolled  grade_id => student ids
     * @param  array<int, int>  $recordedBy  block_id => user_id
     */
    private function seedGrades(array $activities, array $enrolled, array $recordedBy, int $gradeEvery): void
    {
        $rows = [];
        $count = 0;

        foreach ($activities as $activityId) {
            if ($activityId % $gradeEvery !== 0) {
                continue;
            }

            $blockId = (int) DB::table('activities')->where('id', $activityId)->value('assessment_block_id');
            $gradeId = (int) DB::table('assessment_blocks')->where('id', $blockId)->value('grade_id');

            foreach ($enrolled[$gradeId] ?? [] as $studentId) {
                $rows[] = [
                    'activity_id' => $activityId,
                    'student_id' => $studentId,
                    'grade' => fake()->randomFloat(2, 5, 10),
                    'recorded_by' => $recordedBy[$blockId] ?? array_values($recordedBy)[0],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $count++;

                if (count($rows) >= 500) {
                    DB::table('activity_grades')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('activity_grades')->insert($rows);
        }

        $this->command?->info("Calificaciones sembradas: {$count}.");
    }

    /**
     * @param  list<ClassSchedule>  $schedules
     * @param  array<int, list<int>>  $enrolled  grade_id => student ids
     * @param  Collection<int, CalendarDay>  $days
     */
    private function seedAttendance(
        array $schedules, array $enrolled, Collection $days, ScolarYear $year,
        int $attendanceSchedules,
    ): void {
        $target = array_slice($schedules, 0, $attendanceSchedules);
        $scheduleIds = array_map(fn (ClassSchedule $schedule): int => (int) $schedule->id, $target);

        $recordedBy = $scheduleIds === []
            ? []
            : DB::table('class_schedules')
                ->join('teachers', 'teachers.id', '=', 'class_schedules.teacher_id')
                ->whereIn('class_schedules.id', $scheduleIds)
                ->pluck('teachers.user_id', 'class_schedules.id')
                ->all();

        $dayDates = $days->slice(0, 5)->map(fn (CalendarDay $day): string => $day->date->toDateString())->all();
        $count = 0;

        foreach ($target as $schedule) {
            $userId = $recordedBy[(int) $schedule->id] ?? array_values($recordedBy)[0];
            $studentIds = $enrolled[(int) $schedule->grade_id] ?? [];

            $rows = [];
            foreach ($dayDates as $date) {
                foreach ($studentIds as $studentId) {
                    $rows[] = [
                        'class_schedule_id' => $schedule->id,
                        'student_id' => $studentId,
                        'date' => $date,
                        'year_id' => $year->id,
                        'status' => fake()->randomElement(['P', 'P', 'P', 'A', 'I', 'J']),
                        'recorded_by' => $userId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $count++;
                }

                if (count($rows) >= 500) {
                    DB::table('attendances')->insert($rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                DB::table('attendances')->insert($rows);
            }
        }

        $this->command?->info("Asistencias sembradas: {$count}.");
    }

    /**
     * @return Collection<int, CalendarDay>
     */
    private function createCalendarDays(ScolarYear $year, AcademicPeriod $trimester, int $count): Collection
    {
        $days = collect();
        $date = Carbon::parse($year->start_date)->startOfWeek();

        while ($days->count() < $count) {
            if ($date->isWeekday()) {
                $days->push(CalendarDay::factory()->create([
                    'year_id' => $year->id,
                    'trimester_id' => $trimester->id,
                    'date' => $date->toDateString(),
                    'month_name' => $date->format('F'),
                    'day_name' => $date->format('l'),
                    'week' => $date->weekOfMonth,
                    'day_number' => (int) $date->format('N'),
                    'is_holiday' => false,
                ]));
            }
            $date->addDay();
        }

        return $days;
    }
}
