<?php

namespace Database\Seeders;

use App\Models\Security\Authorizations\Permission;
use App\Services\ModelTableService;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    protected $modelTableService;

    public function __construct(ModelTableService $modelTableService)
    {
        $this->modelTableService = $modelTableService;
    }

    public function run(): void
    {
        //

        $models = $this->modelTableService->getModelsForTables();

        $this->command->info('Creando permisos.');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach ($models as $modelo => $table) {
            $name = 'ver-'.strtolower($modelo);
            $label = 'Ver '.ucwords($modelo);
            $modulo = $modelo;
            Permission::firstOrCreate(['name' => $name, 'label' => $label, 'module' => $modulo, 'guard_name' => config('auth.defaults.guard')]);

            $name = 'crear-'.strtolower($modelo);
            $label = 'Crear '.ucwords($modelo);
            $modulo = $modelo;
            Permission::firstOrCreate(['name' => $name, 'label' => $label, 'module' => $modulo, 'guard_name' => config('auth.defaults.guard')]);

            $name = 'editar-'.strtolower($modelo);
            $label = 'Editar '.ucwords($modelo);
            $modulo = $modelo;
            Permission::firstOrCreate(['name' => $name, 'label' => $label, 'module' => $modulo, 'guard_name' => config('auth.defaults.guard')]);

            $name = 'borrar-'.strtolower($modelo);
            $label = 'Borrar '.ucwords($modelo);
            $modulo = $modelo;
            Permission::firstOrCreate(['name' => $name, 'label' => $label, 'module' => $modulo, 'guard_name' => config('auth.defaults.guard')]);

            $name = 'actualizar-estado-'.strtolower($modelo);
            $label = 'Actualizar Estado '.ucwords($modelo);
            $modulo = $modelo;
            Permission::firstOrCreate(['name' => $name, 'label' => $label, 'module' => $modulo, 'guard_name' => config('auth.defaults.guard')]);

        }

        // Operaciones sobre tabla roles
        // Permission::firstOrCreate(['name'=>'ver-rol','label'=>'Ver Rol','module'=>'Rol','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'crear-rol','label'=>'Crear Rol','module'=>'Rol','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'editar-rol','label'=>'Editar Rol','module'=>'Rol','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-rol','label'=>'Borrar Rol','module'=>'Rol','guard_name'=>config('auth.defaults.guard')]);
        // // Permission::firstOrCreate(['name'=>'restaurar-rol','label'=>'Restaurar Rol','module'=>'Rol','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-forzar-rol','label'=>'Borrar Forzar Rol','module'=>'Rol','guard_name'=>config('auth.defaults.guard')]);

        // Operaciones sobre tabla permissions
        // Permission::firstOrCreate(['name'=>'ver-permiso','label'=>'Ver Permiso','module'=>'Permiso','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'crear-permiso','label'=>'Crear Permiso','module'=>'Permiso','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'editar-permiso','label'=>'Editar Permiso','module'=>'Permiso','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-permiso','label'=>'Borrar Permiso','module'=>'Permiso','guard_name'=>config('auth.defaults.guard')]);
        // // Permission::firstOrCreate(['name'=>'restaurar-permiso','label'=>'Restaurar Permiso','module'=>'Permiso','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-forzar-permiso','label'=>'Borrar Forzar Permiso','module'=>'Permiso','guard_name'=>config('auth.defaults.guard')]);

        // Operaciones sobre tabla users
        // Permission::firstOrCreate(['name'=>'ver-usuario','label'=>'Ver Usuario','module'=>'Usuario','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'crear-usuario','label'=>'Crear Usuario','module'=>'Usuario','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'editar-usuario','label'=>'Editar Usuario','module'=>'Usuario','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-usuario','label'=>'Borrar Usuario','module'=>'Usuario','guard_name'=>config('auth.defaults.guard')]);
        // // Permission::firstOrCreate(['name'=>'restaurar-usuario','label'=>'Restaurar Usuario','module'=>'Usuario','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-forzar-usuario','label'=>'Borrar Forzar Usuario','module'=>'Usuario','guard_name'=>config('auth.defaults.guard')]);

        // Operaciones sobre tabla departamento
        // Permission::firstOrCreate(['name'=>'ver-departamento','label'=>'Ver Departamento','module'=>'Departamento','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'crear-departamento','label'=>'Crear Departamento','module'=>'Departamento','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'editar-departamento','label'=>'Editar Departamento','module'=>'Departamento','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-departamento','label'=>'Borrar Departamento','module'=>'Departamento','guard_name'=>config('auth.defaults.guard')]);
        // // Permission::firstOrCreate(['name'=>'restaurar-departamento','label'=>'Restaurar Departamento','module'=>'Departamento','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-forzar-departamento','label'=>'Borrar Forzar Departamento','module'=>'Departamento','guard_name'=>config('auth.defaults.guard')]);
        //  //Operaciones sobre tabla archivos
        // Permission::firstOrCreate(['name'=>'ver-archivo','label'=>'Ver Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'crear-archivo','label'=>'Crear Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'editar-archivo','label'=>'Editar Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-archivo','label'=>'Borrar Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);
        // // Permission::firstOrCreate(['name'=>'restaurar-archivo','label'=>'Restaurar Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-forzar-archivo','label'=>'Borrar Forzar Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);
        // //Operaciones sobre tabla subcarpeta
        // Permission::firstOrCreate(['name'=>'ver-subcarpeta','label'=>'Ver Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'crear-subcarpeta','label'=>'Crear Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'editar-subcarpeta','label'=>'Editar Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-subcarpeta','label'=>'Borrar Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'restaurar-subcarpeta','label'=>'Restaurar Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);
        // Permission::firstOrCreate(['name'=>'borrar-forzar-subcarpeta','label'=>'Borrar Forzar Archivo','module'=>'Archivo','guard_name'=>config('auth.defaults.guard')]);

    }
}
