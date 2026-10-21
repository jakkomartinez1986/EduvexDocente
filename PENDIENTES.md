# TAREAS PENDIENTES

Registro vivo de trabajo pendiente para próximas sesiones. Actualizar al cierre de cada sesión (mover a "completado" lo terminado, añadir lo descubierto).

Última actualización: 2026-10-21.

---

## Planificación a actualizaciones (2026-10-21)

Requerimiento analizado y acotado. **P-1 y P-2 implementados el 2026-10-21**; P-3 y P-4 siguen como plan para sesiones futuras. Cada bloque es independiente de los demás.

### P-1 · Super-admin único y sin DNI en el repositorio — prioridad alta

**Estado: IMPLEMENTADO (2026-10-21).** Archivos: `config/eduvex.php` (nuevo, `super_admin_dni` ← `SUPER_ADMIN_DNI`), `.env.example` (variable vacía + comentario), `app/Services/Identity/SuperAdminService.php` (nuevo: `exists()`, `first()`, `isSuperAdminDni()`, `promote()`, `isSuperAdmin()`), `app/Actions/Fortify/CreateNewUser.php` (lee la config; falla cerrado con log de warning), `app/Console/Commands/MakeSuperAdmin.php` (nuevo: `php artisan make:super-admin`), guards en `resources/views/pages/system/identity/users/⚡index.blade.php` (`toggleStatus`) y `resources/views/pages/system/identity/users/⚡edit.blade.php` (`update()` conserva el rol SUPER-ADMIN y el estado activo). Tests en `tests/Feature/Auth/SuperAdminRegistrationTest.php` (12 casos, incluido el que prohíbe el DNI en `app/` y `config/`). Nota: el `scope role()` de Spatie lanza `RoleDoesNotExist` si el rol aún no existe en la base, por eso `SuperAdminService::first()` usa `whereHas('roles', ...)` y no `User::role(...)`.

**Problema.** `app/Actions/Fortify/CreateNewUser.php:44` compara contra el DNI literal `0502987548` para crear el super-admin. Ese literal está en el commit inicial `d3d6f0c` (2026-08-21), nunca se modificó, y por lo tanto está en **las 27 ramas** del repo. El repo es **público** (`visibility: PUBLIC`), así que el DNI es legible por cualquiera. Hoy el único freno es el índice `UNIQUE` de `users.dni`: el primero que se registra con ese DNI se queda con el rol.

**Diseño.**

1. Sacar el DNI del código → `config/eduvex.php` con `'super_admin_dni' => env('SUPER_ADMIN_DNI')`. `.env.example` con `SUPER_ADMIN_DNI=` vacío + comentario; `.env` real (gitignored) con el valor; en despliegue, variable de entorno de Laravel Cloud / `.env.production`.
2. `CreateNewUser` lee `config('eduvex.super_admin_dni')`. Si la config está vacía → **no** crea super-admin y deja log de warning (fallar cerrado, nunca abierto).
3. **Unicidad**: `users.dni` ya es `UNIQUE` (`0001_01_01_000000_create_users_table.php:19`) → en la base solo puede existir una cuenta con ese DNI. Reforzar con:
   - Impedir eliminar o desactivar la cuenta del super-admin desde la UI (patrón análogo al guard de `roles/⚡index.blade.php:57-61`, que ya bloquea borrar el rol `SUPER-ADMIN`); hoy `users/⚡index.blade.php:45-52` permite desactivar a cualquiera, incluido el super-admin → dejaría libre el DNI.
   - `users/⚡edit.blade.php:41,93-98` ya excluye `SUPER-ADMIN` de los roles asignables por UI: nadie más puede autopromoverse.
   - `php artisan make:super-admin` para bootstrap/recuperación: crea o promueve la cuenta con el DNI de la config y **aborta** si ya existe un super-admin.
4. Alternativa sin ningún secreto: que el **primer** usuario registrado se convierta en super-admin (bootstrap por vacancia). Elimina el DNI del código por completo, pero pierde la garantía de que sea el `0502987548` concreto. Decidir entre (1) y esta.
5. Endurecimiento opcional: guardar en la env solo `SUPER_ADMIN_DNI_HASH` (sha256) y comparar con `hash_equals()`; evita que el DNI en claro aparezca en dumps de entorno o logs.

