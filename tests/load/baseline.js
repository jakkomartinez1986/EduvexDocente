// BASELINE test — 10 usuarios durante 15 minutos (#28).
// Mide p50/p95/p99, requests/s, errores y estabilidad de referencia.
// Ejecutar:  k6 run --env K6_BASE_URL=https://staging-... tests/load/baseline.js
import { teacherSession } from './lib.js';

export const options = {
  scenarios: {
    baseline: {
      executor: 'constant-vus',
      vus: 10,
      duration: '15m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.005'],
    http_req_duration: ['p(95)<1000', 'p(99)<2000'],
    'http_req_duration{name:gradebook}': ['p(95)<2000'],
  },
};

export default function () {
  teacherSession(360); // ~15 min de trabajo docente por VU
}