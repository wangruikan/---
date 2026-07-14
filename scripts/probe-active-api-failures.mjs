import fs from 'node:fs';
import path from 'node:path';
import { request as playwrightRequest } from '@playwright/test';

const root = process.cwd();
const baseURL = `${(process.env.API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '')}/`;
const accountSetId = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const reportDir = path.join(root, 'storage', 'api-test-reports', 'read-contract');
const routes = [
  'attendance/999999999/export',
  'employees/999999999/documents',
  'employees/999999999/documents/999999999/download',
  'employees/999999999/change-history',
  'employees/999999999/large-medical-status',
  'employees/999999999/onboarding-form',
  'employees/999999999/registration-form',
  'employees/999999999/registration-form-update-status',
  'employees/999999999/view-details',
  'employees/contracts/999999999/download',
  'employees/resignation-certificates/999999999/download',
  'large-medical-insurance/999999999/histories',
  'payment-request-attachments/999999999/download',
  'payroll-remarks',
  'other-insurance-policies',
];

const context = await playwrightRequest.newContext({
  baseURL,
  extraHTTPHeaders: { Accept: 'application/json' },
});

async function login(pathname, data) {
  const response = await context.post(pathname, { data });
  const body = await response.json();
  if (response.status() !== 200) throw new Error(`Login failed for ${pathname}: ${response.status()}`);
  return body.data?.token || body.token;
}

const adminToken = await login('auth/login', {
  username: process.env.E2E_USERNAME || 'admin',
  password: process.env.E2E_PASSWORD || '123456',
});
const miniToken = await login('mini/login', {
  phone: process.env.MINI_PHONE || '13800000001',
  password: process.env.MINI_PASSWORD || '011234',
});

const probes = [];
for (const route of routes) {
  const startedAt = Date.now();
  const response = await context.get(route, {
    headers: {
      Authorization: `Bearer ${adminToken}`,
      'X-Auth-Token': adminToken,
      'X-Account-Set-Id': String(accountSetId),
    },
    params: { current_account_set_id: accountSetId },
  });
  probes.push({
    route: `GET /${route}`,
    status: response.status(),
    durationMs: Date.now() - startedAt,
    contentType: response.headers()['content-type'] || '',
    body: (await response.text()).slice(0, 4000),
  });
}

const miniStartedAt = Date.now();
const miniResponse = await context.get('mini/my-resignation-certificates', {
  headers: {
    Authorization: `Bearer ${miniToken}`,
    'X-Auth-Token': miniToken,
  },
});
probes.push({
  route: 'GET /mini/my-resignation-certificates',
  status: miniResponse.status(),
  durationMs: Date.now() - miniStartedAt,
  contentType: miniResponse.headers()['content-type'] || '',
  body: (await miniResponse.text()).slice(0, 4000),
});

await context.dispose();
fs.mkdirSync(reportDir, { recursive: true });
fs.writeFileSync(path.join(reportDir, 'probes.json'), `${JSON.stringify(probes, null, 2)}\n`);
console.log(JSON.stringify(probes.map(({ route, status, durationMs, body }) => ({
  route,
  status,
  durationMs,
  message: (() => {
    try {
      const parsed = JSON.parse(body);
      return parsed.message || parsed.error || '';
    } catch {
      return body.slice(0, 160);
    }
  })(),
})), null, 2));
