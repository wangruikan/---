import { execFileSync } from 'node:child_process';
import { expect, test, type APIRequestContext } from '@playwright/test';

const BASE_URL = process.env.API_BASE_URL || 'http://127.0.0.1:8000/api';
const DB_HOST = process.env.DB_HOST || '127.0.0.1';
const DB_PORT = process.env.DB_PORT || '3306';
const DB_DATABASE = process.env.DB_DATABASE || 'weiqing';
const DB_USERNAME = process.env.DB_USERNAME || 'root';
const DB_PASSWORD = process.env.DB_PASSWORD || 'root';
const PASSWORD = 'Pass123456';
const PASSWORD_HASH = '$2y$10$vatwVgpxXSnmzxkxog.2s.M.cW8G60pnlKlytftZnVbyQqrLJavjW';
const RUN_ID = Date.now();
const EMAIL = `api-account-isolation-${RUN_ID}@example.com`;
const ACCOUNT_A = 1;
const ACCOUNT_B = 3;
const PROJECT_ID = 4;

let userId: number;
let requestAId: number;
let requestBId: number;
let token: string;

function sqlValue(value: string | number): string {
  return typeof value === 'number' ? String(value) : `'${value.replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function runSql(sql: string): string {
  const args = ['-N', '-B', '--default-character-set=utf8mb4', '-h', DB_HOST, '-P', DB_PORT, '-u', DB_USERNAME];
  if (DB_PASSWORD) args.push(`-p${DB_PASSWORD}`);
  args.push(DB_DATABASE, '-e', sql);
  return execFileSync('mysql', args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}

function headers(accountSetId = ACCOUNT_A): Record<string, string> {
  return {
    Authorization: `Bearer ${token}`,
    'X-Auth-Token': token,
    'X-Account-Set-Id': String(accountSetId),
    Accept: 'application/json',
  };
}

async function login(request: APIRequestContext): Promise<string> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: { username: EMAIL, password: PASSWORD },
  });
  expect(response.status()).toBe(200);
  const body = await response.json();
  return body.data.token;
}

test.describe('Personnel change account-set isolation', () => {
  test.beforeAll(async ({ request }) => {
    runSql(`
      INSERT INTO users (name, email, password, role, account_set_id, current_account_set_id, is_active, created_at, updated_at)
      VALUES (${sqlValue(`API isolation ${RUN_ID}`)}, ${sqlValue(EMAIL)}, ${sqlValue(PASSWORD_HASH)}, 'employee', ${ACCOUNT_A}, ${ACCOUNT_A}, 1, NOW(), NOW())
    `);
    userId = Number(runSql(`SELECT id FROM users WHERE email = ${sqlValue(EMAIL)} LIMIT 1`));
    runSql(`
      INSERT INTO account_set_users (account_set_id, user_id, role, is_default, created_at, updated_at)
      VALUES (${ACCOUNT_A}, ${userId}, 'viewer', 1, NOW(), NOW())
    `);
    runSql(`
      INSERT INTO personnel_change_requests
        (account_set_id, project_id, month, change_type, personnel_list, remark, status, created_by, created_at, updated_at)
      VALUES
        (${ACCOUNT_A}, ${PROJECT_ID}, '2098-01', 'add', '[]', ${sqlValue(`isolation-a-${RUN_ID}`)}, 'pending', ${userId}, NOW(), NOW()),
        (${ACCOUNT_B}, ${PROJECT_ID}, '2098-02', 'add', '[]', ${sqlValue(`isolation-b-${RUN_ID}`)}, 'pending', ${userId}, NOW(), NOW())
    `);
    requestAId = Number(runSql(`SELECT id FROM personnel_change_requests WHERE remark = ${sqlValue(`isolation-a-${RUN_ID}`)} LIMIT 1`));
    requestBId = Number(runSql(`SELECT id FROM personnel_change_requests WHERE remark = ${sqlValue(`isolation-b-${RUN_ID}`)} LIMIT 1`));
    token = await login(request);
  });

  test.afterAll(() => {
    if (!userId) return;
    runSql(`DELETE FROM personnel_change_request_attachments WHERE personnel_change_request_id IN (${requestAId || 0}, ${requestBId || 0})`);
    runSql(`DELETE FROM personnel_change_requests WHERE id IN (${requestAId || 0}, ${requestBId || 0})`);
    runSql(`DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\\\\Models\\\\User' AND tokenable_id = ${userId}`);
    runSql(`DELETE FROM account_set_users WHERE user_id = ${userId}`);
    runSql(`DELETE FROM users WHERE id = ${userId}`);
  });

  test('the assigned account remains readable', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/personnel-change-requests/${requestAId}`, {
      headers: headers(ACCOUNT_A),
    });
    expect(response.status()).toBe(200);
  });

  test('a user cannot list records from an unassigned account set', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/personnel-change-requests`, {
      headers: headers(ACCOUNT_B),
      params: { current_account_set_id: ACCOUNT_B, per_page: 100 },
    });
    if ([403, 404].includes(response.status())) return;
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.data.data.map((item: { id: number }) => item.id)).not.toContain(requestBId);
  });

  test('a user cannot read a record from an unassigned account set', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/personnel-change-requests/${requestBId}`, {
      headers: headers(ACCOUNT_A),
    });
    expect([403, 404]).toContain(response.status());
  });

  test('a user cannot upload an attachment to an unassigned account record', async ({ request }) => {
    const response = await request.post(`${BASE_URL}/personnel-change-requests/upload-attachment`, {
      headers: headers(ACCOUNT_A),
      multipart: {
        file: {
          name: 'isolation.txt',
          mimeType: 'text/plain',
          buffer: Buffer.from('account isolation test'),
        },
        personnel_change_request_id: String(requestBId),
      },
    });
    expect([403, 404, 422]).toContain(response.status());
  });

  test('a user cannot delete an unassigned account record', async ({ request }) => {
    const response = await request.delete(`${BASE_URL}/personnel-change-requests/${requestBId}`, {
      headers: headers(ACCOUNT_A),
    });
    expect([403, 404]).toContain(response.status());
  });
});
