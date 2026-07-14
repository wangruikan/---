import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { expect, test, type APIRequestContext } from '@playwright/test';

const BASE_URL = process.env.API_BASE_URL || 'http://127.0.0.1:8000/api';
const ADMIN_USERNAME = process.env.E2E_USERNAME || 'admin';
const ADMIN_PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const DB_HOST = process.env.DB_HOST || '127.0.0.1';
const DB_PORT = process.env.DB_PORT || '3306';
const DB_DATABASE = process.env.DB_DATABASE || 'weiqing';
const DB_USERNAME = process.env.DB_USERNAME || 'root';
const DB_PASSWORD = process.env.DB_PASSWORD || 'root';
const TEST_PASSWORD = 'Pass123456';
const PASSWORD_HASH = '$2y$10$vatwVgpxXSnmzxkxog.2s.M.cW8G60pnlKlytftZnVbyQqrLJavjW';
const RUN_ID = Date.now();
const TEST_EMAIL = `api-stateful-${RUN_ID}@example.com`;
const TEST_IMAGE = path.join(process.cwd(), 'tests', 'api', 'test-image.png');

let adminToken: string;
let signatureToken: string;
let signatureUserId: number;

function sqlValue(value: string | number): string {
  return typeof value === 'number' ? String(value) : `'${value.replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function runSql(sql: string): string {
  const args = ['-N', '-B', '--default-character-set=utf8mb4', '-h', DB_HOST, '-P', DB_PORT, '-u', DB_USERNAME];
  if (DB_PASSWORD) args.push(`-p${DB_PASSWORD}`);
  args.push(DB_DATABASE, '-e', sql);
  return execFileSync('mysql', args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}

async function login(request: APIRequestContext, username: string, password: string): Promise<string> {
  const response = await request.post(`${BASE_URL}/auth/login`, { data: { username, password } });
  expect(response.status()).toBe(200);
  const body = await response.json();
  return body.data.token;
}

function authHeaders(token: string): Record<string, string> {
  return {
    Authorization: `Bearer ${token}`,
    'X-Auth-Token': token,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
    Accept: 'application/json',
  };
}

test.describe('Active API stateful contracts', () => {
  test.beforeAll(async ({ request }) => {
    adminToken = await login(request, ADMIN_USERNAME, ADMIN_PASSWORD);

    runSql(`
      INSERT INTO users (name, email, password, role, account_set_id, current_account_set_id, is_active, created_at, updated_at)
      VALUES (${sqlValue(`API stateful ${RUN_ID}`)}, ${sqlValue(TEST_EMAIL)}, ${sqlValue(PASSWORD_HASH)}, 'employee', ${ACCOUNT_SET_ID}, ${ACCOUNT_SET_ID}, 1, NOW(), NOW())
    `);
    signatureUserId = Number(runSql(`SELECT id FROM users WHERE email = ${sqlValue(TEST_EMAIL)} LIMIT 1`));
    runSql(`
      INSERT INTO account_set_users (account_set_id, user_id, role, is_default, created_at, updated_at)
      VALUES (${ACCOUNT_SET_ID}, ${signatureUserId}, 'viewer', 1, NOW(), NOW())
    `);
    signatureToken = await login(request, TEST_EMAIL, TEST_PASSWORD);
  });

  test.afterAll(() => {
    if (!signatureUserId) return;
    const imagePath = runSql(`SELECT image_path FROM user_signatures WHERE user_id = ${signatureUserId} LIMIT 1`);
    if (imagePath) {
      const localPath = path.join(process.cwd(), 'storage', 'app', 'public', imagePath);
      if (fs.existsSync(localPath)) fs.unlinkSync(localPath);
    }
    runSql(`DELETE FROM user_signatures WHERE user_id = ${signatureUserId}`);
    runSql(`DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\\\\Models\\\\User' AND tokenable_id = ${signatureUserId}`);
    runSql(`DELETE FROM account_set_users WHERE user_id = ${signatureUserId}`);
    runSql(`DELETE FROM users WHERE id = ${signatureUserId}`);
  });

  test('logout revokes the current token', async ({ request }) => {
    const token = await login(request, ADMIN_USERNAME, ADMIN_PASSWORD);
    const before = await request.get(`${BASE_URL}/auth/user`, { headers: authHeaders(token) });
    expect(before.status()).toBe(200);

    const logout = await request.post(`${BASE_URL}/auth/logout`, { headers: authHeaders(token) });
    expect(logout.status()).toBe(200);

    const after = await request.get(`${BASE_URL}/auth/user`, { headers: authHeaders(token) });
    expect(after.status()).toBe(401);
    adminToken = await login(request, ADMIN_USERNAME, ADMIN_PASSWORD);
  });

  test('menu layout can be written back without changing its value', async ({ request }) => {
    const before = await request.get(`${BASE_URL}/roles/menu-layout`, { headers: authHeaders(adminToken) });
    expect(before.status()).toBe(200);
    const beforeBody = await before.json();

    const update = await request.put(`${BASE_URL}/roles/menu-layout`, {
      headers: authHeaders(adminToken),
      data: { layout: beforeBody.data, current_account_set_id: ACCOUNT_SET_ID },
    });
    expect(update.status()).toBe(200);

    const after = await request.get(`${BASE_URL}/roles/menu-layout`, { headers: authHeaders(adminToken) });
    expect(after.status()).toBe(200);
    expect((await after.json()).data).toEqual(beforeBody.data);
  });

  test('signature upload, read and delete form a complete lifecycle', async ({ request }) => {
    const upload = await request.post(`${BASE_URL}/signatures/upload`, {
      headers: authHeaders(signatureToken),
      multipart: {
        signature_image: {
          name: 'signature.png',
          mimeType: 'image/png',
          buffer: fs.readFileSync(TEST_IMAGE),
        },
      },
    });
    expect(upload.status()).toBe(200);
    const uploaded = await upload.json();
    expect(uploaded.data.user_id).toBe(signatureUserId);

    const read = await request.get(`${BASE_URL}/signatures/my`, { headers: authHeaders(signatureToken) });
    expect(read.status()).toBe(200);
    expect((await read.json()).data.user_id).toBe(signatureUserId);

    const remove = await request.delete(`${BASE_URL}/signatures`, { headers: authHeaders(signatureToken) });
    expect(remove.status()).toBe(200);

    const after = await request.get(`${BASE_URL}/signatures/my`, { headers: authHeaders(signatureToken) });
    expect(after.status()).toBe(200);
    expect((await after.json()).data).toBeNull();
  });

  test('download-all-documents returns a zip or a clear no-data response', async ({ request }) => {
    const response = await request.post(`${BASE_URL}/employees/download-all-documents`, {
      headers: authHeaders(adminToken),
      data: { current_account_set_id: ACCOUNT_SET_ID },
      timeout: 120_000,
    });
    expect([200, 404]).toContain(response.status());
    if (response.status() === 200) {
      expect(response.headers()['content-type']).toContain('application/zip');
      expect((await response.body()).length).toBeGreaterThan(0);
    } else {
      expect((await response.json()).message).toContain('没有可下载');
    }
  });

  test('salary payment export returns structured data or a clear no-data response', async ({ request }) => {
    const response = await request.post(`${BASE_URL}/salary-payment-records/export`, {
      headers: authHeaders(adminToken),
      data: { current_account_set_id: ACCOUNT_SET_ID },
    });
    expect([200, 422]).toContain(response.status());
    const body = await response.json();
    if (response.status() === 200) {
      expect(Array.isArray(body.headers)).toBe(true);
      expect(Array.isArray(body.data)).toBe(true);
      expect(body.filename).toBeTruthy();
    } else {
      expect(body.message).toContain('没有数据');
    }
  });
});
