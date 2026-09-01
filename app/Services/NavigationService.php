<?php

namespace App\Services;

use App\Models\TeacherManagement\Academics\ClassSchedule;
use Illuminate\Support\Facades\Route;

class NavigationService
{
    /**
     * @return array<string, array{icon: string, links: array<int, array{name: string, icon: string, label: string, route: ?string, current: bool, roles: array<int, string>, badge: ?string, color: ?string}>}>
     */
    public function filteredGroups(): array
    {
        $all = $this->allGroups();

        $userRoles = auth()->check()
            ? auth()->user()->roles->pluck('name')->map(fn ($r) => strtoupper($r))->toArray()
            : [];

        $canAccessTeacherModules = $this->canAccessTeacherModules();

        $filtered = [];

        foreach ($all as $groupName => $groupData) {
            $filteredLinks = [];

            foreach ($groupData['links'] as $link) {
                if (! $link['route']) {
                    continue;
                }

                if (! isset($link['roles']) || count(array_intersect($userRoles, $link['roles'])) > 0) {
                    if ($this->isTeacherDependentLink($link) && ! $canAccessTeacherModules) {
                        continue;
                    }

                    $filteredLinks[] = $link;
                }
            }

            if (! empty($filteredLinks)) {
                $filtered[$groupName] = [
                    'icon' => $groupData['icon'],
                    'links' => $filteredLinks,
                ];
            }
        }

        return $filtered;
    }

    /**
     * Los módulos del group "Docente" que dependen de un perfil docente y de
     * asignaturas asignadas en el año activo. Si el usuario no es docente o no
     * tiene asignaturas, estos enlaces no deberían mostrarse en el menú.
     */
    private const TEACHER_DEPENDENT_NAMES = [
        'Horario',
        'Libro Calificaciones',
        'Libro Asistencias',
        'Registro Asistencia',
        'Recuperaciones',
        'Libro de Incidencias',
    ];

    /**
     * @param  array{name: string, icon: string, label: string, route: ?string, current: bool, roles: array<int, string>, badge: ?string, color: ?string}  $link
     */
    private function isTeacherDependentLink(array $link): bool
    {
        return in_array($link['name'], self::TEACHER_DEPENDENT_NAMES, true);
    }

    /**
     * Indica si el usuario autenticado está habilitado para acceder a los
     * módulos docentes, es decir, tiene un perfil de docente y al menos una
     * asignatura asignada (horario) en el año lectivo activo.
     */
    private function canAccessTeacherModules(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $teacher = auth()->user()->teacher;

        if ($teacher === null) {
            return false;
        }

        $activeYearId = app(AcademicYearService::class)->getActiveYearId();

        return $activeYearId !== null
            && ClassSchedule::where('teacher_id', $teacher->id)
                ->where('year_id', $activeYearId)
                ->exists();
    }