**Tests.** DNI ausente del código (grep sobre `app/`), config vacía → no se crea super-admin, super-admin existente → el comando aborta, no se puede desactivar al super-admin.

### P-2 · Cerrar el registro público cuando ya existe super-admin

**Estado: IMPLEMENTADO (2026-10-21).** `app/Services/Identity/SuperAdminService.php` (nuevo), `app/Http/Middleware/EnsureRegistrationIsOpen.php` (nuevo: `abort(404)`), aplicado al grupo `web` en `bootstrap/app.php` (`$middleware->web(append: [...])`) porque Fortify registra sus rutas internamente y no admite alias por ruta; el guard solo intercepta `register` y `register.store`. `resources/views/pages/auth/login.blade.php` oculta el enlace "Sign up" cuando el registro está cerrado. Sin caché: la consulta (un `EXISTS` con `whereHas`) solo corre en las rutas de registro y en el login, y así el cierre es inmediato tras crear el super-admin. Se **mantienen** los botones "Nuevo Usuario / Estudiante / Docente / Representante" (decisión de la P-2 original). Tests en `tests/Feature/Auth/SuperAdminRegistrationTest.php`.

**Diseño mínimo (3 archivos).**

| Archivo | Cambio |
| --- | --- |
| `app/Services/Identity/SuperAdminService.php` | nuevo: `exists()` = `User::role('SUPER-ADMIN')->exists()` (con cache corto) |
| `app/Http/Middleware/EnsureRegistrationIsOpen.php` | nuevo: si ya hay super-admin → `abort(404)`; aplica a `register` y `register.store` (alias en `bootstrap/app.php`) |
| `resources/views/pages/auth/login.blade.php:56` | ocultar el enlace "Sign up" cuando el registro está cerrado |

**Decisión pendiente.** ¿Se quitan también los botones "Nuevo Usuario / Estudiante / Docente / Representante" de la UI admin? **No recommended**: no existe import de usuarios genéricos (`ExcelTemplateService` solo cubre estudiantes, docentes y representantes), así que sin ellos no habría forma de crear roles de gestión (`RECTOR`, `DECE`, `INSPECTOR`, `VICERRECTOR`, que sí aparecen en `users/⚡index.blade.php:103`). Si se quitan, hay que crear antes el import de usuarios.

**Tests.** Sin super-admin → `GET /register` 200 y el enlace visible; con super-admin → 404 en `GET /register` y en `POST /register`, y el enlace ausente del login.

### P-3 · Estudiantes retirados: no editables en el gradebook, sí visibles en reportes

**Reutilizar lo que ya existe** (sin migraciones): `student_enrollments.status` (string, default `active`) para el estado, `completion_date` para la fecha de retiro, `notes` para el motivo de la novedad.

| Archivo | Cambio |
| --- | --- |
| `resources/views/pages/system/identity/students/⚡index.blade.php` | nueva acción "Retirar" (motivo + fecha) → `status='retired'`, `completion_date`, motivo en `notes`. **Distinta** del toggle actual de `users.status` (`:259-271`), que solo bloquea el login |
| `resources/views/pages/system/teachers-management/teachers/gradebook/⚡index.blade.php:292-301` | `getStudents()`: agregar `->where('status','active')` → sale de la entrada de notas (1 línea) |
| `app/Services/Api/V1/Academic/GradebookService.php:190-198` | mismo filtro en `enrolledStudentIds()` (1 línea) |
| `resources/views/pdf/gradebook-summative.blade.php:185-187` | la fila roja ya existe (`background-color:#fecaca`); agregar la condición "retirado" + etiqueta "RETIRADO" (~3 líneas) |
| `app/Services/Academic/GradeCalculationService.php:31-46` | si el retirado no tiene ninguna nota en el bloque → `null` en vez de `0.00` (1 línea) |

