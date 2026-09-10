// STRESS test (#30): supera progresivamente el límite hasta encontrar el punto de
// degradación (timeouts, 5xx, saturación). Ajusta el techo según el resultado del
// load test (por defecto 300, pero SIGUE subiendo mientras no haya errores graves).
// Ejecutar:  k6 run --env K6_BASE_URL=https://staging-... tests/load/stress.js
import { teacherSession } from './lib.js';

export const options = {
  scenarios: {
    stress: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '5m', target: 150 },
        { duration: '10m', target: 150 },
        { duration: '2m', target: 175 },
        { duration: '8m', target: 175 },
        { duration: '2m', target: 200 },
        { duration: '8m', target: 200 },
        { duration: '2m', target: 225 },
        { duration: '8m', target: 225 },
        { duration: '2m', target: 250 },
        { duration: '10m', target: 250 },
        { duration: '2m', target: 300 },
        { duration: '8m', target: 300 },
        { duration: '2m', target: 0 },
      ],
      maxVUs: 300,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],       // tolerancia algo mayor que load: buscamos la rodilla
    http_req_duration: ['p(95)<2000'],
  },
};

export default function () {
  teacherSession(12);
}