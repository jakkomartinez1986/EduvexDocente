<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Sync;

use App\Models\Academic\GradeBook\Summaries\Subjects\Activity;
use App\Models\Academic\GradeBook\Summaries\Subjects\ActivityGrade;
use App\Models\Academic\GradeBook\Summaries\Subjects\AssessmentBlock;
use App\Models\Identity\Users\Teacher;
use App\Models\Sync\SyncTombstone;
use App\Models\TeacherManagement\Academics\ClassSchedule;
use App\Models\TeacherManagement\Attendances\Attendance;
use App\Services\Api\V1\Academic\GradeRegistrationService;
use App\Services\Api\V1\TeacherManagement\AttendanceRegistrationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Motor de sincronización offline-first (API_ROADMAP §7, synchronization.md).
 *
 * Push: cada operación se aplica delegando en los mismos servicios que los
 * endpoints REST (H-09), en SU propia transacción, con éxito parcial por
 * diseño. Los avisos y conflictos §6 se detectan SIEMPRE antes de aplicar,
 * contra el estado previo del servidor (`base_updated_at`).
 * Pull: colecciones incrementales desde un cursor opaco (watermark).
 */
final class SyncService
{
    /**
     * Catálogo MVP de entidades aceptadas (D-03: crear/editar actividades o
     * bloques desde sync queda prohibido).
     *
     * @var array<string, string>
     */
    private const SUPPORTED_OPERATIONS = [
        'attendance_day' => 'replace_day',
        'activity_grade' => 'upsert_batch',
        'summative_grades' => 'upsert_batch',
        'supplementary_grades' => 'upsert_batch',
    ];

    /**
     * Página máxima por colección en cada pull (§sync: evita respuestas
     * ilimitadas cuando un docente tiene mucha actividad entre syncs).
     */
    private const DEFAULT_PULL_LIMIT = 500;

    public function __construct(
        private readonly AttendanceRegistrationService $attendanceRegistrationService,
        private readonly GradeRegistrationService $gradeRegistrationService,
        private readonly ConflictDetector $conflictDetector,
    ) {}

