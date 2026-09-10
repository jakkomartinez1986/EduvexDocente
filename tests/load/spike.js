// SPIKE test (#31): ráfaga 10 → 50 → 100 → 150 → 200 → 250 → 10.
// Mide recuperación y si el sistema vuelve a la normalidad tras el pico.
// Ejecutar:  k6 run --env K6_BASE_URL=https://staging-... tests/load/spike.js
import { teacherSession } from './lib.js';

export const options = {
  scenarios: {
    spike: {
      executor: 'ramping-vus',
      startVUs: 10,
      stages: [
        { duration: '10s', target: 10 },
        { duration: '10s', target: 50 },
        { duration: '1m', target: 50 },
        { duration: '10s', target: 100 },
        { duration: '1m', target: 100 },
        { duration: '10s', target: 150 },
        { duration: '1m', target: 150 },
        { duration: '10s', target: 200 },
        { duration: '1m', target: 200 },
        { duration: '10s', target: 250 },
        { duration: '1m', target: 250 },
        { duration: '30s', target: 10 },
        { duration: '5m', target: 10 },
      ],
      maxVUs: 250,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<2000'],
    http_req_duration: ['p(99)<4000'],
  },
};

export default function () {
  teacherSession(8);
}