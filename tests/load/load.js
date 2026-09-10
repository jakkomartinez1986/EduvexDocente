// LOAD test escalonado (#29): 20 → 30 → 40 → 50 → 75 → 100 → 125 → 150 → 175 → 200 → 250.
// Cada nivel sostiene un mínimo de ~9 min de ventana estable (p95<1s, 5xx<0.5%) antes de
// subir. No pasar al siguiente nivel si el anterior tiene errores graves.
// Ejecutar:  k6 run --env K6_BASE_URL=https://staging-... tests/load/load.js
import { teacherSession } from './lib.js';

const STAGES = [
  { duration: '9m', target: 20 },   // 20   (ventana estable para confirmar pasos)
  { duration: '9m', target: 30 },   // 30
  { duration: '9m', target: 40 },   // 40
  { duration: '9m', target: 50 },   // 50
  { duration: '9m', target: 75 },   // 75
  { duration: '9m', target: 100 },  // 100
  { duration: '9m', target: 125 },  // 125
  { duration: '9m', target: 150 },  // 150  → objetivo
  { duration: '9m', target: 175 },  // 175
  { duration: '9m', target: 200 },  // 200
  { duration: '9m', target: 250 },  // 250  → pico
  { duration: '2m', target: 0 },    // ramp down
];

export const options = {
  scenarios: {
    load: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: STAGES,
      maxVUs: 250,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.005'],
    http_req_duration: ['p(95)<1000'],
    'http_req_duration{name:gradebook}': ['p(95)<2000'],
    'http_req_duration{name:attendance/register}': ['p(95)<2000'],
    'http_req_duration{name:gradebook/quick-grades}': ['p(95)<2000'],
  },
};

export default function () {
  teacherSession(20); // 20-40 iteraciones por VU; el ramp controla el total de VUs
}