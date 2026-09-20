<?php

namespace Database\Seeders;

use App\Models\Setting\EducationalSettings\Area;
use App\Models\Setting\EducationalSettings\Grade;
use App\Models\Setting\EducationalSettings\Nivel;
use App\Models\Setting\EducationalSettings\School;
use App\Models\Setting\EducationalSettings\Shift;
use App\Models\Setting\EducationalSettings\Subject;
use App\Models\Setting\YearSettings\AcademicPeriod;
use App\Models\Setting\YearSettings\GradingScheme;
use App\Models\Setting\YearSettings\ScolarYear;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class BasicDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Datos básicos de la institución (configuración académica) sin usuarios.
     *
     * Idempotente (firstOrCreate): seguro para ejecutarse como comando de
     * deploy en Laravel Cloud. No crea usuarios, roles ni permisos.
     */
    public function run(): void
    {
        $currentYear = (int) date('Y');
        $nextYear = $currentYear + 1;
        $anio = $currentYear.'-'.$nextYear;

        $fechaInicio = Carbon::createFromFormat('Y-m-d', "{$currentYear}-08-12")->startOfDay();
        $fechaFin = Carbon::createFromFormat('Y-m-d', "{$nextYear}-07-10")->endOfDay();

        School::firstOrCreate(['name_school' => 'Unidad Educativa Vicente Leon'], [
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

        // year_name es la clave natural: comparar además por fechas
        // (Carbon) no matchea de forma fiable en MySQL.
        $year = ScolarYear::firstOrCreate(
            ['year_name' => $anio],
            [
                'start_date' => $fechaInicio,
                'end_date' => $fechaFin,
                'status' => 1,
            ]
        );

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
            if ($trimestreData['start_date']->lt($year->start_date)) {
                $trimestreData['start_date'] = $year->start_date->copy();
            }

            if ($trimestreData['end_date']->gt($year->end_date)) {
                $trimestreData['end_date'] = $year->end_date->copy();
            }

            $seSolapa = AcademicPeriod::where('year_id', $year->id)
                ->where(function ($query) use ($trimestreData) {
                    $query->whereBetween('start_date', [$trimestreData['start_date'], $trimestreData['end_date']])
                        ->orWhereBetween('end_date', [$trimestreData['start_date'], $trimestreData['end_date']])
                        ->orWhere(function ($q) use ($trimestreData) {
                            $q->where('start_date', '<=', $trimestreData['start_date'])
                                ->where('end_date', '>=', $trimestreData['end_date']);
                        });
                })
                ->exists();

            if ($seSolapa) {
                continue;
            }

            AcademicPeriod::firstOrCreate(
                [
                    'year_id' => $year->id,
                    'trimester_name' => $trimestreData['trimester_name'],
                ],
                $trimestreData
            );
        }

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

        $shifts = [
            ['shift_name' => 'MATUTINA', 'status' => 1],
            ['shift_name' => 'VESPERTINA', 'status' => 1],
            ['shift_name' => 'INTENSIVO', 'status' => 0],
        ];

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

        foreach ($shifts as $shiftData) {
            $shift = Shift::firstOrCreate([
                'shift_name' => $shiftData['shift_name'],
                'status' => $shiftData['status'],
            ]);

            foreach ($niveles as $nivelData) {
                $nivel = Nivel::firstOrCreate([
                    'shift_id' => $shift->id,
                    'nivel_name' => $nivelData['nivel_name'],
                    'status' => $nivelData['status'],
                ]);

                foreach ($this->gradosDelNivel($nivelData['nivel_name']) as $gradoData) {
                    Grade::firstOrCreate([
                        'nivel_id' => $nivel->id,
                        'grade_name' => $gradoData['grade_name'],
                        'section' => $gradoData['section'],
                        'status' => $gradoData['status'],
                    ]);
                }
            }
        }

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

            foreach ($this->materiasDelArea($areaName) as $materia) {
                Subject::firstOrCreate([
                    'area_id' => $area->id,
                    'subject_name' => $materia,
                ]);
            }
        }
    }

    /**
     * @return array<int, array{grade_name: string, section: string, status: int}>
     */
    private function gradosDelNivel(string $nivelName): array
    {
        $grados = [];

        if ($nivelName === 'Educación_Inicial' || $nivelName === 'Educación_General_Básica_Preparatoria') {
            foreach ([1, 2] as $gradoNum) {
                foreach (range('A', 'F') as $seccion) {
                    $grados[] = [
                        'grade_name' => $nivelName === 'Educación_Inicial' ? $gradoNum.'° Educación Inicial' : $gradoNum.'° EGB Preparatoria',
                        'section' => $seccion,
                        'status' => 1,
                    ];
                }
            }

            return $grados;
        }

        if ($nivelName === 'Educación_General_Básica_Elemental') {
            foreach (range(3, 4) as $gradoNum) {
                foreach (range('A', 'F') as $seccion) {
                    $grados[] = [
                        'grade_name' => $gradoNum.'° EGB Basica Elemental',
                        'section' => $seccion,
                        'status' => 1,
                    ];
                }
            }

            return $grados;
        }

        if ($nivelName === 'Educación_General_Básica_Media') {
            foreach (range(5, 7) as $gradoNum) {
                foreach (range('A', 'F') as $seccion) {
                    $grados[] = [
                        'grade_name' => $gradoNum.'° EGB Basica Media',
                        'section' => $seccion,
                        'status' => 1,
                    ];
                }
            }

            return $grados;
        }

        if ($nivelName === 'Educación_General_Básica_Superior') {
            foreach (range(8, 10) as $gradoNum) {
                foreach (range('A', 'F') as $seccion) {
                    $grados[] = [
                        'grade_name' => $gradoNum.'° EGB Basica Superior',
                        'section' => $seccion,
                        'status' => 1,
                    ];
                }
            }

            return $grados;
        }

        if ($nivelName === 'Bachillerato_General_Unificado') {
            foreach (range(1, 3) as $gradoNum) {
                foreach (range('A', 'C') as $seccion) {
                    $grados[] = [
                        'grade_name' => $gradoNum.'° BGU General Unificado',
                        'section' => $seccion,
                        'status' => 1,
                    ];
                }
            }

            return $grados;
        }

        if ($nivelName === 'Bachillerato_Técnico_Inf-Desarrollo de Soft') {
            foreach ($this->gradosTecnicos('BT Técnico Desarrollo Software') as $grado) {
                $grados[] = $grado;
            }

            foreach ($this->gradosTecnicos('BT Técnico Inf', rango: [3]) as $grado) {
                $grados[] = $grado;
            }

            return $grados;
        }

        if ($nivelName === 'Bachillerato_Técnico_Com-Gestion y Log') {
            foreach ($this->gradosTecnicos('BT Técnico Gestion y Logística') as $grado) {
                $grados[] = $grado;
            }

            foreach ($this->gradosTecnicos('BT Técnico Com', rango: [3]) as $grado) {
                $grados[] = $grado;
            }

            return $grados;
        }

        if ($nivelName === 'Bachillerato_Técnico_Promotor_Rec_Dep-Actividad_Fis_Dep_Rec') {
            foreach ($this->gradosTecnicos('BT Técnico Actividad Fis, Dep y Rec') as $grado) {
                $grados[] = $grado;
            }

            foreach ($this->gradosTecnicos('BT Técnico Promotor en Rec y Dep', rango: [3]) as $grado) {
                $grados[] = $grado;
            }

            return $grados;
        }

        return $grados;
    }

    /**
     * @param  array<int, int>  $rango
     * @return array<int, array{grade_name: string, section: string, status: int}>
     */
    private function gradosTecnicos(string $etiqueta, array $rango = [1, 2, 3]): array
    {
        $grados = [];

        foreach ($rango as $gradoNum) {
            foreach (range('A', 'B') as $seccion) {
                $grados[] = [
                    'grade_name' => $gradoNum.'° '.$etiqueta,
                    'section' => $seccion,
                    'status' => 1,
                ];
            }
        }

        return $grados;
    }

    /**
     * @return array<int, string>
     */
    private function materiasDelArea(string $areaName): array
    {
        return match ($areaName) {
            'Inicial' => ['Currículo Integrado por ámbitos de aprendizaje'],
            'Basica Preparatoria' => ['Currículo Integrado por ámbitos'],
            'Basica Media' => ['Matemáticas', 'Ciencias Naturales', 'Lengua y Literatura', 'Estudios Sociales'],
            'Ciencias Naturales, Biologia y Fisica' => ['Ciencias Naturales', 'Química', 'Quimica Superior', 'Biología', 'Biología Superior', 'Fisica', 'Fisica Superior'],
            'Educación Cultural y Artística' => ['Educación Cultural y Artística', 'Dibujo Técnico Aplicado a Comercialización y Ventas'],
            'Estudios Sociales' => ['Estudios Sociales', 'Filosofía', 'Historia', 'Educación para la Ciudadanía', 'Investigacion Ciencia y Tecnoclogia'],
            'Matematica' => ['Matemáticas', 'Matematica Superior'],
            'Lengua Extranjera' => ['Inglés', 'Inglés Técnico Aplicado a Comercialización y Ventas', 'Inglés Técnico Aplicado a los Negocios'],
            'Lengua y Literatura' => ['Lengua y Literatura', 'Animación a la lectura'],
            'BT Comercio y Ventas -Emprendimiento- Gestion Administrativa y Logistica' => [
                'Herramientas Informaticas Empresariales',
                'Gestión Contable y Administracion Financiera',
                'Compras y Logistica',
                'Gestión Comercial y Comunicacion',
                'Gestión de Procesos Administrativos',
                'Emprendimiento y Gestión',
                'Animación en el Punto de Venta',
                'Operaciones de Venta',
                'Operaciones de Almacenaje',
                'Informática Aplicada a Comercialización y Ventas',
                'Formación y Orientación Laboral - FOL-COMER',
            ],
            'BT Deportes y Recreacion-Educación Física' => [
                'Salud, hábitos y práctica recreativa',
                'Desarrollo deportivo y cultural',
                'Administración deportiva y cultural',
                'Planificación de actividades deportivas y recreativas ',
                'Sesiones deportivas y recreativas',
                'Promoción de la salud y valores en la práctica deportiva ',
                'Seguridad, higiene y primeros auxilios deportivos ',
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
            ],
            'BT Informatica-Desarrollo de Software' => [
                'Fundamentos de las Tecnologias de la Informacion y Com',
                'Pensamiento Computacional y Resolucion de Problemas',
                'Etica, Legislacion y Ciudadania digital',
                'Programación Estructurada',
                'Programación Orientada a Objetos',
                'Base de Datos',
                'Aplicaciones de Escritorio',
                'Aplicaciones WEB y Moviles',
                'Modulo Practico Experimentnal',
                'Programación y Bases de Datos',
                'Diseño y Desarrollo WEB',
                'Soporte Técnico',
                'Sistemas Operativos y Redes',
                'Aplicaciones Ofimáticas Locales y en Línea',
                'Formación y Orientación Laboral - FOL-INFOR',
            ],
            'Optativas' => ['Asignaturas optativas', 'Orientación vocacional y profesional'],
            'Tutoria' => ['Acompañamiento integral en el aula', 'Cívica'],
            default => [],
        };
    }
}
