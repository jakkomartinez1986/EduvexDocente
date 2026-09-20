# TAREAS PENDIENTES

Registro vivo de trabajo pendiente para próximas sesiones. Actualizar al cierre de cada sesión (mover a "completado" lo terminado, añadir lo descubierto).

Última actualización: 2026-09-20.

---

## Recién completado (verificación al día)

- **Provisión de client_uid (task 2)**: el servidor auto-genera `client_uid` (uuid) cuando REGISTER/recoveries y el web no lo envían: `registerRecovery()` (GradeRegistrationService) y `registerExamRecovery()` (RecoveriesService) usan `client_uid ?? (string) Str::uuid()`; el web (`⚡index.blade.php` de recoveries) genera `client_uid => Str::uuid()` en cada creación; FormRequests (`StoreRecoveryRequest`, `StoreExamRecoveryRequest`) aceptan `client_uid` opcional. Tests: REST sin uid → uuid creado y uid explícito preservado (`GradebookWriteTest`, `RecoveriesTest`), web → uuid (`TeacherRecoveriesPageTest`).
- **Código muerto eliminado (task 1)**: `GradeRegistrationService::applyRecoveryOffline` y `resolveActivityRecoveryOrNull` eliminados (sin uso; el push usa `RecoveriesService::applyActivityRecoveryOffline`). Suite completa verde sin ellos.
- **Tombstones y delete en la web (task 3)**: verificado, sin cambios: `deleteBlock()`/`deleteActivity()` del gradebook web llaman `->delete()` (soft) → `GradebookTombstoneObserver` publica tombstones `assessment_block`/`activity` que el pull consume con `whereIn`.
- **Borrado offline de bloques/actividades (push)**: `activity`/`assessment_block` aceptan `delete` en sync (D-03 sigue prohibiendo crear/editar). Payload con `activity_id`/`block_id` (sin `exists` a propósito: replay → `accepted` + `echo.noop`); `GradeRegistrationService::deleteBlockOffline/deleteActivityOffline` reutilizan propiedad + cascada soft + tombstones (`GradebookTombstoneObserver`). Sin `client_uid` (no tienen columna): la identidad offline es el `id` de servidor (crear offline sigue prohibido). ConflictDetector los trata como máquina de estado (null, como recoveries). Tests en `SyncGradebookStructureTest` (éxito, no-op replay, otro docente → reject, id inexistente → no-op, D-03 inalterado).
- **Elimin/Gradebook + tombstones de sync**: `DELETE /grades/blocks/{block}` y `DELETE /grades/activities/{activity}`; observer `GradebookTombstoneObserver` (cascada soft: bloque→actividades→notas) con tombstones `assessment_block`/`activity`/`activity_grade`; pull las entrega con `whereIn`.
- **Recuperaciones idempotentes**: `client_uid` (uuid, unique) en `activity_recoveries`/`exam_recoveries`; register deduplicado con `withTrashed()`, apply/delete no-op idempotentes; habilitadas en push sync (`activity_recovery`, `exam_recovery`: register/apply/delete) con validación y ecos no-op.
- **Logos en sync**: `report_logo_url` en `institution()` y `logos` (data-URIs) en el offline package.
- **Descarga de asistencias paginada**: `GET /teachermanagement/attendances` acepta `limit` (1..1000) y `offset`; con `limit` responde `pagination {limit, offset, has_more}` (limit+1 para detectar has_more, sin COUNT). Sin `limit` el contrato previo queda intacto (`pagination: null`).
- **Novedad excluyente en resúmenes**: `data.summary.novedad` (registro) y `novedad_count` (summary por período) cuentan SOLO filas con estado `N` (presente con novedad). Un ausente con `novedad`/`novedad_type` se cuenta como falta (`I`/`J`/`AI`/`AA`) y la novedad queda como dato descriptivo (P+novedad ya se persiste como `N`). Tests: `AttendanceTest`.
- **Docblock Attendance**: `calendarday_id` nullable (`int|null`).
- Suite completa verde: 477 tests / 1760 assertions, Pint aplicado.
- Pedido en curso requerido/suspender: (vacío).

## Pendientes del scope recién terminado (bajo esfuerzo)

- (Los 3 ítems previos — código muerto, provisión de client_uid, tombstones/delete web — quedaron cerrados el 2026-09-16; ver "Recién completado".)

Pendiente operativo restante de aquel scope: **validar el flujo real del cliente** (que el móvil envíe `client_uid` por acción en REGISTER de recuperaciones) con datos reales en la siguiente sesión.

## Observaciones (sin bloquear) — revisión API attendances (2026-09-20)

Rastreo de las observaciones levantadas en la revisión funcional de la API de asistencias. Estado actual del plan:

