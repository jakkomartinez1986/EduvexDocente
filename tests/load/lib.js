// Shared helpers for EduVexDocente load tests (k6).
// Usage: k6 run --env K6_BASE_URL=https://staging-... tests/load/<scenario>.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';

export const BASE = __ENV.K6_BASE_URL || 'https://staging-eduvexdocente.example.test';
const TEACHER_EMAIL = __ENV.K6_TEACHER_EMAIL || 'docente@test.eduvexd';
const TEACHER_PASSWORD = __ENV.K6_TEACHER_PASSWORD || 'secret';

// Datos compartidos (per-VU se consigue una sesión indep.)
export const COURSES = new SharedArray('courses', () => Array.from({ length: 250 }, (_, i) => i + 1));
export const STUDENTS = new SharedArray('students', () => Array.from({ length: 40 }, (_, i) => i + 1));
const YEAR_IDS = new SharedArray('years', () => [1, 2, 3, 4]);
const GRADE_IDS = new SharedArray('grades', () => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
export const ACTIVITY_IDS = new SharedArray('activities', () => Array.from({ length: 5000 }, (_, i) => i + 1));

function random(collection) {
  return collection[Math.floor(Math.random() * collection.length)];
}

function randomValue(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

function pickJson(res) {
  try {
    return res.json();
  } catch (e) {
    return {};
  }
}

// ---- Autenticación ----
export function loginWeb() {
  const jar = http.cookieJar();
  jar.clear(BASE);
  const res = http.get(`${BASE}/login`, { jar });
  const csrf = (res.body || '').match(/name="csrf-token" content="([^"]+)"/);
  const token = csrf ? csrf[1] : 'x';
  return http.post(
    `${BASE}/login`,
    { email: TEACHER_EMAIL, password: TEACHER_PASSWORD, _token: token },
    { jar, redirects: 0 },
  );
}

export function loginApi() {
  const res = http.post(
    `${BASE}/api/v1/auth/login`,
    JSON.stringify({ email: TEACHER_EMAIL, password: TEACHER_PASSWORD }),
    { headers: { 'Content-Type': 'application/json' } },
  );
  const body = pickJson(res);
  return body && body.data && body.data.token ? body.data.token : null;
}

function apiHeaders(token, json = true) {
  return {
    Authorization: `Bearer ${token}`,
    Accept: 'application/json',
    ...(json ? { 'Content-Type': 'application/json' } : {}),
  };
}

// ---- Operaciones (perfil realista de docente) ----
export function dashboardPage(jar) {
  return http.get(`${BASE}/teachers/dashboard`, { jar, tags: { name: 'dashboard' } });
}

export function gradebookPage(jar) {
  const course = random(COURSES);
  const res = http.get(`${BASE}/teachers/gradebook/${course}`, { jar, tags: { name: 'gradebook' } });
  check(res, { 'gradebook 200': (r) => r.status === 200 });
  return res;
}

export function notificationsPage(jar) {
  const res = http.get(`${BASE}/teachers/notifications`, { jar, tags: { name: 'notifications' } });
  check(res, { 'notifications 200': (r) => r.status === 200 });
  return res;
}

export function registerAttendance(token) {
  const payload = {
    class_schedule_id: random(COURSES),
    academic_year_id: random(YEAR_IDS),
    date: '2026-09-15',
    records: STUDENTS.map((id, i) => ({ student_id: id, status: i % 7 === 0 ? 'late' : 'present' })),
  };
  const res = http.post(`${BASE}/api/v1/attendance-day/register`, JSON.stringify(payload), {
    headers: apiHeaders(token),
    tags: { name: 'attendance/register' },
  });
  check(res, { 'attendance reg 2xx': (r) => r.status >= 200 && r.status < 300 });
  return res;
}

export function quickGrades(token) {
  const payload = {
    class_schedule_id: random(COURSES),
    activity_id: random(ACTIVITY_IDS),
    grades: STUDENTS.map((id) => ({ student_id: id, grade: randomValue(7, 10) })),
  };
  const res = http.post(`${BASE}/api/v1/gradebook/quick-grades`, JSON.stringify(payload), {
    headers: apiHeaders(token),
    tags: { name: 'gradebook/quick-grades' },
  });
  check(res, { 'quick-grades 2xx': (r) => r.status >= 200 && r.status < 300 });
  return res;
}

export function syncPull(token) {
  const cursor = Buffer.from('attendance:1:100').toString('base64');
  const res = http.get(`${BASE}/api/v1/sync/pull?cursor=${encodeURIComponent(cursor)}`, {
    headers: apiHeaders(token, false),
    tags: { name: 'sync/pull' },
  });
  check(res, { 'sync/pull 2xx': (r) => r.status >= 200 && r.status < 300 });
  return res;
}

export function settingsConfig(token) {
  const res = http.get(`${BASE}/api/v1/settings/config`, {
    headers: apiHeaders(token, false),
    tags: { name: 'settings/config' },
  });
  check(res, { 'settings/config 2xx': (r) => r.status >= 200 && r.status < 300 });
  return res;
}

export function settingsGrade(token) {
  const year = random(YEAR_IDS);
  const grade = random(GRADE_IDS);
  const res = http.get(`${BASE}/api/v1/settings/${year}/${grade}`, {
    headers: apiHeaders(token, false),
    tags: { name: 'settings/grade' },
  });
  check(res, { 'settings grade 2xx': (r) => r.status >= 200 && r.status < 300 });
  return res;
}

// Perfil de carga documentado (#8): 30% consultas, 20% asistencia, 20% actividades/
// gradebook, 15% calificaciones, 10% estadísticas/sync, 5% otras.
export function pickOperation() {
  const r = Math.random();
  if (r < 0.20) return 'dashboard';       // consultas ligeras
  if (r < 0.35) return 'gradebook';       // lecturas pesadas (gradebook)
  if (r < 0.50) return 'notifications';   // consultas (notificaciones)
  if (r < 0.65) return 'attendance';      // asistencia (escritura batch)
  if (r < 0.80) return 'quickgrades';     // calificaciones (escritura batch)
  if (r < 0.90) return 'syncpull';        // sync / estadísticas
  if (r < 0.95) return 'settingsconfig';  // configuración
  return 'settingsgrade';                 // otras
}

export function simulateTeacher(token, jar) {
  switch (pickOperation()) {
    case 'dashboard': return dashboardPage(jar);
    case 'gradebook': return gradebookPage(jar);
    case 'notifications': return notificationsPage(jar);
    case 'attendance': return registerAttendance(token);
    case 'quickgrades': return quickGrades(token);
    case 'syncpull': return syncPull(token);
    case 'settingsconfig': return settingsConfig(token);
    default: return settingsGrade(token);
  }
}

// Sesión completa de VU: login web (cookie) + login API (token), luego N operaciones.
export function teacherSession(iterations = 400) {
  const jar = http.cookieJar();
  const webLogin = loginWeb();
  check(webLogin, { 'web login ok': (r) => r.status < 400 });
  const token = loginApi();
  check(token, { 'api login ok': (t) => t !== null });
  for (let i = 0; i < iterations; i++) {
    simulateTeacher(token, jar);
    sleep(1 + Math.random() * 3); // ~1 request cada 2.5–4 s; media ~1/2.5 s
  }
  return { token, jar };
}

export { YEAR_IDS, GRADE_IDS, apiHeaders, randomValue, random };