import { expect, test, type APIRequestContext } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const BASE_URL = process.env.API_BASE_URL || 'http://127.0.0.1:8000/api';
const DB_HOST = process.env.DB_HOST || '127.0.0.1';
const DB_PORT = process.env.DB_PORT || '3306';
const DB_DATABASE = process.env.DB_DATABASE || 'weiqing';
const DB_USERNAME = process.env.DB_USERNAME || 'root';
const DB_PASSWORD = process.env.DB_PASSWORD || 'root';
const RUN_ID = String(Date.now());
const PREFIX = `mini-account-switch-${RUN_ID}`;
const PHONE = `139${RUN_ID.slice(-8).padStart(8, '0')}`;
const ID_NUMBER = '110101199001123456';
const PASSWORD = '123456';
const PASSWORD_HASH = '$2y$10$vatwVgpxXSnmzxkxog.2s.M.cW8G60pnlKlytftZnVbyQqrLJavjW';

interface SeedContext {
  firstAccountSetId: number;
  secondAccountSetId: number;
  firstProjectId: number;
  secondProjectId: number;
  firstEmployeeId: number;
  secondEmployeeId: number;
  firstContractId: number;
  secondContractId: number;
}

interface MiniResponse {
  success: boolean;
  data: {
    token: string;
    employee: { id: number; account_set_name?: string; project_name?: string };
    available_accounts: Array<{
      employee_id: number;
      account_set_name: string;
      project_name: string | null;
      is_current: boolean;
    }>;
  };
}

function mysqlArgs(sql: string): string[] {
  const args = ['-N', '-B', '--default-character-set=utf8mb4', '-h', DB_HOST, '-P', DB_PORT, '-u', DB_USERNAME];
  if (DB_PASSWORD !== '') {
    args.push(`-p${DB_PASSWORD}`);
  }
  args.push(DB_DATABASE, '-e', sql);
  return args;
}