    /**
     * Aplica un lote de operaciones del outbox del cliente.
     *
     * @param  array<int, array<string, mixed>>  $operations
     * @return array<string, mixed>
     */
    public function push(Teacher $teacher, string $deviceId, array $operations): array
    {
        $startedAt = microtime(true);

        $this->syncLog()->info('sync.push', [
            'teacher_id' => $teacher->id,
            'device_id' => $deviceId,
            'operations' => count($operations),
        ]);

        $results = [];

        foreach ($operations as $operation) {
            $results[] = $this->pushOperation($teacher, $deviceId, $operation);
        }

        $counts = ['accepted' => 0, 'rejected' => 0, 'conflict' => 0, 'forced' => 0];

        foreach ($results as $result) {
            $counts[$result['status']]++;
            $counts['forced'] += isset($result['forced']) ? 1 : 0;
        }

        // Resumen estructurado del lote para monitoreo (Fase 11): alertar
        // sobre tasas anómalas de rejected/conflict por dispositivo.
        $this->syncLog()->info('sync.push.batch', [
            'teacher_id' => $teacher->id,
            'device_id' => $deviceId,
            'total' => count($results),
            ...$counts,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return [
            'results' => $results,
            // Índice rápido para la UI de revisión §7.6: solo las operaciones
            // en conflicto (vacío cuando no hubo ninguna).
            'conflicts' => array_values(array_filter(
                $results,
                fn (array $result): bool => ($result['status'] ?? null) === 'conflict'
            )),
            'server_time' => now()->toISOString(),
            'next_steps' => ['pull_recommended' => true],
        ];
    }

    /**
     * Pull incremental paginado: cambios por colección desde el watermark del
     * cursor. Las colecciones que superan `$limit` marcan `has_more` y NO
     * avanzan su watermark, de modo que la siguiente petición con el mismo
     * cursor re-entrega la misma ventana (el merge local es idempotente).
     *
     * @param  array<int, string>  $collections
     * @return array<string, mixed>
     */
    public function pull(Teacher $teacher, array $collections, ?string $cursor, int $limit = self::DEFAULT_PULL_LIMIT): array
    {
        $startedAt = microtime(true);

        $since = $this->decodeCursor($cursor);
        $boundary = Carbon::now();

        $changes = [];
        $hasMore = [];

        foreach ($collections as $collection) {
            [$collectionChanges, $collectionHasMore] = $collection === 'attendance'
                ? $this->attendanceChanges($teacher, $since['attendance'], $boundary, $limit)
                : $this->gradebookChanges($teacher, $since['gradebook'], $boundary, $limit);

            $changes[$collection] = $collectionChanges;
            $hasMore[$collection] = $collectionHasMore;
        }

        // Trazabilidad de pull para monitoreo (Fase 11): volumen entregado
        // por colección y duración, sin volcar payloads.
        $this->syncLog()->info('sync.pull', [
            'teacher_id' => $teacher->id,
            'collections' => $collections,
            'limit' => $limit,
            'has_more' => array_keys(array_filter($hasMore)),
            'upserts' => collect($changes)->sum(fn (array $c): int => count($c['upserts'])),
            'tombstones' => collect($changes)->sum(fn (array $c): int => count($c['tombstones'])),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        // Solo avanzan los watermarks de las colecciones entregadas completas.
        $boundaries = [];

        foreach ($collections as $collection) {
            $key = $collection === 'attendance' ? 'attendance' : 'gradebook';
            $boundaries[$key] = $hasMore[$collection] ? $since[$key] : $boundary;
        }

        return [
            'changes' => $changes,
            'cursor' => $this->encodeCursor($boundaries),
            'has_more' => $hasMore,
            'server_time' => $boundary->toISOString(),
        ];
    }

    /**
     * Canal de logging dedicado a sincronización (config/logging.php → sync).
     */
    private function syncLog(): LoggerInterface
    {
        return Log::channel('sync');
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function pushOperation(Teacher $teacher, string $deviceId, array $operation): array
    {
        $operationId = (string) ($operation['operation_id'] ?? '');
        $entity = (string) ($operation['entity'] ?? '');
        $action = (string) ($operation['action'] ?? '');
        $force = (bool) ($operation['force'] ?? false);

        try {
            if ((self::SUPPORTED_OPERATIONS[$entity] ?? null) !== $action) {
                throw ValidationException::withMessages([
                    'entity' => "Operación no soportada: {$entity}/{$action}.",
                ]);
            }

            $payload = $this->validatePayload($entity, (array) ($operation['payload'] ?? []));

            // Detección §7.6 contra el estado PREVIO del servidor.
            $detection = $this->conflictDetector->detect($teacher, $entity, $payload);

            if ($detection?->isConflict()) {
                if (! $force) {
                    return [
                        'operation_id' => $operationId,
                        'entity' => $entity,
                        'action' => $action,
                        'status' => 'conflict',
                        'server_record' => $detection->serverRecord,
                        'resolution_hint' => 'server_newer',
                    ];
                }

                // Override explícito del docente (§7.6): se aplica la snapshot
                // entrante y la decisión queda auditada. Las reglas duras del
                // sistema (p.ej. ventana de calificación cerrada) NO tienen
                // override: siguen fallando dentro de applyOperation.
                Log::channel('sync')->warning('sync.push.forced_override', [
                    'teacher_id' => $teacher->id,
                    'device_id' => $deviceId,
                    'operation_id' => $operationId,
                    'entity' => $entity,
                    'server_record' => $detection->serverRecord,
                ]);
            }

            $echo = $this->applyOperation($teacher, $entity, $payload);

            return [
                'operation_id' => $operationId,
                'entity' => $entity,
                'action' => $action,
                'status' => 'accepted',
                'echo' => $echo,
                ...($force ? ['forced' => true] : []),
                ...($detection?->isSameAuthorNewer() && ! $force
                    ? ['notice' => 'overwritten_by_newer_same_author']
                    : []),
            ];
        } catch (ValidationException $exception) {
            return [
                'operation_id' => $operationId,
                'status' => 'rejected',
                'errors' => $exception->errors(),
            ];
        } catch (HttpExceptionInterface $exception) {
            return [
                'operation_id' => $operationId,
                'status' => 'rejected',
                'errors' => ['entity' => $exception->getMessage()],
            ];
        } catch (Throwable $exception) {
            Log::channel('sync')->error('sync.push.operation_failed', [
                'operation_id' => $operationId,
                'teacher_id' => $teacher->id,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'operation_id' => $operationId,
                'status' => 'rejected',
                'errors' => ['server' => 'La operación no pudo aplicarse.'],
            ];
        }
    }

    /**
     * Aplica la operación delegando en los servicios REST equivalentes.
     * Cada operación corre en su propia transacción (éxito parcial por diseño).
     *
     * Precondición: `base_updated_at` sigue presente para los echo/aviso; se
     * elimina aquí dentro antes de delegar (los servicios REST no lo aceptan).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyOperation(Teacher $teacher, string $entity, array $payload): array
    {
        unset($payload['base_updated_at']);

        return DB::transaction(function () use ($teacher, $entity, $payload): array {
            if ($entity === 'attendance_day') {
                $detail = $this->attendanceRegistrationService->register($teacher, $payload);

                return [
                    'schedule_id' => (int) $payload['schedule_id'],
                    'date' => (string) $payload['date'],
                    'summary' => $detail['summary'],
                ];
            }

            if ($entity === 'activity_grade') {
                $activity = Activity::findOrFail((int) $payload['activity_id']);

                return ['updated' => $this->gradeRegistrationService->storeActivityGrades($teacher, $activity, (array) $payload['grades'])];
            }

            if ($entity === 'summative_grades') {
                return ['updated' => $this->gradeRegistrationService->storeSummative(
                    $teacher,
                    $payload['type'] === 'project' ? 'project' : 'exam',
                    $payload,
                )];
            }

            return ['updated' => $this->gradeRegistrationService->storeSupplementary($teacher, $payload)];
        });
    }

    /**
     * Valida el payload contra las mismas reglas del endpoint REST
     * equivalente (+ `base_updated_at` opcional para conflictos §6).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatePayload(string $entity, array $payload): array
    {
        $rules = match ($entity) {
            'attendance_day' => [
                'schedule_id' => ['required', 'integer', 'exists:class_schedules,id'],
                'date' => ['required', 'date'],
                'classtopic' => ['required', 'string', 'max:255'],
                'observation' => ['nullable', 'string', 'max:1000'],
                'statuses' => ['required', 'array', 'min:1'],
                'statuses.*' => ['required', 'string', 'in:P,A,I,J,AI,AA'],
                'observations' => ['nullable', 'array'],
                'observations.*' => ['nullable', 'string', 'max:1000'],
            ],
            'activity_grade' => [
                'activity_id' => ['required', 'integer', 'exists:activities,id'],
                'grades' => ['required', 'array', 'min:1'],
                'grades.*.student_id' => ['required', 'integer', 'distinct'],
                'grades.*.grade' => ['nullable', 'numeric', 'min:0', 'max:10'],
            ],
            'summative_grades' => [
                'type' => ['required', 'string', 'in:exam,project'],
                'year_id' => ['nullable', 'integer', 'exists:scolar_years,id'],
                'subject_id' => ['required', 'integer', 'exists:subjects,id'],
                'grade_id' => ['required', 'integer', 'exists:grades,id'],
                'trimester_id' => ['required', 'integer', 'exists:academic_periods,id'],
                'grades' => ['required', 'array', 'min:1'],
                'grades.*.student_id' => ['required', 'integer', 'distinct'],
                'grades.*.grade' => ['nullable', 'numeric', 'min:0', 'max:10'],
            ],
            default => [
                'year_id' => ['nullable', 'integer', 'exists:scolar_years,id'],
                'subject_id' => ['required', 'integer', 'exists:subjects,id'],
                'grade_id' => ['required', 'integer', 'exists:grades,id'],
                'grades' => ['required', 'array', 'min:1'],
                'grades.*.student_id' => ['required', 'integer', 'distinct'],
                'grades.*.grade' => ['nullable', 'numeric', 'min:0', 'max:10'],
            ],
        };

        $rules['base_updated_at'] = ['sometimes', 'nullable', 'date'];

        return Validator::make($payload, $rules)->validate();
    }

    /**
     * Upserts + tombstones de la colección attendance en la ventana dada.
     *
     * @return array{array{upserts: array<int, array<string, mixed>>, tombstones: array<int, array<string, int|string>>}, bool}
     */
    private function attendanceChanges(Teacher $teacher, Carbon $since, Carbon $boundary, int $limit): array
    {
        $ownScheduleIds = ClassSchedule::query()
            ->where('teacher_id', $teacher->id)
            ->select('id');

        $query = Attendance::query()
            ->whereIn('class_schedule_id', $ownScheduleIds)
            ->whereNull('deleted_at')
            ->whereBetween('updated_at', [$since, $boundary])
            ->orderBy('updated_at')
            ->orderBy('id');

        $rows = $query->limit($limit + 1)->get();
        $hasMore = count($rows) > $limit;

        $upserts = $rows
            ->take($limit)
            ->map(fn (Attendance $attendance): array => [
                'id' => $attendance->id,
                'schedule_id' => $attendance->class_schedule_id,
                'student_id' => $attendance->student_id,
                'date' => Carbon::parse((string) $attendance->date)->toDateString(),
                'status' => $attendance->status,
                'observation' => $attendance->observation,
                'arrival_time' => $attendance->arrival_time?->format('H:i'),
                'updated_at' => $attendance->updated_at?->toISOString(),
            ])
            ->all();

        return [
            [
                'upserts' => $upserts,
                'tombstones' => $this->tombstones($teacher, 'attendance', $since, $boundary),
            ],
            $hasMore,
        ];
    }

    /**
     * @return array{array{upserts: array<int, array<string, mixed>>, tombstones: array<int, array<string, int|string>>}, bool}
     */
    private function gradebookChanges(Teacher $teacher, Carbon $since, Carbon $boundary, int $limit): array
    {
        $ownActivityIds = Activity::query()
            ->whereIn('assessment_block_id', AssessmentBlock::query()->where('teacher_id', $teacher->id)->select('id'))
            ->select('id');

        $query = ActivityGrade::query()
            ->whereIn('activity_id', $ownActivityIds)
            ->whereNull('deleted_at')
            ->whereBetween('updated_at', [$since, $boundary])
            ->orderBy('updated_at')
            ->orderBy('id');

        $rows = $query->limit($limit + 1)->get();
        $hasMore = count($rows) > $limit;

        $upserts = $rows
            ->take($limit)
            ->map(fn (ActivityGrade $grade): array => [
                'activity_id' => $grade->activity_id,
                'student_id' => $grade->student_id,
                'grade' => $grade->grade !== null ? (float) $grade->grade : null,
                'updated_at' => $grade->updated_at?->toISOString(),
            ])
            ->all();

        return [
            [
                'upserts' => $upserts,
                'tombstones' => $this->tombstones($teacher, 'activity_grade', $since, $boundary),
            ],
            $hasMore,
        ];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function tombstones(Teacher $teacher, string $entity, Carbon $since, Carbon $boundary): array
    {
        return SyncTombstone::query()
            ->where('entity', $entity)
            ->where('owner_user_id', $teacher->user_id)
            ->whereBetween('deleted_at', [$since, $boundary])
            ->orderBy('deleted_at')
            ->get()
            ->map(fn (SyncTombstone $tombstone): array => [
                'entity' => $tombstone->entity,
                'id' => $tombstone->entity_id,
            ])
            ->all();
    }

    /**
     * Cursor opaco: base64(JSON {v:1, <colección>: Y-m-d H:i:s}).
     *
     * Los watermarks viajan en la MISMA pared de tiempo que la columna
     * `updated_at` de la BD (app.timezone), sin conversión de zona: comparar
     * contra ISO/UTC desplazaría las ventanas cuando app.timezone ≠ UTC.
     *
     * @return array<string, Carbon>
     */
    private function decodeCursor(?string $cursor): array
    {
        $epoch = Carbon::parse('1970-01-01 00:00:00');

        if ($cursor === null || trim($cursor) === '') {
            return ['attendance' => $epoch, 'gradebook' => $epoch];
        }

        $decoded = json_decode((string) base64_decode($cursor, true), true);

        if (! is_array($decoded) || ($decoded['v'] ?? null) !== 1) {
            throw ValidationException::withMessages([
                'cursor' => 'El cursor de sincronización no es válido.',
            ]);
        }

        return [
            'attendance' => isset($decoded['attendance']) ? Carbon::parse((string) $decoded['attendance']) : $epoch,
            'gradebook' => isset($decoded['gradebook']) ? Carbon::parse((string) $decoded['gradebook']) : $epoch,
        ];
    }

    /**
     * @param  array<string, Carbon>  $boundaries
     */
    private function encodeCursor(array $boundaries): string
    {
        $payload = ['v' => 1];

        foreach ($boundaries as $collection => $boundary) {
            $payload[$collection] = $boundary->format('Y-m-d H:i:s');
        }

        return base64_encode((string) json_encode($payload));
    }
}