**Punto crítico.** El filtro va en `getStudents()` (`:292-301`), **nunca** en `getStudentIds()` (`:199-208`), que alimenta los agregados: si se filtra antes, el retirado saldría "Sin datos" en los reportes, que es justo lo contrario de lo pedido. El filtro también **no** aplica a `GradebookPdfService::tutorAllTri` (`:932-934`), que hoy es el único que excluye a los no activos y contradice el requerimiento.

**Decisiones pendientes.**

1. ¿El retiro es **de la matrícula** (se va del grado este año → `student_enrollments.status`) o **del estudiante** (abandona la institución → `students`/`users`)? Se recomienda matrícula.
2. ¿Motivo de la novedad con catálogo propio (Retirado / Trasladado / Egresado / …) o el texto libre en `notes`?
3. ¿Fila roja en cuáles de los 11 PDF? Propuesta: empezar por el sumativo y replicar en formative, qualitative, subjectAnnual y tutorAllTri.
4. ¿El promedio del retirado se corta por fecha (`completion_date`) o se muestra tal cual?
5. Al retirar, ¿se desactiva también la cuenta (`users.status = 0`)?

**Aplazado (no bloquea).** Invalidación de caché de PDF (`PdfReportCacheObserver::invalidate()` en `:55-74` no matchea `StudentEnrollment` → cambiar el status no regenera los PDF; hasta 30 min por el TTL de `AsyncReportDispatcher::ready()`). Rechazo de sincronización offline de notas de retirados. Índice compuesto `(grade_id, year_id, status)` en `student_enrollments`. Decidir si el carnet (`CarnetPdfService:49-51`) debe seguir emitiéndose para un retirado.

### P-4 · Saneamiento del historial Git (eliminar el DNI de todas las ramas)

**Cuándo.** El literal está en `d3d6f0c` y en las 27 ramas. Hay dos cosas distintas y **las dos hacen falta**:

1. **Rotar el DNI (obligatorio, 15 min).** Moverlo a la env (P-1) y cambiar el valor por uno nuevo. El DNI viejo queda en la historia pero deja de ser válido: para el repositorio público, un DNI filtrado hay que tratarlo como comprometido.
2. **Reescribir la historia (higiene, ~1-2 h).** Solo si el DNI viejo debe dejar de ser recuperable desde el repo.

**Procedimiento para (2).**

```bash
pip install git-filter-repo
git clone --mirror https://github.com/jakkomartinez1986/EduvexDocente.git EduvexDocente-clean.git
cd EduvexDocente-clean.git
printf 'literal:0502987548==>***DNI_REMOVED***\n' > replacements.txt
git filter-repo --replace-text replacements.txt --force        # reescribe TODOS los refs
git log --all -S "0502987548" --oneline                        # debe salir vacío
git grep -F "0502987548" $(git rev-list --all)                 # debe salir vacío
git push --force --mirror origin
```

**Riesgos y Checklist.**

- [ ] **Reescribir SIEMPRE en un clon `--mirror`**, nunca en el clon de trabajo (el filtro borra el working tree).
- [ ] **Los PRs abiertos se rompen**: #18 (dependabot), #19 y #20 (los de este trabajo). Cerrarlos y reabrir después del rewrite.
- [ ] **Los PRs ya fusionados (#1-#17) quedan con commits huérfanos**; las páginas siguen visibles pero el commit puede aparecer como "no disponible".
- [ ] **Todos los clones del equipo se invalidan**: re-clonar, o `git fetch origin --prune --force` + `git reset --hard origin/<rama>` + borrar las ramas locales antiguas.
- [ ] **GitHub conserva los objetos viejos** (accesibles por SHA). Para purga real: borrar los refs cacheados con la API y **pedir a soporte de GitHub la recolección de garbage** del repo. Sin eso, el DNI sigue siendo recuperable por URL directa al commit viejo.
- [ ] **Los forks** conservan una copia para siempre: hay que avisar/contar con los owners de los forks (p. ej. el fork de dependabot).
- [ ] Coordinar con el CI: el rewrite dispara de nuevo los workflows `tests` sobre los SHAs nuevos.
- [ ] **Orden recomendado**: aplicar primero el cambio de código (P-1) en una rama, luego el rewrite, y solo después volver a abrir los PRs.

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