// tests/load/scan-burst.js
//
// Roadmap Phase 2.5 — simulates the app's actual worst-case traffic
// pattern: a large group of students all checking in within a short
// window at the start of an event.
//
// Requires k6 (https://k6.io/docs/get-started/installation/) and a real
// deploy to point at — do NOT run this against production during an
// actual event; use a staging environment or an off-hours production run.
//
// Usage:
//   BASE_URL=https://your-staging-url.onrender.com \
//   OFFICER_TOKEN=<a valid Sanctum bearer token for an officer account> \
//   SESSION_ID=<an EventSession id with an open time-in window> \
//   k6 run tests/load/scan-burst.js
//
// This hits the JSON scan endpoint (App\Http\Controllers\Api\Officer\AttendanceController
// -- confirm the exact route name/path against routes/api_officer.php
// before running; adjust SCAN_PATH below if it differs).

import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:10000';
const OFFICER_TOKEN = __ENV.OFFICER_TOKEN;
const SESSION_ID = __ENV.SESSION_ID;
const SCAN_PATH = __ENV.SCAN_PATH || '/api/v1/officer/attendance/scan';

if (!OFFICER_TOKEN || !SESSION_ID) {
  throw new Error('Set OFFICER_TOKEN and SESSION_ID env vars before running.');
}

export const options = {
  scenarios: {
    event_start_burst: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '10s', target: 50 },   // scanners ramp up fast at event start
        { duration: '2m', target: 80 },    // sustained check-in window
        { duration: '10s', target: 0 },    // tail off
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],   // <1% failures
    http_req_duration: ['p(95)<800'], // 95% of scans under 800ms
  },
};

export default function () {
  // Fake token payloads -- most will be rejected server-side as invalid,
  // which is fine: this test measures throughput/latency under load, not
  // successful check-in logic. Swap in real rotating QR tokens from
  // QrTokenService if you need to test the "present" path specifically.
  const fakeToken = `loadtest-${__VU}-${__ITER}-${Date.now()}`;

  const res = http.post(
    `${BASE_URL}${SCAN_PATH}`,
    JSON.stringify({ token: fakeToken, session_id: Number(SESSION_ID) }),
    {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${OFFICER_TOKEN}`,
      },
    }
  );

  check(res, {
    'responded': (r) => r.status !== 0,
    'no 5xx': (r) => r.status < 500,
  });

  sleep(0.1);
}
