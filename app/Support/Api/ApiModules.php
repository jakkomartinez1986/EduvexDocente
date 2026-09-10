<?php

declare(strict_types=1);

namespace App\Support\Api;

/**
 * Fuente única de verdad para los módulos del cliente API v1.
 *
 * - PERMISSION_MODELS: modelos cuyos permisos Spatie (columna `module`)
 *   habilitan cada módulo. Consumido por ConfigurationService (reporte
 *   `modules.*`) y por TokenAbilityService (abilities del token).
 * - BASE_ABILITIES / ABILITIES_PER_MODULE: abilities mínimas de todo token
 *   y las que se añaden cuando el usuario tiene permisos del módulo.
 */
final class ApiModules
{
    /**
     * @var array<string, array<int, string>>
     */
    public const PERMISSION_MODELS = [
        'schedule' => ['ClassSchedule'],
        'attendance' => ['Attendance', 'AttendanceSummary', 'ClassObservation'],
        'grades' => [
            'AssessmentBlock', 'Activity', 'ActivityGrade', 'ActivityRecovery',
            'StudentExam', 'StudentProject', 'SupplementaryExam',
            'AcademicReinforcement', 'GraduationExam',
        ],
    ];

    /**
     * @var array<int, string>
     */
    public const BASE_ABILITIES = [
        'auth.me',
        'auth.logout',
        'configuration.read',
    ];

    /**
     * Abilities que se añaden cuando el usuario tiene permisos de CUALQUIER
     * módulo (p. ej. el dataset de estudiantes alimenta a notas y asistencia;
     * el motor de sync opera sobre cualquier combinación de ellos).
     *
     * @var array<int, string>
     */
    public const CROSS_MODULE_ABILITIES = [
        'students.read',
        'sync.pull',
        'sync.push',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    public const ABILITIES_PER_MODULE = [
        'schedule' => ['schedule.read', 'schedule.write'],
        'attendance' => ['attendance.read', 'attendance.write'],
        'grades' => ['grades.read', 'grades.write'],
    ];
}