function runSql(sql: string): string {
  return execFileSync('mysql', mysqlArgs(sql), {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

function queryRows(sql: string): string[][] {
  const output = runSql(sql);
  return output
    ? output.split(/\r?\n/).filter(Boolean).map((line) => line.split('\t'))
    : [];
}

function queryId(sql: string): number {
  const value = runSql(sql);
  if (!value) {
    throw new Error(`Expected one row: ${sql}`);
  }
  return Number(value);
}

function sqlValue(value: string | number | null): string {
  if (value === null) {
    return 'NULL';
  }
  if (typeof value === 'number') {
    return String(value);
  }
  return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function cleanup(): void {
  const employeeWhere = `phone = ${sqlValue(PHONE)} AND id_number = ${sqlValue(ID_NUMBER)}`;
  const employeeIds = `SELECT id FROM employees WHERE ${employeeWhere}`;
  const projectWhere = `code IN (${sqlValue(`${PREFIX}-project-a`)}, ${sqlValue(`${PREFIX}-project-b`)})`;
  const projectIds = `SELECT id FROM projects WHERE ${projectWhere}`;
  const accountSetWhere = `code IN (${sqlValue(`${PREFIX}-account-a`)}, ${sqlValue(`${PREFIX}-account-b`)})`;
  const accountSetIds = `SELECT id FROM account_sets WHERE ${accountSetWhere}`;

  for (const sql of [
    `DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\\\\Models\\\\Employee' AND tokenable_id IN (${employeeIds})`,
    `DELETE FROM employee_contracts WHERE employee_id IN (${employeeIds})`,
    `DELETE FROM employee_projects WHERE employee_id IN (${employeeIds}) OR project_id IN (${projectIds})`,
    `DELETE FROM employees WHERE ${employeeWhere}`,
    `DELETE FROM projects WHERE ${projectWhere}`,
    `DELETE FROM account_sets WHERE ${accountSetWhere}`,
    `DELETE FROM users WHERE email = ${sqlValue(`${PREFIX}@example.com`)}`,
  ]) {
    runSql(sql);
  }
}

function seed(): SeedContext {
  cleanup();

  runSql(`
    INSERT INTO users (name, email, password, role, is_active, created_at, updated_at)
    VALUES (${sqlValue(PREFIX)}, ${sqlValue(`${PREFIX}@example.com`)}, ${sqlValue(PASSWORD_HASH)}, 'admin', 1, NOW(), NOW())
  `);
  const userId = queryId(`SELECT id FROM users WHERE email = ${sqlValue(`${PREFIX}@example.com`)} LIMIT 1`);

  runSql(`
    INSERT INTO account_sets (name, code, status, created_by, created_at, updated_at)
    VALUES
      (${sqlValue(`${PREFIX}-账套A`)}, ${sqlValue(`${PREFIX}-account-a`)}, 'active', ${userId}, NOW(), NOW()),
      (${sqlValue(`${PREFIX}-账套B`)}, ${sqlValue(`${PREFIX}-account-b`)}, 'active', ${userId}, NOW(), NOW())
  `);
  const firstAccountSetId = queryId(`SELECT id FROM account_sets WHERE code = ${sqlValue(`${PREFIX}-account-a`)} LIMIT 1`);
  const secondAccountSetId = queryId(`SELECT id FROM account_sets WHERE code = ${sqlValue(`${PREFIX}-account-b`)} LIMIT 1`);

  runSql(`
    INSERT INTO projects (account_set_id, name, code, status, start_date, created_at, updated_at)
    VALUES
      (${firstAccountSetId}, ${sqlValue(`${PREFIX}-项目A`)}, ${sqlValue(`${PREFIX}-project-a`)}, 'active', '2026-01-01', NOW(), NOW()),
      (${secondAccountSetId}, ${sqlValue(`${PREFIX}-项目B`)}, ${sqlValue(`${PREFIX}-project-b`)}, 'active', '2026-01-01', NOW(), NOW())
  `);
  const firstProjectId = queryId(`SELECT id FROM projects WHERE code = ${sqlValue(`${PREFIX}-project-a`)} LIMIT 1`);
  const secondProjectId = queryId(`SELECT id FROM projects WHERE code = ${sqlValue(`${PREFIX}-project-b`)} LIMIT 1`);

  runSql(`
    INSERT INTO employees (
      account_set_id, name, id_number, phone, gender, birth_date, hire_date, contract_start_date,
      project_ids, employee_number, created_at, updated_at
    ) VALUES
      (${firstAccountSetId}, ${sqlValue(`${PREFIX}-员工A`)}, ${sqlValue(ID_NUMBER)}, ${sqlValue(PHONE)}, 'male', '1990-01-01', '2026-01-01', '2026-01-01', ${sqlValue(JSON.stringify([firstProjectId]))}, ${sqlValue(`${PREFIX}-A`)}, NOW(), NOW()),
      (${secondAccountSetId}, ${sqlValue(`${PREFIX}-员工B`)}, ${sqlValue(ID_NUMBER)}, ${sqlValue(PHONE)}, 'male', '1990-01-01', '2026-01-01', '2026-01-01', ${sqlValue(JSON.stringify([secondProjectId]))}, ${sqlValue(`${PREFIX}-B`)}, NOW(), NOW())
  `);
  const firstEmployeeId = queryId(`SELECT id FROM employees WHERE account_set_id = ${firstAccountSetId} AND phone = ${sqlValue(PHONE)} LIMIT 1`);
  const secondEmployeeId = queryId(`SELECT id FROM employees WHERE account_set_id = ${secondAccountSetId} AND phone = ${sqlValue(PHONE)} LIMIT 1`);

  // 模拟历史版本曾经选择过第一个账套，验证登录规则仍固定进入最新档案。
  runSql(`UPDATE employees SET mini_selected_at = NOW() WHERE id = ${firstEmployeeId}`);

  runSql(`
    INSERT INTO employee_projects (employee_id, project_id, start_date, status, created_at, updated_at)
    VALUES
      (${firstEmployeeId}, ${firstProjectId}, '2026-01-01', 'active', NOW(), NOW()),
      (${secondEmployeeId}, ${secondProjectId}, '2026-01-01', 'active', NOW(), NOW())
  `);
  runSql(`
    INSERT INTO employee_contracts (employee_id, account_set_id, contract_type, status, created_at, updated_at)
    VALUES
      (${firstEmployeeId}, ${firstAccountSetId}, 'labor', 'pending_sign', NOW(), NOW()),
      (${secondEmployeeId}, ${secondAccountSetId}, 'labor', 'pending_sign', NOW(), NOW())
  `);
  const firstContractId = queryId(`SELECT id FROM employee_contracts WHERE employee_id = ${firstEmployeeId} LIMIT 1`);
  const secondContractId = queryId(`SELECT id FROM employee_contracts WHERE employee_id = ${secondEmployeeId} LIMIT 1`);

  return {
    firstAccountSetId,
    secondAccountSetId,
    firstProjectId,
    secondProjectId,
    firstEmployeeId,
    secondEmployeeId,
    firstContractId,
    secondContractId,
  };
}

async function miniLogin(request: APIRequestContext): Promise<MiniResponse> {
  const response = await request.post(`${BASE_URL}/mini/login`, {
    data: { phone: PHONE, password: PASSWORD },
  });
  const body = await response.json() as MiniResponse;
  expect(response.status(), JSON.stringify(body)).toBe(200);
  expect(body.success).toBe(true);
  return body;
}

function miniHeaders(token: string): Record<string, string> {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' };
}

test('小程序登录固定进入同一员工的最新账套档案', async ({ request }) => {
  const ctx = seed();

  try {
    const firstLogin = await miniLogin(request);
    expect(firstLogin.data.employee.id).toBe(ctx.secondEmployeeId);
    expect(firstLogin.data.employee.account_set_name).toBe(`${PREFIX}-账套B`);
    expect(firstLogin.data.employee.project_name).toBe(`${PREFIX}-项目B`);
    const secondContractsResponse = await request.get(`${BASE_URL}/mini/pending-contracts`, {
      headers: miniHeaders(firstLogin.data.token),
    });
    const secondContracts = await secondContractsResponse.json();
    expect(secondContractsResponse.status(), JSON.stringify(secondContracts)).toBe(200);
    expect(secondContracts.data.map((contract: { id: number }) => contract.id)).toEqual([ctx.secondContractId]);

    const currentInfoResponse = await request.get(`${BASE_URL}/mini/my-info`, {
      headers: miniHeaders(firstLogin.data.token),
    });
    const currentInfo = await currentInfoResponse.json();
    expect(currentInfoResponse.status(), JSON.stringify(currentInfo)).toBe(200);
    expect(currentInfo.data.id).toBe(ctx.secondEmployeeId);

    const relogin = await miniLogin(request);
    expect(relogin.data.employee.id).toBe(ctx.secondEmployeeId);
  } finally {
    cleanup();
  }
});
