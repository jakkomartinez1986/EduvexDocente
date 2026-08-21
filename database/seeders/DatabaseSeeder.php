<?php

namespace Database\Seeders;

use App\Models\Security\Authorizations\Permission;
use App\Models\Security\Authorizations\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        if ($this->command->confirm('¿Desea actualizar la migración antes de sembrar? Borrará todos los datos antiguos? [y|N]', true)) {
            $this->command->call('migrate:fresh');
            $this->command->warn('Datos borrados, Iniciando la base de datos en blanco.');
        }

        $this->call([
            PermissionsSeeder::class,
        ]);

        $this->command->info('Permisos de Inclusión Educativa agregados.');
        $this->command->info('Permisos predeterminados agregados.');
        // Confirm roles needed
        if ($this->command->confirm('Crear roles para el usuario? Se crearán: Super-Admin, Admin, Rector, Vicerrector, Inspector, Dir-Area, Dece, Tutor, Docente, Estudiante, Representante [y|N]', true)) {
            // roles y descripcion de rol
            $allPerms = Permission::all();
            $readPerms = Permission::where('name', 'LIKE', 'ver-%')->get();

            $input_roles = ['Super-Admin' => 'Super Administrador pueden realizar cualquier acción',
                'Admin' => 'Administrador están habilitados para leer,crear,actualizar,compartir,firmar documentos',
                'Rector' => 'Rector están habilitados para leer ,firmar documentos)',
                'Vicerrector' => 'Vicerrector están habilitados para leer (todos los documentos)- crear(año lectivo-periodo academico-areas- tipo documento-asignar directores) y actualizar(estado usuario,año lectivo-periodo academico-areas- tipo documento-asignar directores)',
                'Inspector' => 'Rector están habilitados para leer ,firmar documentos,revisar asistencias)',
                'Dir-Area' => 'Director de Area  están habilitados para leer(documentos area),crear(equipos), actualizar (estado usuario,equipos)',
                'Dece' => 'Ps Dece están habilitados para leer documentos NEE ,firmar documentos de NEE',
                'Tutor' => 'Generar Documentacion de curso asignado, firmar documentos de curso asignado, llevar asistencia de curso asignado,subir listado estudiantes del curso asignado',
                'Docente' => 'Docente están habilitados para leer- crear- actualizar-compartir (documentos,notas,horario de clases) firmar documentos de curso asignado, llevar asistencia de curso asignado, subir listado estudiantes del curso asignado',
                'Estudiante' => 'Estudiante esta habilitado para ver horario de clases, ver documentos de curso asignado, ver asistencia de curso asignado, ver notas',
                'Representante' => 'Representante esta habilitado para ver horario de clases, ver notas , ver asitencia, ver noitificaciones de estudiante seleccionado, justificar inasistencia de 1 dia', ];
            //
            $input_array = ('Super-Admin,Admin,Rector,Vicerrector,Inspector,Dir-Area,Dece,Tutor,Docente,Estudiante,Representante');
            // add roles strtoupper($query)
            foreach ($input_roles as $role => $description) {
                $rol = Role::firstOrCreate(['name' => trim(strtoupper($role)), 'description' => $description, 'guard_name' => config('auth.defaults.guard')]);

                if ($rol->name === 'SUPER-ADMIN') {
                    $rol->syncPermissions($allPerms);
                    $this->command->info('Super-Admin: todos los permisos');
                } elseif (in_array($rol->name, ['ADMIN'])) {
                    $rol->syncPermissions($allPerms);
                    $this->command->info("{$role}: todos los permisos");
                } elseif (in_array($rol->name, ['RECTOR', 'VICERRECTOR'])) {
                    $perms = Permission::where('name', 'LIKE', 'ver-%')->get();
                    $extra = Permission::whereIn('name', [
                        'crear-year', 'editar-year',
                        'crear-trimester', 'editar-trimester',
                        'crear-grade', 'editar-grade',
                        'crear-nivel', 'editar-nivel',
                        'crear-shift', 'editar-shift',
                        'crear-area', 'editar-area',
                        'crear-subject', 'editar-subject',
                        'crear-gradingscheme', 'editar-gradingscheme',
                        'actualizar-estado-user',
                    ])->get();
                    $rol->syncPermissions($perms->merge($extra));
                    $this->command->info("{$role}: lectura + gestión académica");
                } elseif (in_array($rol->name, ['INSPECTOR'])) {
                    $perms = Permission::where('name', 'LIKE', 'ver-%')->get();
                    $extra = Permission::whereIn('name', [
                        'crear-attendance', 'editar-attendance',
                        'crear-attendancesummary', 'editar-attendancesummary',
                        'crear-classobservation', 'editar-classobservation',
                    ])->get();
                    $rol->syncPermissions($perms->merge($extra));
                    $this->command->info("{$role}: lectura + gestión asistencias");
                } elseif (in_array($rol->name, ['DECE'])) {
                    $rol->syncPermissions($readPerms);
                    $this->command->info("{$role}: solo lectura");
                } elseif (in_array($rol->name, ['DIR-AREA'])) {
                    $extra = Permission::whereIn('name', [
                        'crear-user', 'editar-user', 'actualizar-estado-user',
                    ])->get();
                    $rol->syncPermissions($readPerms->merge($extra));
                    $this->command->info("{$role}: lectura + gestión usuarios");
                } elseif (in_array($rol->name, ['DOCENTE', 'TUTOR'])) {
                    $academicPatterns = [
                        'ver-%', 'crear-%', 'editar-%',
                        'attendance', 'attendancesummary', 'classobservation',
                        'activitygrade', 'activityrecovery', 'studentexam', 'studentproject',
                        'classschedule', 'homeworkpending', 'document',
                    ];
                    $academicCrud = Permission::where(function ($q) use ($academicPatterns) {
                        foreach ($academicPatterns as $pattern) {
                            if (str_contains($pattern, '%')) {
                                $q->orWhere('name', 'LIKE', $pattern);
                            } else {
                                $q->orWhere('name', 'LIKE', '%-'.$pattern);
                                $q->orWhere('name', 'LIKE', '%-'.$pattern.'-%');
                            }
                        }
                    })->get();
                    $rol->syncPermissions($academicCrud);
                    $this->command->info("{$role}: CRUD académico + asistencias + estudiantes");
                } elseif (in_array($rol->name, ['ESTUDIANTE', 'REPRESENTANTE'])) {
                    $rol->syncPermissions($readPerms);
                    $this->command->info("{$role}: solo lectura");
                } else {
                    $rol->syncPermissions($readPerms);
                }
            }
            $this->command->info('Roles '.$input_array.' actualizados con permisos específicos');
        } else {
            Role::firstOrCreate(['name' => 'ADMIN']);
            $this->command->info('Solo se agregó la función de usuario predeterminada a lectura.');
        }

        $this->command->info('Creando 20 usuarios de prueba.');
        User::factory(20)->create();

        $requiredRoles = ['DOCENTE', 'TUTOR', 'ESTUDIANTE'];
        $missingRoles = [];
        foreach ($requiredRoles as $roleName) {
            if (! Role::where('name', $roleName)->exists()) {
                $missingRoles[] = $roleName;
            }
        }
        if (! empty($missingRoles)) {
            $this->command->error('Faltan los roles: '.implode(', ', $missingRoles).'. Ejecute primero la creación de roles.');

            return;
        }

        $this->call([
            // SchoolSeeder::class,
            DataPrincipalSeeder::class,
            // DocumentCategorySeeder::class,
            // GradeSeeder::class,
            // DepartamentSeeder::class,
            // SubjectSeeder::class,
            // DocumentTypeSeeder::class,
            // DocumentTimelineSeeder::class,
        ]);

    }
}
