import { test, expect, APIRequestContext } from '@playwright/test';
import { execFileSync } from 'child_process';

const DB_HOST = process.env.DB_HOST || '127.0.0.1';
const DB_PORT = process.env.DB_PORT || '3306';
const DB_DATABASE = process.env.DB_DATABASE || 'weiqing';
const DB_USERNAME = process.env.DB_USERNAME || 'root';
const DB_PASSWORD = process.env.DB_PASSWORD || 'root';
const BASE_URL = process.env.API_BASE_URL || 'http://localhost:8000/api';
const PASSWORD_HASH =
  process.env.E2E_INSURANCE_CHANGE_PASSWORD_HASH ||
  '$2y$10$vatwVgpxXSnmzxkxog.2s.M.cW8G60pnlKlytftZnVbyQqrLJavjW';
const USER_PASSWORD = process.env.E2E_INSURANCE_CHANGE_PASSWORD || 'Pass123456';

const RUN_ID = String(Date.now());
const PREFIX = `api-ins-change-${RUN_ID}`;
const OTHER_POLICY_PREFIX = 'other_policy:';
const TEST_PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
  'base64'
);

interface SeedContext {
  userId: number;
  accountSetId: number;
  projectId: number;
  employeeSuccessId: number;
  employeeTerminatedId: number;
  policyIds: [number, number];
  token: string;
}

interface ChangeItem {
  id: number;
  category: string;
  status: 'pending' | 'submitted' | 'completed' | 'failed' | 'terminated';
  category_snapshot?: string | null;
}

let ctx: SeedContext;

function mysqlArgs(sql: string): string[] {
  const args = ['-N', '-B', '-h', DB_HOST, '-P', DB_PORT, '-u', DB_USERNAME];
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
  if (!output) {
    return [];
  }

  return output
    .split(/\r?\n/)
    .filter(Boolean)
    .map((line) => line.split('\t'));
}

function querySingleNumber(sql: string): number {
  const value = runSql(sql);
  if (!value) {
    throw new Error(`Query returned empty result: ${sql}`);
  }
  return Number(value);
}

