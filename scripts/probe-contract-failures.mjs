import fs from 'node:fs';
import path from 'node:path';
import { request as playwrightRequest } from '@playwright/test';

const root = process.cwd();
const scope = process.argv[2];
if (!scope) throw new Error('Usage: node scripts/probe-contract-failures.mjs <report-scope>');

const baseURL = `${(process.env.API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '')}/`;
const accountSetId = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const reportDir = path.join(root, 'storage', 'api-test-reports', scope);
const failures = JSON.parse(fs.readFileSync(path.join(reportDir, 'failures.json'), 'utf8'));

function materialize(uri) {
  return uri
    .replace(/\/\{[^}]+\?\}/g, '')
    .replace(/\{[^}]+\}/g, '999999999');
}

const targets = [...new Map(failures.flatMap((failure) => {
  const match = failure.title.match(/\b(GET|POST|PUT|PATCH|DELETE) \/([^ ]+)/);
  if (!match) return [];
  const target = { method: match[1], uri: match[2] };
  return [[`${target.method} ${target.uri}`, target]];
})).values()];

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
for (const target of targets) {
  const isMini = target.uri.startsWith('mini/');
  const token = isMini ? miniToken : adminToken;
  const options = {
    method: target.method,
    headers: {
      Authorization: `Bearer ${token}`,
      'X-Auth-Token': token,
      ...(isMini ? {} : { 'X-Account-Set-Id': String(accountSetId) }),
    },
    params: isMini ? {} : { current_account_set_id: accountSetId },
    failOnStatusCode: false,
  };
  if (!['GET', 'DELETE'].includes(target.method)) options.data = {};

  const startedAt = Date.now();
  const response = await context.fetch(materialize(target.uri), options);
  const body = (await response.text()).slice(0, 6000);
  let message = body.slice(0, 300);
  let exception = '';
  try {
    const parsed = JSON.parse(body);
    message = parsed.message || parsed.error || message;
    exception = parsed.exception || '';
  } catch {
    // Keep the text preview for non-JSON responses.
  }

  probes.push({
    method: target.method,
    uri: target.uri,
    requestPath: materialize(target.uri),
    status: response.status(),
    durationMs: Date.now() - startedAt,
    contentType: response.headers()['content-type'] || '',
    message,
    exception,
    body,
  });
}

await context.dispose();
fs.writeFileSync(path.join(reportDir, 'probes.json'), `${JSON.stringify(probes, null, 2)}\n`);
console.log(JSON.stringify(probes.map(({ method, uri, status, message, exception }) => ({
  method,
  uri,
  status,
  message,
  exception,
})), null, 2));