    /**
     * @return array<string, array{icon: string, links: array<int, array{name: string, icon: string, label: string, route: ?string, current: bool, roles: array<int, string>, badge: ?string, color: ?string}>}>
     */
    public function allGroups(): array
    {
        return [
            'Plataforma' => [
                'icon' => 'home',
                'links' => [
                    $this->link('Dashboard', 'home', 'Dashboard', $this->safeRoute('dashboard'), 'dashboard', ['SUPER-ADMIN', 'ADMIN', 'DOCENTE', 'TUTOR'], 'Dev', 'teal'),
                ],
            ],
            'Docente' => [
                'icon' => 'academic-cap',
                'links' => [
                    $this->link('Horario', 'rectangle-group', 'Horario', $this->safeRoute('admin.teacher.schedule.timeline'), 'admin.teacher.schedule.*', ['SUPER-ADMIN', 'ADMIN', 'DOCENTE']),
                    $this->link('Libro Calificaciones', 'book-open-text', 'Libro Calificaciones', $this->safeRoute('admin.summaries.gradebook.index'), 'admin.summaries.gradebook.*', ['SUPER-ADMIN', 'ADMIN', 'DOCENTE'], null, 'blue'),
                    $this->link('Libro Asistencias', 'clipboard-document-list', 'Libro Asistencias', $this->safeRoute('admin.teacher.attendance-book.index'), 'admin.teacher.attendance-book.*', ['SUPER-ADMIN', 'ADMIN', 'DOCENTE'], null, 'amber'),
                    $this->link('Registro Asistencia', 'clipboard-document-check', 'Registro Asistencia', $this->safeRoute('admin.teacher.attendance-register.index'), 'admin.teacher.attendance-register.*', ['SUPER-ADMIN', 'ADMIN', 'DOCENTE'], null, 'green'),
                    $this->link('Recuperaciones', 'arrow-path', 'Recuperaciones', $this->safeRoute('admin.teacher.recoveries.index'), 'admin.teacher.recoveries.*', ['SUPER-ADMIN', 'ADMIN', 'DOCENTE'], null, 'purple'),
                    $this->link('Mis Estudiantes', 'user-group', 'Mis Estudiantes', $this->safeRoute('system.identity.students.index'), 'system.identity.students.*', ['SUPER-ADMIN', 'ADMIN', 'DOCENTE']),
                    $this->link('Importar Representantes', 'user-group', 'Importar Representantes', $this->safeRoute('system.identity.representatives.import'), 'system.identity.representatives.*', ['SUPER-ADMIN', 'ADMIN', 'DOCENTE'], null, 'green'),
                    // $this->link('Reporte Notas', 'document-chart-bar', 'Reporte Notas', $this->safeRoute('admin.summaries.assessment-blocks.index'), 'admin.summaries.assessmet-blocks.*', ['SUPER-ADMIN', 'ADMIN', 'DOCENTE'], null, 'yellow'),
                    $this->link('Libro de Incidencias', 'exclamation-triangle', 'Libro de Incidencias', $this->safeRoute('admin.teacher.incidents.index'), 'admin.teacher.incidents.*', ['SUPER-ADMIN', 'ADMIN', 'DOCENTE'], null, 'red'),
                    $this->link('Notificaciones', 'bell', 'Notificaciones', $this->safeRoute('admin.teacher.notifications.index'), 'admin.teacher.notifications.*', ['SUPER-ADMIN', 'ADMIN', 'DOCENTE'], null, 'violet'),
                ],
            ],
            'Tutor' => [
                'icon' => 'academic-cap',
                'links' => [
                    $this->link('Horario Grado', 'calendar-date-range', 'Horario Grado', $this->safeRoute('admin.teacher.tutor-schedule.index'), 'admin.teacher.tutor-schedule.*', ['SUPER-ADMIN', 'ADMIN', 'TUTOR'], null, 'fuchsia'),
                    $this->link('Mis Estudiantes', 'user-plus', 'Mis Estudiantes', $this->safeRoute('admin.teacher.tutor-students.index'), 'admin.teacher.tutor-students.*', ['SUPER-ADMIN', 'ADMIN', 'TUTOR']),
                    $this->link('Representantes', 'user-group', 'Representantes', $this->safeRoute('admin.teacher.tutor-representatives.index'), 'admin.teacher.tutor-representatives.*', ['SUPER-ADMIN', 'ADMIN', 'TUTOR']),
                    $this->link('Libro Asistencias', 'clipboard-document-list', 'Libro Asistencias', $this->safeRoute('admin.teacher.tutor-attendance-book.index'), 'admin.teacher.tutor-attendance-book.*', ['SUPER-ADMIN', 'ADMIN', 'TUTOR'], null, 'amber'),
                    $this->link('Justificaciones', 'document-text', 'Justificaciones', $this->safeRoute('admin.teacher.tutor-justifications.index'), 'admin.teacher.tutor-justifications.*', ['SUPER-ADMIN', 'ADMIN', 'TUTOR']),
                    $this->link('Reportes de Notas', 'document-chart-bar', 'Reportes de Notas', $this->safeRoute('admin.teacher.tutor-grade-reports.index'), 'admin.teacher.tutor-grade-reports.*', ['SUPER-ADMIN', 'ADMIN', 'TUTOR'], null, 'yellow'),
                    $this->link('Incidencias de Tutoría', 'exclamation-triangle', 'Incidencias de Tutoría', $this->safeRoute('admin.teacher.tutor-incidents.index'), 'admin.teacher.tutor-incidents.*', ['SUPER-ADMIN', 'ADMIN', 'TUTOR'], null, 'red'),
                ],
            ],
            'Portal Rep.' => [
                'icon' => 'user-group',
                'links' => [
                    $this->link('Portal Representante', 'user-group', 'Portal Representante', $this->safeRoute('representante.dashboard'), 'representante.*', ['SUPER-ADMIN', 'ADMIN', 'REPRESENTANTE']),
                    $this->link('Notas', 'pencil-square', 'Notas', $this->safeRoute('representante.grades'), 'representante.grades', ['SUPER-ADMIN', 'ADMIN', 'REPRESENTANTE']),
                    $this->link('Asistencia', 'shield-check', 'Asistencia', $this->safeRoute('representante.attendance'), 'representante.attendance', ['SUPER-ADMIN', 'ADMIN', 'REPRESENTANTE']),
                    $this->link('Justificaciones', 'document-text', 'Justificaciones', $this->safeRoute('representante.justifications'), 'representante.justifications', ['SUPER-ADMIN', 'ADMIN', 'REPRESENTANTE']),
                    $this->link('Notificaciones', 'bell', 'Notificaciones', $this->safeRoute('representante.notifications'), 'representante.notifications', ['SUPER-ADMIN', 'ADMIN', 'REPRESENTANTE']),
                    $this->link('Actas', 'document-check', 'Actas Compromiso', $this->safeRoute('representante.commitment-letters'), 'representante.commitment-letters', ['SUPER-ADMIN', 'ADMIN', 'REPRESENTANTE']),
                ],
            ],
            'Inclusión Educativa' => [
                'icon' => 'academic-cap',
                'links' => [
                    $this->link('Dashboard Inclusión', 'chart-bar-square', 'Dashboard', $this->safeRoute('inclusion.dashboard'), 'inclusion.dashboard', ['SUPER-ADMIN', 'ADMIN', 'DECE', 'TUTOR', 'DOCENTE', 'RECTOR', 'VICERRECTOR']),
                    $this->link('Expedientes', 'folder', 'Expedientes', $this->safeRoute('inclusion.expedientes.index'), 'inclusion.expedientes.*', ['SUPER-ADMIN', 'ADMIN', 'DECE', 'TUTOR', 'DOCENTE', 'RECTOR', 'VICERRECTOR', 'INSPECTOR']),
                    $this->link('Configuración', 'adjustments-vertical', 'Configuración', $this->safeRoute('inclusion.configuracion.index'), 'inclusion.configuracion.*', ['SUPER-ADMIN', 'ADMIN', 'DECE']),
                ],
            ],
            'Usuarios' => [
                'icon' => 'user',
                'links' => [
                    $this->link('Usuarios', 'users', 'Usuarios', $this->safeRoute('system.identity.users.index'), 'system.identity.users.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Docentes', 'user-group', 'Docentes', $this->safeRoute('system.identity.teachers.index'), 'system.identity.teachers.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Estudiantes', 'academic-cap', 'Estudiantes', $this->safeRoute('system.identity.students.index'), 'system.identity.students.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Representantes', 'user-circle', 'Representantes', $this->safeRoute('system.identity.representatives.index'), 'system.identity.representatives.*', ['SUPER-ADMIN', 'ADMIN']),
                ],
            ],
            'Seguridad' => [
                'icon' => 'shield-check',
                'links' => [
                    $this->link('Roles', 'lock-closed', 'Roles', $this->safeRoute('admin.roles.index'), 'admin.roles.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Permisos', 'key', 'Permisos', $this->safeRoute('admin.permissions.index'), 'admin.permissions.*', ['SUPER-ADMIN', 'ADMIN']),
                ],
            ],
            'Con. Año Esc' => [
                'icon' => 'calendar',
                'links' => [
                    $this->link('Año Gestion', 'calendar-date-range', 'Año Gestion', $this->safeRoute('admin.settings.years.index'), 'admin.settings.years.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Periodos', 'bars-arrow-up', 'Periodos', $this->safeRoute('admin.settings.trimesters.index'), 'admin.settings.trimesters.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Calendario', 'calendar-days', 'Calendario Escolar', $this->safeRoute('admin.settings.calendar-scolars.index'), 'admin.settings.calendar-scolars.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Conf. Calificaciones', 'adjustments-vertical', 'Conf. Calificaciones', $this->safeRoute('admin.settings.grading-schemes.index'), 'admin.settings.grading-schemes.*', ['SUPER-ADMIN', 'ADMIN']),
                ],
            ],
            'Con. Esc' => [
                'icon' => 'building-office',
                'links' => [
                    $this->link('Colegio', 'building-library', 'Colegio', $this->safeRoute('admin.schools.index'), 'admin.schools.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Jornadas', 'archive-box', 'Jornadas', $this->safeRoute('admin.settings.shifts.index'), 'admin.settings.shifts.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Nivel', 'adjustments-vertical', 'Nivel', $this->safeRoute('admin.settings.niveles.index'), 'admin.settings.niveles.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Grado', 'academic-cap', 'Grado', $this->safeRoute('admin.settings.grades.index'), 'admin.settings.grades.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Area', 'building-office-2', 'Area', $this->safeRoute('admin.settings.areas.index'), 'admin.settings.areas.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Asignatura', 'bars-3-bottom-right', 'Asignatura', $this->safeRoute('admin.settings.subjects.index'), 'admin.settings.subjects.*', ['SUPER-ADMIN', 'ADMIN']),
                    $this->link('Canales', 'chat-bubble-left-right', 'Canales de Mensajería', $this->safeRoute('admin.settings.messaging-channels.index'), 'admin.settings.messaging-channels.*', ['SUPER-ADMIN', 'ADMIN'], null, 'teal'),
                ],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $roles
     * @return array{name: string, icon: string, label: string, route: ?string, current: bool, roles: array<int, string>, badge: ?string, color: ?string}
     */
    private function link(string $name, string $icon, string $label, ?string $route, string $current, array $roles = [], ?string $badge = null, ?string $color = null): array
    {
        return [
            'name' => $name,
            'icon' => $icon,
            'label' => __($label),
            'route' => $route,
            'current' => request()->routeIs($current),
            'roles' => $roles,
            'badge' => $badge,
            'color' => $color,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $params
     */
    private function safeRoute(string $name, array $params = []): ?string
    {
        if (Route::has($name)) {
            return route($name, $params);
        }

        return null;
    }
}
