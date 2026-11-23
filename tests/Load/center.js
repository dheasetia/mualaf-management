// 15 §15.1 performance targets, checked with k6 against staging seeded by
// LoadTestSeeder (never production):
//
//   BASE_URL=https://staging.example SESSION_COOKIE='muallaf_session=...' \
//   TENANT=LOAD CONVERT_ID=123 k6 run tests/Load/center.js
//
// SESSION_COOKIE is copied from a signed-in browser session of a Center
// Admin of the LOAD office (MFA done). Only GET requests are made.
import http from 'k6/http';
import { check, group, sleep } from 'k6';

const base = `${__ENV.BASE_URL}/center/${__ENV.TENANT || 'LOAD'}`;
const params = { headers: { Cookie: __ENV.SESSION_COOKIE }, redirects: 0 };

export const options = {
    scenarios: {
        office_staff: { executor: 'ramping-vus', stages: [{ duration: '1m', target: 20 }, { duration: '3m', target: 20 }, { duration: '30s', target: 0 }] },
    },
    thresholds: {
        'http_req_failed': ['rate<0.01'],
        'http_req_duration{page:list}': ['p(95)<500'],
        'http_req_duration{page:convert}': ['p(95)<400'],
        'http_req_duration{page:dashboard}': ['p(95)<1000'],
    },
};

function visit(path, page) {
    const response = http.get(`${base}${path}`, { ...params, tags: { page } });
    check(response, { [`${page} is 200`]: (r) => r.status === 200 });
}

export default function () {
    group('lists', () => {
        visit('/converts', 'list');
        visit('/prospects', 'list');
        visit('/follow-up-schedule', 'list');
    });
    group('convert detail', () => visit(`/converts/${__ENV.CONVERT_ID}`, 'convert'));
    group('dashboard', () => visit('/dashboard-report', 'dashboard'));
    sleep(1);
}
