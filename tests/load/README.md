# Load Tests — EduVexDocente (k6)

Scripts reproducibles para **comprobar** la capacidad de EduVexDocente (norma: no inventar
resultados). Hasta que no se ejecuten contra un staging poblado, la capacidad del informe
(`docs/performance/`) es **estimada**.

## Requisitos

- k6 ≥ 0.49 (https://k6.io). En Windows: `choco install k6` o el binario de GitHub.
- Staging en Laravel Cloud con:
  - Drivers redis (`CACHE_STORE/SESSION_DRIVER/QUEUE_CONNECTION=redis`).
  - Seed de carga realista: 3.500 estudiantes, 250 docentes, ~9.000 matrículas,
    ~700.000 asistencias, ~315.000 activity_grades.
  - Rate limits API según `config/api.php` (login 5/min por IP; testear desde IP distinta
    o usar muchas VU → 429 posibles en login; mitigación: reusar tokens en VUs).

## Escenarios

| Script | Escenario | Usuarios | Duración | Nivel (#) |
| --- | --- | --- | --- | --- |
| `smoke.js` | humo | 5 | 10 min | #27 |
| `baseline.js` | referencia | 10 | 15 min | #28 |
| `load.js` | escalonado | 20→30→40→50→75→100→125→150→175→200→250 | sostenido c/nivel | #29 |
| `stress.js` | estrés (hasta degradación) | 150→…→300 | progresivo | #30 |
| `spike.js` | ráfaga + recuperación | 10→…→250→10 | ~12 min | #31 |

`lib.js` contiene el perfil realista de docente (#7) y los pesos (#8):
30% consultas (dashboard+notifications), 20% asistencia, 20% gradebook (pesado), 15%
calificaciones, 10% stats/sync, 5% otras. Cada VU hace login web (Fortify) + login API
(Sanctum) y opera ~1 request cada 2.5–4 s.

## Ejecución

```bash
k6 run --env K6_BASE_URL=https://staging-eduvexdocente.example.test tests/load/smoke.js
k6 run --env K6_BASE_URL=https://staging-eduvexdocente.example.test tests/load/baseline.js
k6 run --env K6_BASE_URL=https://staging-eduvexdocente.example.test tests/load/load.js
k6 run --env K6_BASE_URL=https://staging-eduvexdocente.example.test tests/load/stress.js
k6 run --env K6_BASE_URL=https://staging-eduvexdocente.example.test tests/load/spike.js
```

Variables opcionales: `K6_TEACHER_EMAIL`, `K6_TEACHER_PASSWORD`. (El `lib.js` de staging
deberá usar credenciales reales de un docente de prueba creado en el seed.)

## Métricas obligatorias por prueba (#32)

Concurrentes, requests/s, requests totales, p50/p90/p95/p99, min/max, % errores, HTTP
4xx/5xx, CPU/RAM web, CPU DB, conexiones DB, queries/s (vía `DB::listen` de muestreo),
queue length, cache hit ratio (Valkey). Resumir en una tabla por ejecución y volcar a
`docs/performance/load-testing.md` (reemplazando lo "estimado").

## Regla de parada

No subir de nivel si el anterior falla: p95 web > 1.5 s, 5xx > 0.5 % o timeouts. El punto de
degradación es el último nivel que SÍ cumplió los objetivos (#33).

## Referencia

- Plan completo y criterios: `docs/performance/load-testing.md`.
- Scripts previos originales: `docs/infrastructure/loadtest/`.