function querySingleValue(sql: string): string | null {
  const value = runSql(sql);
  if (!value || value === 'NULL' || value === '\\N') {
    return null;
  }
  return value;
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

function apiUrl(path: string): string {
  return `${BASE_URL}/${path.replace(/^\/+/, '')}`;
}

function authHeaders(token = ctx.token, accountSetId = ctx.accountSetId): Record<string, string> {
  return {
    Authorization: `Bearer ${token}`,
    'X-Account-Set-Id': String(accountSetId),
    Accept: 'application/json',
  };
}

function idNumber(seed: number): string {
  const suffix = `${RUN_ID}${seed}`.replace(/\D/g, '').slice(-16).padStart(16, '0');
  return `91${suffix}`;
}

function policySnapshot(policyId: number, label: string, cost: number): Record<string, unknown> {
  return {
    id: policyId,
    policy_name: label,
    name: label,
    type_name: `${PREFIX}-商业险`,
    employee_per_capita_cost: cost,
    start_date: '2026-01-01',
    end_date: '2026-12-31',
    policy_start_date: '2026-01-01',
    policy_end_date: '2026-12-31',
  };
}

function parsePolicyIds(value: string | null): number[] {
  if (!value) {
    return [];
  }

  const parsed = JSON.parse(value);
  if (!Array.isArray(parsed)) {
    return [];
  }

  return parsed.map((item) => Number(item.id)).filter(Boolean);
}

function cleanupSeedData(): void {
  const accountSetSubquery = `SELECT id FROM account_sets WHERE code = ${sqlValue(`${PREFIX}-account-set`)}`;
  const userSubquery = `SELECT id FROM users WHERE email = ${sqlValue(`${PREFIX}-admin@example.com`)}`;
  const changeSubquery = `SELECT id FROM insurance_changes WHERE account_set_id IN (${accountSetSubquery})`;
  const projectSubquery = `SELECT id FROM projects WHERE account_set_id IN (${accountSetSubquery})`;
  const employeeSubquery = `SELECT id FROM employees WHERE account_set_id IN (${accountSetSubquery})`;

  const statements = [
    `DELETE FROM insurance_change_attachments WHERE insurance_change_id IN (${changeSubquery})`,
    `DELETE FROM insurance_change_items WHERE insurance_change_id IN (${changeSubquery})`,
    `DELETE FROM insurance_detail_records WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM insurance_personnel WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM insurance_changes WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM employee_projects WHERE employee_id IN (${employeeSubquery}) OR project_id IN (${projectSubquery})`,
    `DELETE FROM project_other_insurance_policies WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM other_insurance_policies WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM other_insurance_types WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM projects WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM employees WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM account_set_users WHERE account_set_id IN (${accountSetSubquery}) OR user_id IN (${userSubquery})`,
    `DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\\\\Models\\\\User' AND tokenable_id IN (${userSubquery})`,
    `DELETE FROM users WHERE email = ${sqlValue(`${PREFIX}-admin@example.com`)}`,
    `DELETE FROM account_sets WHERE code = ${sqlValue(`${PREFIX}-account-set`)}`,
  ];

  for (const sql of statements) {
    runSql(sql);
  }
}

async function login(request: APIRequestContext): Promise<{ token: string; userId: number }> {
  const response = await request.post(apiUrl('auth/login'), {
    data: {
      username: `${PREFIX}-admin@example.com`,
      password: USER_PASSWORD,
    },
  });

  expect(response.status()).toBe(200);
  const body = await response.json();
  expect(body.success).toBe(true);
  return {
    token: body.data.token,
    userId: Number(body.data.user.id),
  };
}

async function seedBaseData(request: APIRequestContext): Promise<SeedContext> {
  cleanupSeedData();

  runSql(`
    INSERT INTO users (
      name, email, password, role, is_active, created_at, updated_at
    ) VALUES (
      ${sqlValue(`${PREFIX}-admin`)},
      ${sqlValue(`${PREFIX}-admin@example.com`)},
      ${sqlValue(PASSWORD_HASH)},
      'admin',
      1,
      NOW(),
      NOW()
    )
  `);
  const userId = querySingleNumber(
    `SELECT id FROM users WHERE email = ${sqlValue(`${PREFIX}-admin@example.com`)} LIMIT 1`
  );

  runSql(`
    INSERT INTO account_sets (name, code, status, created_by, created_at, updated_at)
    VALUES (
      ${sqlValue(`${PREFIX}-account-set`)},
      ${sqlValue(`${PREFIX}-account-set`)},
      'active',
      ${userId},
      NOW(),
      NOW()
    )
  `);
  const accountSetId = querySingleNumber(
    `SELECT id FROM account_sets WHERE code = ${sqlValue(`${PREFIX}-account-set`)} LIMIT 1`
  );

  runSql(`
    UPDATE users
    SET account_set_id = ${accountSetId}, current_account_set_id = ${accountSetId}, updated_at = NOW()
    WHERE id = ${userId}
  `);

  runSql(`
    INSERT INTO account_set_users (
      account_set_id, user_id, role, is_default, created_at, updated_at
    ) VALUES (${accountSetId}, ${userId}, 'owner', 1, NOW(), NOW())
  `);

  runSql(`
    INSERT INTO projects (
      account_set_id, name, code, status, salary_payment_month, insurance_import_month,
      require_attendance, requires_attendance, requires_salary_basis, requires_attendance_basis,
      created_at, updated_at
    ) VALUES (
      ${accountSetId},
      ${sqlValue(`${PREFIX}-项目`)},
      ${sqlValue(`${PREFIX}-P`)},
      'active',
      'current',
      'current',
      0,
      0,
      0,
      0,
      NOW(),
      NOW()
    )
  `);
  const projectId = querySingleNumber(
    `SELECT id FROM projects WHERE code = ${sqlValue(`${PREFIX}-P`)} LIMIT 1`
  );

  runSql(`
    INSERT INTO employees (
      account_set_id, name, id_number, phone, gender, birth_date, hire_date,
      contract_start_date, contract_end_date, project_ids, other_insurance_enabled,
      created_at, updated_at
    ) VALUES
      (
        ${accountSetId},
        ${sqlValue(`${PREFIX}-成功失败员工`)},
        ${sqlValue(idNumber(1))},
        ${sqlValue('13900000001')},
        'male',
        '1990-01-01',
        '2026-07-01',
        '2026-07-01',
        '2029-06-30',
        ${sqlValue(JSON.stringify([projectId]))},
        1,
        NOW(),
        NOW()
      ),
      (
        ${accountSetId},
        ${sqlValue(`${PREFIX}-终结员工`)},
        ${sqlValue(idNumber(2))},
        ${sqlValue('13900000002')},
        'female',
        '1992-02-02',
        '2026-07-01',
        '2026-07-01',
        '2029-06-30',
        ${sqlValue(JSON.stringify([projectId]))},
        1,
        NOW(),
        NOW()
      )
  `);
  const employeeSuccessId = querySingleNumber(
    `SELECT id FROM employees WHERE id_number = ${sqlValue(idNumber(1))} LIMIT 1`
  );
  const employeeTerminatedId = querySingleNumber(
    `SELECT id FROM employees WHERE id_number = ${sqlValue(idNumber(2))} LIMIT 1`
  );

  runSql(`
    INSERT INTO employee_projects (
      employee_id, project_id, start_date, status, created_at, updated_at
    ) VALUES
      (${employeeSuccessId}, ${projectId}, '2026-07-01', 'active', NOW(), NOW()),
      (${employeeTerminatedId}, ${projectId}, '2026-07-01', 'active', NOW(), NOW())
  `);

  runSql(`
    INSERT INTO other_insurance_types (
      name, description, account_set_id, created_by, created_at, updated_at
    ) VALUES (
      ${sqlValue(`${PREFIX}-商业险`)},
      ${sqlValue('API 测试其他保险类型')},
      ${accountSetId},
      ${userId},
      NOW(),
      NOW()
    )
  `);
  const typeId = querySingleNumber(
    `SELECT id FROM other_insurance_types WHERE name = ${sqlValue(`${PREFIX}-商业险`)} LIMIT 1`
  );

  runSql(`
    INSERT INTO other_insurance_policies (
      type_id, policy_number, policy_name, insurance_company, coverage_amount,
      employee_per_capita_cost, quota, start_date, end_date, status,
      account_set_id, created_by, created_at, updated_at
    ) VALUES
      (
        ${typeId},
        ${sqlValue(`${PREFIX}-POLICY-A`)},
        ${sqlValue(`${PREFIX}-细分保险A`)},
        ${sqlValue(`${PREFIX}-保险公司`)},
        10000,
        100,
        10,
        '2026-01-01',
        '2026-12-31',
        'active',
        ${accountSetId},
        ${userId},
        NOW(),
        NOW()
      ),
      (
        ${typeId},
        ${sqlValue(`${PREFIX}-POLICY-B`)},
        ${sqlValue(`${PREFIX}-细分保险B`)},
        ${sqlValue(`${PREFIX}-保险公司`)},
        20000,
        200,
        10,
        '2026-01-01',
        '2026-12-31',
        'active',
        ${accountSetId},
        ${userId},
        NOW(),
        NOW()
      )
  `);

  const policyAId = querySingleNumber(
    `SELECT id FROM other_insurance_policies WHERE policy_number = ${sqlValue(`${PREFIX}-POLICY-A`)} LIMIT 1`
  );
  const policyBId = querySingleNumber(
    `SELECT id FROM other_insurance_policies WHERE policy_number = ${sqlValue(`${PREFIX}-POLICY-B`)} LIMIT 1`
  );

  runSql(`
    UPDATE employees
    SET other_insurance_policy_ids = ${sqlValue(JSON.stringify([policyAId, policyBId]))}
    WHERE id IN (${employeeSuccessId}, ${employeeTerminatedId})
  `);

  runSql(`
    INSERT INTO project_other_insurance_policies (
      project_id, policy_id, account_set_id, created_at, updated_at
    ) VALUES
      (${projectId}, ${policyAId}, ${accountSetId}, NOW(), NOW()),
      (${projectId}, ${policyBId}, ${accountSetId}, NOW(), NOW())
  `);

  const loginResult = await login(request);
  return {
    userId,
    accountSetId,
    projectId,
    employeeSuccessId,
    employeeTerminatedId,
    policyIds: [policyAId, policyBId],
    token: loginResult.token,
  };
}

function seedInsuranceChange(employeeId: number, employeeName: string): number {
  const policies = [
    policySnapshot(ctx.policyIds[0], `${PREFIX}-细分保险A`, 100),
    policySnapshot(ctx.policyIds[1], `${PREFIX}-细分保险B`, 200),
  ];
  const changeDetails = [
    {
      category: 'other_insurance',
      action: 'added',
      item: '其他保险新增2项',
    },
  ];

  runSql(`
    INSERT INTO insurance_changes (
      employee_id, employee_name, employee_id_number, employee_gender, employee_birth_date,
      employee_phone, employee_status, project_id, account_set_id, change_type, status,
      fully_confirmed, other_insurance_processed, other_insurance_policies, change_summary,
      change_details, created_by, created_at, updated_at
    ) VALUES (
      ${employeeId},
      ${sqlValue(employeeName)},
      ${sqlValue(employeeId === ctx.employeeSuccessId ? idNumber(1) : idNumber(2))},
      ${employeeId === ctx.employeeSuccessId ? 1 : 2},
      ${employeeId === ctx.employeeSuccessId ? sqlValue('1990-01-01') : sqlValue('1992-02-02')},
      ${employeeId === ctx.employeeSuccessId ? sqlValue('13900000001') : sqlValue('13900000002')},
      1,
      ${ctx.projectId},
      ${ctx.accountSetId},
      'increase',
      'pending',
      0,
      0,
      ${sqlValue(JSON.stringify(policies))},
      ${sqlValue('其他保险新增2项')},
      ${sqlValue(JSON.stringify(changeDetails))},
      ${ctx.userId},
      NOW(),
      NOW()
    )
  `);

  return querySingleNumber(`
    SELECT id
    FROM insurance_changes
    WHERE employee_id = ${employeeId}
      AND project_id = ${ctx.projectId}
      AND account_set_id = ${ctx.accountSetId}
    ORDER BY id DESC
    LIMIT 1
  `);
}

async function getItems(request: APIRequestContext, changeId: number): Promise<ChangeItem[]> {
  const response = await request.get(apiUrl(`insurance-changes/${changeId}/items`), {
    headers: authHeaders(),
  });

  expect(response.status()).toBe(200);
  const body = await response.json();
  expect(body.success).toBe(true);
  expect(Array.isArray(body.data)).toBe(true);

  return body.data as ChangeItem[];
}

async function getChange(request: APIRequestContext, changeId: number): Promise<any> {
  const response = await request.get(apiUrl(`insurance-changes/${changeId}`), {
    headers: authHeaders(),
  });

  expect(response.status()).toBe(200);
  const body = await response.json();
  expect(body.success).toBe(true);
  return body.data;
}

async function confirmItem(
  request: APIRequestContext,
  changeId: number,
  itemId: number,
  result: 'success' | 'failed' | 'terminated'
): Promise<any> {
  const response = await request.put(apiUrl(`insurance-changes/${changeId}/confirm-process`), {
    headers: authHeaders(),
    data: {
      item_ids: [itemId],
      result,
    },
  });

  const body = await response.json();
  expect(response.status(), JSON.stringify(body)).toBe(200);
  expect(body.success).toBe(true);
  return body.data;
}

async function uploadGeneralAttachment(request: APIRequestContext, changeId: number): Promise<void> {
  const response = await request.post(apiUrl(`insurance-changes/${changeId}/upload-attachment`), {
    headers: authHeaders(),
    multipart: {
      'attachments[]': {
        name: 'process-proof.png',
        mimeType: 'image/png',
        buffer: TEST_PNG,
      },
    },
  });

  const body = await response.json();
  expect(response.status(), JSON.stringify(body)).toBe(200);
  expect(body.success).toBe(true);
}

function itemByCategory(items: ChangeItem[], category: string): ChangeItem {
  const item = items.find((candidate) => candidate.category === category);
  expect(item, `missing item ${category}`).toBeTruthy();
  return item!;
}

function currentPersonnelPolicyIds(employeeId: number): number[] {
  const value = querySingleValue(`
    SELECT other_insurance_policies
    FROM insurance_personnel
    WHERE employee_id = ${employeeId}
      AND project_id = ${ctx.projectId}
      AND account_set_id = ${ctx.accountSetId}
    ORDER BY id DESC
    LIMIT 1
  `);

  return parsePolicyIds(value);
}

function currentDetailPolicyIds(employeeId: number): number[] {
  const value = querySingleValue(`
    SELECT other_insurance_policies
    FROM insurance_detail_records
    WHERE employee_id = ${employeeId}
      AND project_id = ${ctx.projectId}
      AND account_set_id = ${ctx.accountSetId}
      AND record_year = YEAR(CURDATE())
      AND record_month = MONTH(CURDATE())
    ORDER BY id DESC
    LIMIT 1
  `);

  return parsePolicyIds(value);
}

test.describe.serial('参保增减其他保险细分 API 测试', () => {
  test.beforeAll(async ({ request }) => {
    ctx = await seedBaseData(request);
  });

  test.afterAll(async () => {
    cleanupSeedData();
  });

  test('其他保险细分可单独成功和失败处理，失败生成续办任务', async ({ request }) => {
    const changeId = seedInsuranceChange(ctx.employeeSuccessId, `${PREFIX}-成功失败员工`);
    const policyACategory = `${OTHER_POLICY_PREFIX}${ctx.policyIds[0]}`;
    const policyBCategory = `${OTHER_POLICY_PREFIX}${ctx.policyIds[1]}`;

    const initialItems = await getItems(request, changeId);
    expect(initialItems.map((item) => item.category).sort()).toEqual(
      [policyACategory, policyBCategory].sort()
    );
    expect(initialItems.some((item) => item.category === 'other_insurance')).toBe(false);
    expect(initialItems.every((item) => item.status === 'pending')).toBe(true);

    await confirmItem(request, changeId, itemByCategory(initialItems, policyACategory).id, 'success');

    const afterSuccessItems = await getItems(request, changeId);
    expect(itemByCategory(afterSuccessItems, policyACategory).status).toBe('completed');
    expect(itemByCategory(afterSuccessItems, policyBCategory).status).toBe('pending');

    const afterSuccessChange = await getChange(request, changeId);
    expect(afterSuccessChange.status).toBe('pending');
    expect(Number(afterSuccessChange.other_insurance_processed)).toBe(0);
    expect(currentPersonnelPolicyIds(ctx.employeeSuccessId)).toEqual([ctx.policyIds[0]]);
    expect(currentDetailPolicyIds(ctx.employeeSuccessId)).toEqual([ctx.policyIds[0]]);

    await uploadGeneralAttachment(request, changeId);
    await confirmItem(request, changeId, itemByCategory(afterSuccessItems, policyBCategory).id, 'failed');

    const afterFailedItems = await getItems(request, changeId);
    expect(itemByCategory(afterFailedItems, policyACategory).status).toBe('completed');
    expect(itemByCategory(afterFailedItems, policyBCategory).status).toBe('failed');
    expect(currentDetailPolicyIds(ctx.employeeSuccessId)).toEqual([ctx.policyIds[0]]);

    const afterFailedChange = await getChange(request, changeId);
    expect(afterFailedChange.status).toBe('failed');
    expect(Number(afterFailedChange.fully_confirmed)).toBe(0);

    const carryoverRows = queryRows(`
      SELECT id, status
      FROM insurance_changes
      WHERE employee_id = ${ctx.employeeSuccessId}
        AND project_id = ${ctx.projectId}
        AND account_set_id = ${ctx.accountSetId}
        AND id <> ${changeId}
      ORDER BY id DESC
    `);
    expect(carryoverRows.length).toBe(1);

    const carryoverId = Number(carryoverRows[0][0]);
    expect(carryoverId).not.toBe(changeId);
    expect(carryoverRows[0][1]).toBe('pending');

    const carryoverItems = await getItems(request, carryoverId);
    expect(carryoverItems.map((item) => item.category)).toEqual([policyBCategory]);
    expect(carryoverItems[0].status).toBe('pending');
  });

  test('其他保险细分终结后不生成续办任务', async ({ request }) => {
    const changeId = seedInsuranceChange(ctx.employeeTerminatedId, `${PREFIX}-终结员工`);
    const policyACategory = `${OTHER_POLICY_PREFIX}${ctx.policyIds[0]}`;
    const policyBCategory = `${OTHER_POLICY_PREFIX}${ctx.policyIds[1]}`;

    const initialItems = await getItems(request, changeId);
    await uploadGeneralAttachment(request, changeId);
    await confirmItem(request, changeId, itemByCategory(initialItems, policyACategory).id, 'terminated');

    const afterTerminatedItems = await getItems(request, changeId);
    expect(itemByCategory(afterTerminatedItems, policyACategory).status).toBe('terminated');
    expect(itemByCategory(afterTerminatedItems, policyBCategory).status).toBe('pending');

    const change = await getChange(request, changeId);
    expect(change.status).toBe('pending');
    expect(currentPersonnelPolicyIds(ctx.employeeTerminatedId)).toEqual([]);
    expect(currentDetailPolicyIds(ctx.employeeTerminatedId)).toEqual([]);

    const carryoverRows = queryRows(`
      SELECT id
      FROM insurance_changes
      WHERE employee_id = ${ctx.employeeTerminatedId}
        AND project_id = ${ctx.projectId}
        AND account_set_id = ${ctx.accountSetId}
        AND id <> ${changeId}
      ORDER BY id DESC
    `);
    expect(carryoverRows).toEqual([]);
  });
});
