# TAREAS PENDIENTES

Registro vivo de trabajo pendiente para próximas sesiones. Actualizar al cierre de cada sesión (mover a "completado" lo terminado, añadir lo descubierto).

Última actualización: 2026-09-16.

---

## Recién completado (verificación al día)

- **Provisión de client_uid (task 2)**: el servidor auto-genera `client_uid` (uuid) cuando REGISTER/recoveries y el web no lo envían: `registerRecovery()` (GradeRegistrationService) y `registerExamRecovery()` (RecoveriesService) usan `client_uid ?? (string) Str::uuid()`; el web (`⚡index.blade.php` de recoveries) genera `client_uid => Str::uuid()` en cada creación; FormRequests (`StoreRecoveryRequest`, `StoreExamRecoveryRequest`) aceptan `client_uid` opcional. Tests: REST sin uid → uuid creado y uid explícito preservado (`GradebookWriteTest`, `RecoveriesTest`), web → uuid (`TeacherRecoveriesPageTest`).
- **Código muerto eliminado (task 1)**: `GradeRegistrationService::applyRecoveryOffline` y `resolveActivityRecoveryOrNull` eliminados (sin uso; el push usa `RecoveriesService::applyActivityRecoveryOffline`). Suite completa verde sin ellos.
- **Tombstones y delete en la web (task 3)**: verificado, sin cambios: `deleteBlock()`/`deleteActivity()` del gradebook web llaman `->delete()` (soft) → `GradebookTombstoneObserver` publica tombstones `assessment_block`/`activity` que el pull consume con `whereIn`.
- **Borrado offline de bloques/actividades (push)**: `activity`/`assessment_block` aceptan `delete` en sync (D-03 sigue prohibiendo crear/editar). Payload con `activity_id`/`block_id` (sin `exists` a propósito: replay → `accepted` + `echo.noop`); `GradeRegistrationService::deleteBlockOffline/deleteActivityOffline` reutilizan propiedad + cascada soft + tombstones (`GradebookTombstoneObserver`). Sin `client_uid` (no tienen columna): la identidad offline es el `id` de servidor (crear offline sigue prohibido). ConflictDetector los trata como máquina de estado (null, como recoveries). Tests en `SyncGradebookStructureTest` (éxito, no-op replay, otro docente → reject, id inexistente → no-op, D-03 inalterado).
- **Elimin/Gradebook + tombstones de sync**: `DELETE /grades/blocks/{block}` y `DELETE /grades/activities/{activity}`; observer `GradebookTombstoneObserver` (cascada soft: bloque→actividades→notas) con tombstones `assessment_block`/`activity`/`activity_grade`; pull las entrega con `whereIn`.
- **Recuperaciones idempotentes**: `client_uid` (uuid, unique) en `activity_recoveries`/`exam_recoveries`; register deduplicado con `withTrashed()`, apply/delete no-op idempotentes; habilitadas en push sync (`activity_recovery`, `exam_recovery`: register/apply/delete) con validación y ecos no-op.
- **Logos en sync**: `report_logo_url` en `institution()` y `logos` (data-URIs) en el offline package.
- Suite completa verde: 469 tests / 1718 assertions, Pint aplicado. Reglas durables actualizadas en `.ai/rules/sync.md` y `.ai/rules/observers.md`.
- Pedido en curso requerido/suspender: (vacío).

## Pendientes del scope recién terminado (bajo esfuerzo)

- (Los 3 ítems previos — código muerto, provisión de client_uid, tombstones/delete web — quedaron cerrados el 2026-09-16; ver "Recién completado".)

Pendiente operativo restante de aquel scope: **validar el flujo real del cliente** (que el móvil envíe `client_uid` por acción en REGISTER de recuperaciones) con datos reales en la siguiente sesión.

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