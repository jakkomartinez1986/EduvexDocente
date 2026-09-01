<?php

use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\User;
use App\Services\NavigationService;
use Spatie\Permission\Models\Role;

function docenteRole(): Role
{
    return Role::firstOrCreate(
        ['name' => 'DOCENTE', 'guard_name' => 'web'],
        ['description' => 'Docente'],
    );
}

/**
 * Nombres de los enlaces del group "Docente" del menú para el usuario autenticado.
 *
 * @return array<int, string>
 */
function docenteGroupLinks(User $user): array
{
    auth()->login($user);

    $links = app(NavigationService::class)->filteredGroups()['Docente']['links'] ?? [];

    return array_map(fn (array $link) => $link['name'], $links);
}

/**
 * El menú docente (group "Docente") debe ocultar los módulos que dependen de
 * un perfil de docente y de asignaturas asignadas cuando el usuario no es
 * docente o no tiene horario en el año activo. Notificaciones (multirrol) y
 * los módulos del group "Usuarios" no se ven afectados.
 */
it('oculta los módulos docentes a un usuario sin perfil de docente', function (): void {
    $user = User::factory()->create();
    $user->assignRole(docenteRole());

    $names = docenteGroupLinks($user);

    expect($names)->not->toContain('Horario', 'Libro Calificaciones', 'Libro Asistencias', 'Registro Asistencia', 'Recuperaciones', 'Libro de Incidencias')
        ->and($names)->toContain('Notificaciones', 'Mis Estudiantes', 'Importar Representantes');
});

it('oculta los módulos docentes a un docente sin asignaturas asignadas en el año activo', function (): void {
    $context = academicContext();
    $context['teacher']->user->assignRole(docenteRole());

    ClassSchedule::where('teacher_id', $context['teacher']->id)
        ->where('year_id', $context['year']->id)
        ->delete();

    $names = docenteGroupLinks($context['teacher']->user);

    expect($names)->not->toContain('Horario', 'Libro Calificaciones', 'Libro Asistencias', 'Registro Asistencia', 'Recuperaciones', 'Libro de Incidencias')
        ->and($names)->toContain('Notificaciones');
});

it('muestra los módulos docentes a un docente con horario asignado', function (): void {
    $context = academicContext();
    $context['teacher']->user->assignRole(docenteRole());

    $names = docenteGroupLinks($context['teacher']->user);

    expect($names)->toContain('Horario', 'Libro Calificaciones', 'Libro Asistencias', 'Registro Asistencia', 'Recuperaciones', 'Libro de Incidencias', 'Notificaciones');
});