- **[APLICADA] Paginar/limitar la descarga**: `GET /teachermanagement/attendances` ahora acepta `limit` (1..1000, default sin limit = comportamiento previo) y `offset`; con `limit` responde `pagination {limit, offset, has_more}` (detectado con `limit + 1`, sin COUNT extra). Ver `AttendanceDownloadService::paged()`.
- **[APLICADA] Aclarar el conteo de novedad en el summary**: `summary.novedad` (registro) y `novedad_count` (resumen por período) cuentan solo filas `status === 'N'` (presente con novedad); un ausente con `novedad`/`novedad_type` se cuenta como falta y no infla `novedad`. Ver `AttendanceRegistrationService::summary()` y `AttendanceSummaryService::studentRow()`.
- **[APLICADA] Docblock `calendarday_id`**: `int|null` (columna nullable). Ver `Attendance`.
- **[SIN CAMBIO — intencional] `recorded_at` no comparado en `attendanceDiffers()`**: se excluye a propósito para que un cambio de solo timestamp no rompa la idempotencia del sync/upsert.
- **[PENDIENTE DE ALCANCE — ver hallazgos offline]**: descarga sin paginación de tombstones/watermarks por entidad (ya en "Pipeline heredado") y pull incremental que no incluye sumativas ni recuperaciones (G-1, ver sección "Revisión offline del docente").

Tests que fijan lo aplicado: `AttendanceTest` (paginación con `has_more`, novedad excluyente en registro y en resumen por período).

## Revisión offline del docente (2026-09-20) — hallazgos reportados

Repaso de las APIs que el cliente docente usa para trabajar sin conexión: **asistencia, gradebook, recuperaciones, horarios, configuración escolar y calendario ya son descargables/sincronizables** (bootstrap, configuration, schedules, gradebook/download, recoveries/*, attendances/*, sync pull/push). Dependencias del flujo: `attendance.read`, `grades.read`, `schedule.read`, `configuration.read`, `sync.pull`, `sync.push`. Brechas detectadas (validar alcance antes de ejecutar):

- **Pull incremental no cubre sumativas ni recuperaciones (G-1)**: `sync/pull` entrega solo `attendance` (asistencias) y `gradebook` (notas de actividad + tombstones de bloques/actividades). Exámenes/proyectos/suplatorios y recuperaciones (`exam_recovery`/`activity_recovery`) creados por otro dispositivo o por la web NO vuelven al cliente por pull; hay que re-descargar `gradebook/download` o `recoveries/*`. Candidato a ampliar el pull (requiere esquema de entidad/watermark).
- **Lista de estudiantes por horario (roster) (G-3)**: no hay roster plano por horario; se deriva de `GET /attendances/register?schedule_id&date` (por fecha) o de los grades por bloque. Para tomar asistencia de varios días offline convendría un roster por `schedule_id` (API nueva, requiere aprobación).
- **Snapshot pull**: paginación de tombstones + watermarks por entidad (ya listado en "Pipeline heredado").

## Pipeline heredado de sesiones previas (VALIDAR antes de ejecutar)

Estos ítems vienen de resúmenes de sesiones anteriores y **no están verificados en el código actual**; revisar alcance/enfoque antes de tocar nada.

- **Ventana F-04 / licencia** (según docstring de `app/Providers/AppServiceProvider.php`): pasar del chequeo actual basado en expiración a un estado explícito tipo `Subscription`/`ExpiredLicense`; latido con dormir >3600s queda "muerto" sin última morada previa al cierre del proceso; shutdown hook de Windows que espera el cierre; fila `failed_jobs` del webhook de errores sin limpiar (¿borrados?); manejo de "flavor". — **No implementado; el grep no encuentra clases LicenseCheckedResponse/ExpiredLicense/Subscription.**
- **Sync pull – semántica GET**: mover las llamadas GET (config/contexto/institution) a emitirse *junto con* el pull dentro de un wrapper de contexto, en vez de respuestas separadas (P0-2 pendiente).
- **Snapshot pull**: paginación de tombstones + watermarks por entidad en el snapshot (hoy tombstones sin paginar).
- **Config de licencia por país**: `LICENSE_RISK_START_DATE` (ventana previa al vencimiento para el aviso). Sin referencia en código hoy.
- **Upload de adjuntos**: endpoint de subida de adjuntos (guardado/validación) pendiente de especificación.
- **Notificación programada**: job/programador de notificación según roadmap (falta especificar disparador y canal).
- **"catum vitae"**: feature listada en roadmap sin detalle definido; requiere reunión de alcance antes de implementar.

## Notas operativas (por qué correr así)

- `php artisan test` lanza un subproceso pest que **ignora `-d memory_limit`** y muere en 128M con la suite completa. Usar directamente:
  `php -d memory_limit=512M vendor/bin/pest --compact`
- Reglas que aplican a estos dominios: `.ai/rules/sync.md` (idempotencia recoveries, tombstones gradebook, watermarks locales), `.ai/rules/observers.md` (un solo `deleted(Model $model)` por observer + instanceof), `.ai/rules/tests.md` (CACHE_STORE=array; Cache::flush()), `.ai/rules/api.md` / `routes-api.md` (abilities existentes, sin ability nueva sin aprobación).