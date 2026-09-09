// SMOKE test — 5 usuarios durante 10 minutos (#27).
// Objetivo: comprobar que el sistema funciona correctamente con carga mínima.
// Ejecutar:  k6 run --env K6_BASE_URL=https://staging-... tests/load/smoke.js
import { teacherSession } from './lib.js';

export const options = {
  scenarios: {
    smoke: {
      executor: 'constant-vus',
      vus: 5,
      duration: '10m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1000'],
  },
};

export default function () {
  teacherSession(240); // ~10 min de trabajo docente por VU
}