<?php

namespace Database\Seeders;

use App\Models\Identity\Users\Student;
use App\Models\Identity\Users\Teacher;
use App\Models\Management\Enrollments\StudentEnrollment;
use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\GradingScheme;
use App\Models\Setting\YearSettings\ScolarYear;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DataPrincipalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentYear = (int) date('Y');
        $nextYear = $currentYear + 1;
        $anio = $currentYear.'-'.$nextYear;

        $fechaInicio = Carbon::createFromFormat('Y-m-d', "{$currentYear}-08-12")->startOfDay();
        $fechaFin = Carbon::createFromFormat('Y-m-d', "{$nextYear}-07-10")->endOfDay();

        // Crear escuela
        $school = School::create([
            'name_school' => 'Unidad Educativa Vicente Leon',
            'distrit' => 'DISTRITO 05D01 - CIRCUITO C6_11 - AMIE 05H00091',
            'location' => 'Latacunga -Cotopaxi- Ecuador',
            'address' => 'Av.Tahuantinsuyo y Cañaris/Sector la Cocha',
            'phone' => '9999999999',
            'email' => 'info@uevicenteleon.com',
            'website' => 'https://uevicenteleon.edu.ec',
            'logo_path' => 'app-resources/img/logos/ue-vicente-leon.jpg',
            'report_logo_path' => 'app-resources/img/logos/ue-vicente-leon.jpg',
            'status' => 1,
        ]);

        // Crear año escolar
        $year = ScolarYear::firstOrCreate([
            // 'school_id' => $school->id,
            'year_name' => $anio,
            'start_date' => $fechaInicio,
            'end_date' => $fechaFin,
            'status' => 1,
        ]);

        // [Resto del código para shifts, niveles y grados...]

        // Crear trimestres asociados al año escolar
        $trimestres = [
            [
                'year_id' => $year->id,
                'trimester_name' => 'Primer Trimestre',
                'start_date' => Carbon::create($currentYear, 9, 1)->startOfDay(),
                'end_date' => Carbon::create($currentYear, 12, 1)->endOfDay(),
                'grading_open_date' => Carbon::create($currentYear, 9, 1),
                'grading_close_date' => Carbon::create($currentYear, 12, 7),
                'is_supletorio' => false,
                'status' => 1,
            ],
            [
                'year_id' => $year->id,
                'trimester_name' => 'Segundo Trimestre',
                'start_date' => Carbon::create($currentYear, 12, 2)->startOfDay(),
                'end_date' => Carbon::create($nextYear, 3, 15)->endOfDay(),
                'grading_open_date' => Carbon::create($currentYear, 12, 2),
                'grading_close_date' => Carbon::create($nextYear, 3, 22),
                'is_supletorio' => false,
                'status' => 1,
            ],
            [
                'year_id' => $year->id,
                'trimester_name' => 'Tercer Trimestre',
                'start_date' => Carbon::create($nextYear, 3, 16)->startOfDay(),
                'end_date' => Carbon::create($nextYear, 6, 16)->endOfDay(),
                'grading_open_date' => Carbon::create($nextYear, 3, 16),
                'grading_close_date' => Carbon::create($nextYear, 6, 23),
                'is_supletorio' => false,
                'status' => 1,
            ],
            [
                'year_id' => $year->id,
                'trimester_name' => 'Supletorio',
                'start_date' => Carbon::create($nextYear, 6, 17)->startOfDay(),
                'end_date' => Carbon::create($nextYear, 7, 6)->endOfDay(),
                'grading_open_date' => Carbon::create($nextYear, 6, 17),
                'grading_close_date' => $fechaFin,
                'is_supletorio' => true,
                'status' => 1,
            ],
        ];

        foreach ($trimestres as $trimestreData) {
            // Verificar que las fechas estén dentro del rango del año escolar
            if ($trimestreData['start_date']->lt($year->start_date)) {
                $this->command->warn("Ajustando fecha de inicio del trimestre {$trimestreData['trimester_name']} para que no sea anterior al inicio del año escolar");
                $trimestreData['start_date'] = $year->start_date->copy();
            }

            if ($trimestreData['end_date']->gt($year->end_date)) {
                $this->command->warn("Ajustando fecha de fin del trimestre {$trimestreData['trimester_name']} para que no sea posterior al fin del año escolar");
                $trimestreData['end_date'] = $year->end_date->copy();
            }

            // Verificar que no se solapen los trimestres
            $existingTrimester = AcademicPeriod::where('year_id', $year->id)
                ->where(function ($query) use ($trimestreData) {
                    $query->whereBetween('start_date', [$trimestreData['start_date'], $trimestreData['end_date']])
                        ->orWhereBetween('end_date', [$trimestreData['start_date'], $trimestreData['end_date']])
                        ->orWhere(function ($q) use ($trimestreData) {
                            $q->where('start_date', '<=', $trimestreData['start_date'])
                                ->where('end_date', '>=', $trimestreData['end_date']);
                        });
                })
                ->exists();

            if ($existingTrimester) {
                $this->command->error("No se pudo crear el trimestre {$trimestreData['trimester_name']} porque se solapa con otro trimestre existente");

                continue;
            }

            // Crear el trimestre
            AcademicPeriod::firstOrCreate(
                [
                    'year_id' => $year->id,
                    'trimester_name' => $trimestreData['trimester_name'],
                ],
                $trimestreData
            );

            $this->command->info("Trimestre {$trimestreData['trimester_name']} creado: {$trimestreData['start_date']->format('Y-m-d')} al {$trimestreData['end_date']->format('Y-m-d')}");
        }

        // Crear esquema de calificaciones
        GradingScheme::firstOrCreate(
            ['year_id' => $year->id],
            [
                'year_id' => $year->id,
                'formative_percentage' => 70,
                'summative_percentage' => 30,
                'exam_percentage' => 20,
                'project_percentage' => 10,
                'status' => 1,
            ]
        );
        $this->command->info('Esquema de calificaciones creado: Formativo 70% + Sumativo 30% (Examen 20%, Proyecto 10%)');

        // Crear turnos
        $shifts = [
            ['shift_name' => 'MATUTINA', 'status' => 1],
            ['shift_name' => 'VESPERTINA', 'status' => 1],
            ['shift_name' => 'INTENSIVO', 'status' => 0],
        ];

        foreach ($shifts as $shiftData) {
            $shift = Shift::firstOrCreate([
                // 'year_id' => $year->id,
                'shift_name' => $shiftData['shift_name'],
                'status' => $shiftData['status'],
            ]);

            // Crear niveles para cada turno
            $niveles = [
                ['nivel_name' => 'Educación_Inicial', 'status' => 1],
                ['nivel_name' => 'Educación_General_Básica_Preparatoria', 'status' => 1],
                ['nivel_name' => 'Educación_General_Básica_Elemental', 'status' => 1],
                ['nivel_name' => 'Educación_General_Básica_Media', 'status' => 1],
                ['nivel_name' => 'Educación_General_Básica_Superior', 'status' => 1],
                ['nivel_name' => 'Bachillerato_General_Unificado', 'status' => 1],
                ['nivel_name' => 'Bachillerato_Técnico_Inf-Desarrollo de Soft', 'status' => 1],
                ['nivel_name' => 'Bachillerato_Técnico_Com-Gestion y Log', 'status' => 1],
                ['nivel_name' => 'Bachillerato_Técnico_Promotor_Rec_Dep-Actividad_Fis_Dep_Rec', 'status' => 1],
            ];
            // strtoupper(
            foreach ($niveles as $nivelData) {
                $nivel = Nivel::firstOrCreate([
                    'shift_id' => $shift->id,
                    'nivel_name' => $nivelData['nivel_name'],
                    'status' => $nivelData['status'],
                ]);

                // Crear grados para cada nivel
                $grados = [];
                if ($nivelData['nivel_name'] == 'Educación_Inicial') {
                    $grados = [];
                    // Crear grados 1 y 2
                    foreach ([1, 2] as $gradoNum) {
                        // Crear secciones de A a F para cada grado
                        foreach (range('A', 'F') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° Educación Inicial',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                } elseif ($nivelData['nivel_name'] == 'Educación_General_Básica_Preparatoria') {
                    foreach (range(1, 2) as $gradoNum) {
                        foreach (range('A', 'F') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° EGB Preparatoria',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                } elseif ($nivelData['nivel_name'] == 'Educación_General_Básica_Elemental') {
                    foreach (range(3, 4) as $gradoNum) {
                        foreach (range('A', 'F') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° EGB Basica Elemental',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                } elseif ($nivelData['nivel_name'] == 'Educación_General_Básica_Media') {
                    foreach (range(5, 7) as $gradoNum) {
                        foreach (range('A', 'F') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° EGB Basica Media',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                } elseif ($nivelData['nivel_name'] == 'Educación_General_Básica_Superior') {
                    foreach (range(8, 10) as $gradoNum) {
                        foreach (range('A', 'F') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° EGB Basica Superior',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                } elseif ($nivelData['nivel_name'] == 'Bachillerato_General_Unificado') {
                    foreach (range(1, 3) as $gradoNum) {
                        foreach (range('A', 'C') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° BGU General Unificado',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                } elseif ($nivelData['nivel_name'] == 'Bachillerato_Técnico_Inf-Desarrollo de Soft') {
                    foreach (range(1, 3) as $gradoNum) {
                        foreach (range('A', 'B') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° BT Técnico Desarrollo Software',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                    foreach (range(3, 3) as $gradoNum) {
                        foreach (range('A', 'B') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° BT Técnico Inf',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                } elseif ($nivelData['nivel_name'] == 'Bachillerato_Técnico_Com-Gestion y Log') {
                    foreach (range(1, 3) as $gradoNum) {
                        foreach (range('A', 'B') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° BT Técnico Gestion y Logística',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                    foreach (range(3, 3) as $gradoNum) {
                        foreach (range('A', 'B') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° BT Técnico Com',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                } elseif ($nivelData['nivel_name'] == 'Bachillerato_Técnico_Promotor_Rec_Dep-Actividad_Fis_Dep_Rec') {
                    foreach (range(1, 3) as $gradoNum) {
                        foreach (range('A', 'B') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° BT Técnico Actividad Fis, Dep y Rec',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                    foreach (range(3, 3) as $gradoNum) {
                        foreach (range('A', 'B') as $seccion) {
                            $grados[] = [
                                'grade_name' => $gradoNum.'° BT Técnico Promotor en Rec y Dep',
                                'section' => $seccion,
                                'status' => 1,
                            ];
                        }
                    }
                }

                foreach ($grados as $gradoData) {
                    Grade::firstOrCreate([
                        'nivel_id' => $nivel->id,
                        'grade_name' => $gradoData['grade_name'],
                        'section' => $gradoData['section'],
                        'status' => $gradoData['status'],
                    ]);
                }
            }
        }

        // Crear áreas
        $areas = [
            'Inicial',
            'Basica Preparatoria',
            'Basica Media',
            'Ciencias Naturales, Biologia y Fisica',
            'Educación Cultural y Artística',
            'Estudios Sociales',
            'Matematica',
            'Lengua Extranjera',
            'Lengua y Literatura',
            'BT Comercio y Ventas -Emprendimiento- Gestion Administrativa y Logistica',
            'BT Deportes y Recreacion-Educación Física',
            'BT Informatica-Desarrollo de Software',
            'Optativas',
            'Tutoria',
        ];

        foreach ($areas as $areaName) {
            $area = Area::firstOrCreate(['area_name' => $areaName]);

            // Crear materias para cada área
            $materias = [];
            switch ($areaName) {
                case 'Inicial':
                    $materias = ['Currículo Integrado por ámbitos de aprendizaje'];
                    break;
                case 'Basica Preparatoria':
                    $materias = ['Currículo Integrado por ámbitos'];
                    break;
                case 'Basica Media':
                    $materias = ['Matemáticas', 'Ciencias Naturales', 'Lengua y Literatura', 'Estudios Sociales'];
                    break;
                case 'Ciencias Naturales, Biologia y Fisica':
                    $materias = ['Ciencias Naturales',
                        'Química',
                        'Quimica Superior',
                        'Biología',
                        'Biología Superior',
                        'Fisica',
                        'Fisica Superior'];
                    break;
                case 'Educación Cultural y Artística':
                    $materias = ['Educación Cultural y Artística',
                        'Dibujo Técnico Aplicado a Comercialización y Ventas'];
                    break;
                case 'Estudios Sociales':
                    $materias = ['Estudios Sociales',
                        'Filosofía',
                        'Historia',
                        'Educación para la Ciudadanía',
                        'Investigacion Ciencia y Tecnoclogia'];
                    break;
                case 'Matematica':
                    $materias = ['Matemáticas',
                        'Matematica Superior'];
                    break;
                case 'Lengua Extranjera':
                    $materias = ['Inglés',
                        'Inglés Técnico Aplicado a Comercialización y Ventas',
                        'Inglés Técnico Aplicado a los Negocios'];
                    break;
                case 'Lengua y Literatura':
                    $materias = ['Lengua y Literatura',
                        'Animación a la lectura'];
                    break;
                case 'BT Comercio y Ventas -Emprendimiento- Gestion Administrativa y Logistica':
                    $materias = [
                        'Herramientas Informaticas Empresariales',
                        'Gestión Contable y Administracion Financiera',
                        'Compras y Logistica',
                        'Gestión Comercial y Comunicacion',
                        'Gestión de Procesos Administrativos', // hasta aqui modulos nuevos
                        'Emprendimiento y Gestión',
                        'Animación en el Punto de Venta',
                        'Operaciones de Venta',
                        'Operaciones de Almacenaje',
                        'Informática Aplicada a Comercialización y Ventas',
                        'Formación y Orientación Laboral - FOL-COMER'];
                    break;
                case 'BT Deportes y Recreacion-Educación Física':
                    $materias = [
                        'Salud, hábitos y práctica recreativa',
                        'Desarrollo deportivo y cultural',
                        'Administración deportiva y cultural',
                        'Planificación de actividades deportivas y recreativas ',
                        'Sesiones deportivas y recreativas',
                        'Promoción de la salud y valores en la práctica deportiva ',
                        'Seguridad, higiene y primeros auxilios deportivos ', // hasta aqui modulos nuevos
                        'Educación Física',
                        'Actividades Recreativas',
                        'Planificación y Evaluación en Recreación y Deportes',
                        'Entrenamiento Deportivo',
                        'Organización de Eventos Recreativos y/o Deportivos',
                        'Bases Fisiológicas',
                        'Manejo de Grupos',
                        'Seguridad y Primeros Auxilios',
                        'Recursos Recreativos y Deportivos',
                        'Formación y Orientación Laboral - FOL-DEPORTES',
                    ];
                    break;
                case 'BT Informatica-Desarrollo de Software':
                    $materias = [
                        'Fundamentos de las Tecnologias de la Informacion y Com',
                        'Pensamiento Computacional y Resolucion de Problemas',
                        'Etica, Legislacion y Ciudadania digital',
                        'Programación Estructurada',
                        'Programación Orientada a Objetos',
                        'Base de Datos',
                        'Aplicaciones de Escritorio',
                        'Aplicaciones WEB y Moviles',
                        'Modulo Practico Experimentnal', // hasta aqui modulos nuevos
                        'Programación y Bases de Datos',
                        'Diseño y Desarrollo WEB',
                        'Soporte Técnico',
                        'Sistemas Operativos y Redes',
                        'Aplicaciones Ofimáticas Locales y en Línea',
                        'Formación y Orientación Laboral - FOL-INFOR'];
                    break;
                case 'Optativas':
                    $materias = ['Asignaturas optativas',
                        'Orientación vocacional y profesional'];
                    break;
                case 'Tutoria':
                    $materias = ['Acompañamiento integral en el aula',
                        'Cívica'];
                    break;
            }

            foreach ($materias as $materia) {
                Subject::firstOrCreate([
                    'area_id' => $area->id,
                    'subject_name' => $materia,
                ]);
            }
        }

        // ─── DATOS DE PRUEBA: DOCENTE, HORARIOS, ESTUDIANTES ───
        $this->command->info('Creando datos de prueba (docente, horarios, estudiantes)...');

        // Asignar rol DOCENTE al primer usuario sin rol
        $teacherUser = User::doesntHave('roles')->first();
        if (! $teacherUser) {
            $teacherUser = User::first();
        }
        $teacherUser->assignRole('DOCENTE');

        // Crear registro Teacher para el docente
        $teacher = Teacher::firstOrCreate(
            ['user_id' => $teacherUser->id],
            [
                'teacher_code' => 'DOC-'.str_pad($teacherUser->id, 4, '0', STR_PAD_LEFT),
                'specialization' => 'Matemáticas',
                'title' => 'Licenciado en Matemáticas',
                'education_level' => 'Superior',
            ]
        );

        // Asignar rol TUTOR al segundo usuario sin rol
        $tutorUser = User::whereDoesntHave('roles')->where('id', '!=', $teacherUser->id)->first() ?? User::skip(1)->first();
        $tutorUser->assignRole('TUTOR');

        // Asignar rol ESTUDIANTE al resto de usuarios sin rol (hasta 10)
        $studentUsers = User::whereDoesntHave('roles')->take(10)->get();
        foreach ($studentUsers as $su) {
            $su->assignRole('ESTUDIANTE');
        }

        // Buscar un grado concreto: 8° EGB Basica Superior, sección A
        $nivelSuperior = Nivel::where('nivel_name', 'Educación_General_Básica_Superior')->first();
        $targetGrade = Grade::where('nivel_id', $nivelSuperior?->id)
            ->where('grade_name', '8° EGB Basica Superior')
            ->where('section', 'A')
            ->first();

        if ($targetGrade && $teacher) {
            // Buscar materia: Matemáticas
            $mathArea = Area::where('area_name', 'Matematica')->first();
            $subject = Subject::where('area_id', $mathArea?->id)
                ->where('subject_name', 'Matemáticas')
                ->first();

            if ($subject) {
                // Crear horario de clase (ClassSchedule) — un registro por cada día LUNES-VIERNES
                $days = ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES'];
                $trimester = AcademicPeriod::where('year_id', $year->id)->first();

                foreach ($days as $i => $day) {
                    ClassSchedule::firstOrCreate(
                        [
                            'teacher_id' => $teacher->id,
                            'subject_id' => $subject->id,
                            'grade_id' => $targetGrade->id,
                            'year_id' => $year->id,
                            'day' => $day,
                            'start_time' => now()->setTime(7 + $i, 0),
                            'schedule_type' => 'OFFICIAL',
                        ],
                        [
                            'trimester_id' => $trimester?->id,
                            'end_time' => now()->setTime(7 + $i + 1, 0),
                            'is_active' => true,
                        ]
                    );
                }

                // Crear 5 estudiantes y matriculaciones
                $studentNames = [
                    ['JUAN', 'PEREZ GOMEZ'],
                    ['MARIA', 'LOPEZ MARTINEZ'],
                    ['CARLOS', 'RAMIREZ SANCHEZ'],
                    ['ANA', 'GARCIA RODRIGUEZ'],
                    ['PEDRO', 'TORRES MORA'],
                ];

                foreach ($studentNames as $idx => [$name, $lastname]) {
                    $studentUser = User::create([
                        'name' => $name,
                        'lastname' => $lastname,
                        'dni' => 1000000000 + $idx,
                        'phone' => '990000000'.$idx,
                        'cellphone' => '980000000'.$idx,
                        'address' => 'Dirección estudiante '.($idx + 1),
                        'email' => "estudiante{$idx}@test.com",
                        'password' => bcrypt('password'),
                        'status' => 1,
                    ]);
                    $studentUser->assignRole('ESTUDIANTE');

                    $student = Student::create([
                        'user_id' => $studentUser->id,
                        'student_code' => 'EST-'.str_pad($idx + 1, 4, '0', STR_PAD_LEFT),
                        'enrollment_date' => $year->start_date,
                    ]);

                    StudentEnrollment::create([
                        'student_id' => $student->id,
                        'grade_id' => $targetGrade->id,
                        'year_id' => $year->id,
                        'enrollment_date' => $year->start_date,
                        'status' => 'active',
                        'academic_year' => $year->year_name,
                    ]);
                }

                $this->command->info("Docente {$teacherUser->name} asignado a Matemáticas - {$targetGrade->grade_name} {$targetGrade->section}");
                $this->command->info('5 estudiantes creados y matriculados.');
            }
        }

        $this->command->info('Datos iniciales de la escuela creados exitosamente!');
    }
}
