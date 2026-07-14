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
const PREFIX = `api-ins-gen-${RUN_ID}`;

interface SeedContext {
  userId: number;
  employeeUserId: number;
  accountSetId: number;
  projectId: number;
  socialRegionId: number;
  alternativeSocialRegionId: number;
  medicalRegionId: number;
  housingRegionId: number;
  housingConfigId: number;
  alternativeHousingRegionId: number;
  alternativeHousingConfigId: number;
  largeMedicalConfigId: number;
  policyIds: number[];
  token: string;
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

function columnExists(table: string, column: string): boolean {
  const rows = queryRows(`
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ${sqlValue(table)}
      AND COLUMN_NAME = ${sqlValue(column)}
  `);

  return Number(rows[0]?.[0] || 0) > 0;
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
  return `92${suffix}`;
}

function cleanupSeedData(): void {
  const accountSetSubquery = `SELECT id FROM account_sets WHERE code = ${sqlValue(`${PREFIX}-account-set`)}`;
  const userSubquery = `SELECT id FROM users WHERE email IN (${sqlValue(`${PREFIX}-admin@example.com`)}, ${sqlValue(`${PREFIX}-employee@example.com`)})`;
  const changeSubquery = `SELECT id FROM insurance_changes WHERE account_set_id IN (${accountSetSubquery})`;
  const projectSubquery = `SELECT id FROM projects WHERE account_set_id IN (${accountSetSubquery})`;
  const employeeSubquery = `SELECT id FROM employees WHERE account_set_id IN (${accountSetSubquery})`;
  const socialRegionSubquery = `SELECT id FROM social_security_regions WHERE account_set_id IN (${accountSetSubquery})`;
  const medicalRegionSubquery = `SELECT id FROM medical_insurance_regions WHERE account_set_id IN (${accountSetSubquery})`;
  const housingRegionSubquery = `SELECT id FROM housing_fund_regions WHERE account_set_id IN (${accountSetSubquery})`;
  const otherTypeSubquery = `SELECT id FROM other_insurance_types WHERE account_set_id IN (${accountSetSubquery})`;
  const policySubquery = `SELECT id FROM other_insurance_policies WHERE account_set_id IN (${accountSetSubquery})`;

  const statements = [
    `DELETE FROM insurance_change_attachments WHERE insurance_change_id IN (${changeSubquery})`,
    `DELETE FROM insurance_change_items WHERE insurance_change_id IN (${changeSubquery})`,
    `DELETE FROM insurance_detail_records WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM insurance_personnel WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM insurance_changes WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM employee_projects WHERE employee_id IN (${employeeSubquery}) OR project_id IN (${projectSubquery})`,
    `DELETE FROM project_role_users WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM project_other_insurance_policies WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM other_insurance_policies WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM other_insurance_types WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM large_medical_insurance_configs WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM housing_fund_configs WHERE region_id IN (${housingRegionSubquery}) OR account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM housing_fund_regions WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM medical_insurance_types WHERE region_id IN (${medicalRegionSubquery}) OR account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM medical_insurance_regions WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM social_security_types WHERE region_id IN (${socialRegionSubquery})`,
    `DELETE FROM social_security_regions WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM projects WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM employees WHERE account_set_id IN (${accountSetSubquery})`,
    `DELETE FROM account_set_users WHERE account_set_id IN (${accountSetSubquery}) OR user_id IN (${userSubquery})`,
    `DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\\\\Models\\\\User' AND tokenable_id IN (${userSubquery})`,
    `DELETE FROM users WHERE email IN (${sqlValue(`${PREFIX}-admin@example.com`)}, ${sqlValue(`${PREFIX}-employee@example.com`)})`,
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

  const body = await response.json();
  expect(response.status(), JSON.stringify(body)).toBe(200);
  expect(body.success).toBe(true);
  return {
    token: body.data.token,
    userId: Number(body.data.user.id),
  };
}

async function loginAsEmployee(request: APIRequestContext): Promise<string> {
  const response = await request.post(apiUrl('auth/login'), {
    data: {
      username: `${PREFIX}-employee@example.com`,
      password: USER_PASSWORD,
    },
  });

  const body = await response.json();
  expect(response.status(), JSON.stringify(body)).toBe(200);
  expect(body.success).toBe(true);
  return body.data.token;
}

async function seedBaseData(request: APIRequestContext): Promise<SeedContext> {
  cleanupSeedData();

  runSql(`
    INSERT INTO users (name, email, password, role, is_active, created_at, updated_at)
    VALUES (
      ${sqlValue(`${PREFIX}-admin`)},
      ${sqlValue(`${PREFIX}-admin@example.com`)},
      ${sqlValue(PASSWORD_HASH)},
      'admin',
      1,
      NOW(),
      NOW()
    )
  `);
  runSql(`
    INSERT INTO users (name, email, password, role, is_active, created_at, updated_at)
    VALUES (
      ${sqlValue(`${PREFIX}-employee`)},
      ${sqlValue(`${PREFIX}-employee@example.com`)},
      ${sqlValue(PASSWORD_HASH)},
      'employee',
      1,
      NOW(),
      NOW()
    )
  `);
  const userId = querySingleNumber(
    `SELECT id FROM users WHERE email = ${sqlValue(`${PREFIX}-admin@example.com`)} LIMIT 1`
  );
  const employeeUserId = querySingleNumber(
    `SELECT id FROM users WHERE email = ${sqlValue(`${PREFIX}-employee@example.com`)} LIMIT 1`
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
    WHERE id IN (${userId}, ${employeeUserId})
  `);
  runSql(`
    INSERT INTO account_set_users (account_set_id, user_id, role, is_default, created_at, updated_at)
    VALUES (${accountSetId}, ${userId}, 'owner', 1, NOW(), NOW())
  `);
  runSql(`
    INSERT INTO account_set_users (account_set_id, user_id, role, is_default, created_at, updated_at)
    VALUES (${accountSetId}, ${employeeUserId}, 'viewer', 1, NOW(), NOW())
  `);

  runSql(`
    INSERT INTO social_security_regions (
      name, code, company, min_base_amount, max_base_amount, account_set_id, created_by, created_at, updated_at
    ) VALUES (
      ${sqlValue(`${PREFIX}-社保地区`)},
      ${sqlValue(`${PREFIX}-SS`)},
      ${sqlValue(`${PREFIX}-公司`)},
      1000,
      30000,
      ${accountSetId},
      ${userId},
      NOW(),
      NOW()
    )
  `);
  const socialRegionId = querySingleNumber(
    `SELECT id FROM social_security_regions WHERE code = ${sqlValue(`${PREFIX}-SS`)} LIMIT 1`
  );
  runSql(`
    INSERT INTO social_security_types (
      region_id, name, base_amount, min_base_amount, max_base_amount, employee_ratio, company_ratio, created_by, created_at, updated_at
    ) VALUES (
      ${socialRegionId},
      ${sqlValue(`${PREFIX}-养老`)},
      5000,
      1000,
      30000,
      0.0800,
      0.1600,
      ${userId},
      NOW(),
      NOW()
    )
  `);

  runSql(`
    INSERT INTO social_security_regions (
      name, code, company, min_base_amount, max_base_amount, account_set_id, created_by, created_at, updated_at
    ) VALUES (
      ${sqlValue(`${PREFIX}-社保地区B`)},
      ${sqlValue(`${PREFIX}-SS-B`)},
      ${sqlValue(`${PREFIX}-公司`)},
      1000,
      30000,
      ${accountSetId},
      ${userId},
      NOW(),
      NOW()
    )
  `);
  const alternativeSocialRegionId = querySingleNumber(
    `SELECT id FROM social_security_regions WHERE code = ${sqlValue(`${PREFIX}-SS-B`)} LIMIT 1`
  );
  runSql(`
    INSERT INTO social_security_types (
      region_id, name, base_amount, min_base_amount, max_base_amount, employee_ratio, company_ratio, created_by, created_at, updated_at
    ) VALUES (
      ${alternativeSocialRegionId},
      ${sqlValue(`${PREFIX}-养老B`)},
      6500,
      1000,
      30000,
      0.0900,
      0.1700,
      ${userId},
      NOW(),
      NOW()
    )
  `);

  runSql(`
    INSERT INTO medical_insurance_regions (
      name, code, company, min_base_amount, max_base_amount, account_set_id, created_by, created_at, updated_at
    ) VALUES (
      ${sqlValue(`${PREFIX}-医保地区`)},
      ${sqlValue(`${PREFIX}-MI`)},
      ${sqlValue(`${PREFIX}-公司`)},
      1000,
      30000,
      ${accountSetId},
      ${userId},
      NOW(),
      NOW()
    )
  `);
  const medicalRegionId = querySingleNumber(
    `SELECT id FROM medical_insurance_regions WHERE code = ${sqlValue(`${PREFIX}-MI`)} LIMIT 1`
  );
  runSql(`
    INSERT INTO medical_insurance_types (
      region_id, name, min_base_amount, max_base_amount, employee_ratio, company_ratio, account_set_id, created_by, created_at, updated_at
    ) VALUES (
      ${medicalRegionId},
      ${sqlValue(`${PREFIX}-基本医保`)},
      1000,
      30000,
      0.0200,
      0.0800,
      ${accountSetId},
      ${userId},
      NOW(),
      NOW()
    )
  `);

  runSql(`
    INSERT INTO housing_fund_regions (
      region_name, account_number, company_name, account_set_id, created_by, created_at, updated_at
    ) VALUES (
      ${sqlValue(`${PREFIX}-公积金地区`)},
      ${sqlValue(`${PREFIX}-HF`)},
      ${sqlValue(`${PREFIX}-公司`)},
      ${accountSetId},
      ${userId},
      NOW(),
      NOW()
    )
  `);
  const housingRegionId = querySingleNumber(
    `SELECT id FROM housing_fund_regions WHERE account_number = ${sqlValue(`${PREFIX}-HF`)} LIMIT 1`
  );
  runSql(`
    INSERT INTO housing_fund_configs (
      region_id, config_name, base_amount, min_base_amount, max_base_amount, employee_ratio, company_ratio,
      is_default, account_set_id, created_by, created_at, updated_at
    ) VALUES (
      ${housingRegionId},
      ${sqlValue(`${PREFIX}-公积金配置`)},
      5000,
      1000,
      30000,
      0.0700,
      0.0700,
      1,
      ${accountSetId},
      ${userId},
      NOW(),
      NOW()
    )
  `);
  const housingConfigId = querySingleNumber(
    `SELECT id FROM housing_fund_configs WHERE config_name = ${sqlValue(`${PREFIX}-公积金配置`)} LIMIT 1`
  );

  runSql(`
    INSERT INTO housing_fund_regions (
      region_name, account_number, company_name, account_set_id, created_by, created_at, updated_at
    ) VALUES (
      ${sqlValue(`${PREFIX}-公积金地区B`)},
      ${sqlValue(`${PREFIX}-HF-B`)},
      ${sqlValue(`${PREFIX}-公司`)},
      ${accountSetId},
      ${userId},
      NOW(),
      NOW()
    )
  `);
  const alternativeHousingRegionId = querySingleNumber(
    `SELECT id FROM housing_fund_regions WHERE account_number = ${sqlValue(`${PREFIX}-HF-B`)} LIMIT 1`
  );
  runSql(`
    INSERT INTO housing_fund_configs (
      region_id, config_name, base_amount, min_base_amount, max_base_amount, employee_ratio, company_ratio,
      is_default, account_set_id, created_by, created_at, updated_at
    ) VALUES (
      ${alternativeHousingRegionId},
      ${sqlValue(`${PREFIX}-公积金配置B`)},
      6500,
      1000,
      30000,
      0.0800,
      0.0800,
      0,
      ${accountSetId},
      ${userId},
      NOW(),
      NOW()
    )
  `);
  const alternativeHousingConfigId = querySingleNumber(
    `SELECT id FROM housing_fund_configs WHERE config_name = ${sqlValue(`${PREFIX}-公积金配置B`)} LIMIT 1`
  );

  runSql(`
    INSERT INTO large_medical_insurance_configs (
      region_name, account_set_id, calculation_type, base_source, company_ratio, employee_ratio,
      payment_cycle, annual_payment_month, status, effective_date, created_by, created_at, updated_at
    ) VALUES (
      ${sqlValue(`${PREFIX}-医保地区`)},
      ${accountSetId},
      'base',
      'employee',
      0.0100,
      0.0050,
      'month',
      NULL,
      1,
      '2026-01-01',
      ${userId},
      NOW(),
      NOW()
    )
  `);
  const largeMedicalConfigId = querySingleNumber(
    `SELECT id FROM large_medical_insurance_configs WHERE region_name = ${sqlValue(`${PREFIX}-医保地区`)} LIMIT 1`
  );

  runSql(`
    INSERT INTO other_insurance_types (name, description, account_set_id, created_by, created_at, updated_at)
    VALUES (
      ${sqlValue(`${PREFIX}-商业险类型`)},
      ${sqlValue('API 测试商业险类型')},
      ${accountSetId},
      ${userId},
      NOW(),
      NOW()
    )
  `);
  const typeId = querySingleNumber(
    `SELECT id FROM other_insurance_types WHERE name = ${sqlValue(`${PREFIX}-商业险类型`)} LIMIT 1`
  );
  runSql(`
    INSERT INTO other_insurance_policies (
      type_id, policy_number, policy_name, insurance_company, coverage_amount,
      employee_per_capita_cost, quota, start_date, end_date, status, account_set_id, created_by, created_at, updated_at
    ) VALUES
      (
        ${typeId},
        ${sqlValue(`${PREFIX}-POLICY-A`)},
        ${sqlValue(`${PREFIX}-商业险A`)},
        ${sqlValue(`${PREFIX}-保险公司`)},
        10000,
        100,
        50,
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
        ${sqlValue(`${PREFIX}-商业险B`)},
        ${sqlValue(`${PREFIX}-保险公司`)},
        20000,
        200,
        50,
        '2026-01-01',
        '2026-12-31',
        'active',
        ${accountSetId},
        ${userId},
        NOW(),
        NOW()
      )
  `);
  const policyIds = queryRows(`
    SELECT id FROM other_insurance_policies
    WHERE account_set_id = ${accountSetId}
    ORDER BY id
  `).map((row) => Number(row[0]));

  runSql(`
    INSERT INTO projects (
      account_set_id, name, code, status, start_date, salary_payment_month, insurance_import_month,
      social_security_regions, medical_insurance_regions, housing_fund_regions,
      require_attendance, requires_attendance, requires_salary_basis, requires_attendance_basis,
      created_at, updated_at
    ) VALUES (
      ${accountSetId},
      ${sqlValue(`${PREFIX}-项目`)},
      ${sqlValue(`${PREFIX}-P`)},
      'active',
      '2026-01-01',
      'current',
      'current',
      ${sqlValue(JSON.stringify([socialRegionId, alternativeSocialRegionId]))},
      ${sqlValue(JSON.stringify([medicalRegionId]))},
      ${sqlValue(JSON.stringify([housingRegionId, alternativeHousingRegionId]))},
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

  for (const policyId of policyIds) {
    runSql(`
      INSERT INTO project_other_insurance_policies (project_id, policy_id, account_set_id, created_at, updated_at)
      VALUES (${projectId}, ${policyId}, ${accountSetId}, NOW(), NOW())
    `);
  }

  const loginResult = await login(request);

  return {
    userId,
    employeeUserId,
    accountSetId,
    projectId,
    socialRegionId,
    alternativeSocialRegionId,
    medicalRegionId,
    housingRegionId,
    housingConfigId,
    alternativeHousingRegionId,
    alternativeHousingConfigId,
    largeMedicalConfigId,
    policyIds,
    token: loginResult.token,
  };
}

function baseEmployeePayload(seed: number, name: string): Record<string, unknown> {
  return {
    current_account_set_id: ctx.accountSetId,
    name,
    id_number: idNumber(seed),
    phone: `139${String(seed).padStart(8, '0')}`,
    gender: seed % 2 === 0 ? 'female' : 'male',
    birth_date: '1990-01-01',
    hire_date: '2026-07-01',
    contract_start_date: '2026-07-01',
    contract_end_date: '2029-06-30',
    project_ids: [ctx.projectId],
    employee_number: `${PREFIX}-EMP-${seed}`,
  };
}

function fullInsurancePayload(): Record<string, unknown> {
  return {
    social_security_region_id: ctx.socialRegionId,
    social_security_base: 6000,
    social_insurance_enrollment_date: '2026-07-01',
    medical_insurance_region_id: ctx.medicalRegionId,
    medical_insurance_base: 6000,
    medical_insurance_enrollment_date: '2026-07-01',
    housing_fund_region_id: ctx.housingRegionId,
    housing_fund_config_id: ctx.housingConfigId,
    housing_fund_base: 6000,
    provident_fund_enrollment_date: '2026-07-01',
    other_insurance_policy_ids: ctx.policyIds,
  };
}

async function createEmployee(request: APIRequestContext, payload: Record<string, unknown>): Promise<number> {
  const response = await request.post(apiUrl('employees'), {
    headers: authHeaders(),
    data: payload,
  });
  const body = await response.json();
  expect(response.status(), JSON.stringify(body)).toBe(200);
  expect(body.success).toBe(true);
  return Number(body.data.id);
}

async function updateEmployee(
  request: APIRequestContext,
  employeeId: number,
  payload: Record<string, unknown>
): Promise<void> {
  const response = await request.put(apiUrl(`employees/${employeeId}`), {
    headers: authHeaders(),
    data: payload,
  });
  const body = await response.json();
  expect(response.status(), JSON.stringify(body)).toBe(200);
  expect(body.success).toBe(true);
}

function getDbChangeRows(employeeId: number): string[][] {
  return queryRows(`
    SELECT id, change_type, status, social_security_region_id, medical_insurance_region_id,
           housing_fund_region_id, housing_fund_config_id, large_medical_insurance_config_id,
           other_insurance_policies, change_details, employee_name, fully_confirmed
    FROM insurance_changes
    WHERE employee_id = ${employeeId}
      AND project_id = ${ctx.projectId}
      AND account_set_id = ${ctx.accountSetId}
    ORDER BY id
  `);
}

function getDbItemRows(changeId: number): string[][] {
  return queryRows(`
    SELECT category, status
    FROM insurance_change_items
    WHERE insurance_change_id = ${changeId}
    ORDER BY category
  `);
}

async function syncAndGetItems(request: APIRequestContext, changeId: number): Promise<any[]> {
  const response = await request.get(apiUrl(`insurance-changes/${changeId}/items`), {
    headers: authHeaders(),
  });
  const body = await response.json();
  expect(response.status(), JSON.stringify(body)).toBe(200);
  expect(body.success).toBe(true);
  return body.data;
}

async function processCategories(
  request: APIRequestContext,
  changeId: number,
  categories: string[]
): Promise<void> {
  const response = await request.put(apiUrl(`insurance-changes/${changeId}/confirm-process`), {
    headers: authHeaders(),
    data: { categories, result: 'success' },
  });
  const body = await response.json();
  expect(response.status(), JSON.stringify(body)).toBe(200);
  expect(body.success).toBe(true);
}

async function listVisibleChanges(request: APIRequestContext): Promise<any[]> {
  const response = await request.get(apiUrl('insurance-changes'), {
    headers: authHeaders(),
    params: { account_set_id: ctx.accountSetId },
  });
  const body = await response.json();
  expect(response.status(), JSON.stringify(body)).toBe(200);
  expect(body.success).toBe(true);
  return body.data;
}

function expectCategories(actual: string[], expected: string[]): void {
  expect(actual.sort()).toEqual(expected.sort());
}

test.describe.serial('人员档案保险信息触发增减任务 API 回归', () => {
  test.beforeAll(async ({ request }) => {
    ctx = await seedBaseData(request);
  });

  test.afterAll(async () => {
    cleanupSeedData();
  });

  test('创建员工时直接填写全部保险信息，应生成一条增加任务并拆分所有险种子任务', async ({ request }) => {
    const employeeId = await createEmployee(request, {
      ...baseEmployeePayload(1, `${PREFIX}-创建即参保`),
      ...fullInsurancePayload(),
    });

    const rows = getDbChangeRows(employeeId);
    expect(rows.length).toBe(1);
    const changeId = Number(rows[0][0]);
    expect(rows[0][1]).toBe('increase');
    expect(rows[0][2]).toBe('pending');
    expect(Number(rows[0][3])).toBe(ctx.socialRegionId);
    expect(Number(rows[0][4])).toBe(ctx.medicalRegionId);
    expect(Number(rows[0][5])).toBe(ctx.housingRegionId);
    expect(Number(rows[0][6])).toBe(ctx.housingConfigId);
    expect(Number(rows[0][7])).toBe(ctx.largeMedicalConfigId);

    const items = await syncAndGetItems(request, changeId);
    expectCategories(
      items.map((item: any) => item.category),
      [
        'social_security',
        'medical_insurance',
        'housing_fund',
        'large_medical_insurance',
        `other_policy:${ctx.policyIds[0]}`,
        `other_policy:${ctx.policyIds[1]}`,
      ]
    );

    const visible = await listVisibleChanges(request);
    expect(visible.some((item: any) => Number(item.id) === changeId)).toBe(true);
  });

  test('创建员工时不填保险，后续编辑补全保险信息，应生成增加任务', async ({ request }) => {
    const employeeId = await createEmployee(request, baseEmployeePayload(2, `${PREFIX}-后补参保`));
    expect(getDbChangeRows(employeeId)).toHaveLength(0);

    await updateEmployee(request, employeeId, {
      current_account_set_id: ctx.accountSetId,
      ...fullInsurancePayload(),
    });

    const rows = getDbChangeRows(employeeId);
    expect(rows.length).toBe(1);
    const changeId = Number(rows[0][0]);
    expect(rows[0][1]).toBe('increase');
    expect(rows[0][2]).toBe('pending');
    expect(Number(rows[0][7])).toBe(ctx.largeMedicalConfigId);

    const items = await syncAndGetItems(request, changeId);
    expectCategories(
      items.map((item: any) => item.category),
      [
        'social_security',
        'medical_insurance',
        'housing_fund',
        'large_medical_insurance',
        `other_policy:${ctx.policyIds[0]}`,
        `other_policy:${ctx.policyIds[1]}`,
      ]
    );
  });

  test('后续只补社保，应只生成社保增加任务，不应误带医保公积金', async ({ request }) => {
    const employeeId = await createEmployee(request, baseEmployeePayload(3, `${PREFIX}-只补社保`));

    await updateEmployee(request, employeeId, {
      current_account_set_id: ctx.accountSetId,
      social_security_region_id: ctx.socialRegionId,
      social_security_base: 7000,
      social_insurance_enrollment_date: '2026-07-01',
    });

    const rows = getDbChangeRows(employeeId);
    expect(rows.length).toBe(1);
    const changeId = Number(rows[0][0]);

    const items = await syncAndGetItems(request, changeId);
    expect(items.map((item: any) => item.category)).toEqual(['social_security']);
  });

  test('已有未完成任务时分两次补不同险种，应复用同一条任务并追加子任务', async ({ request }) => {
    const employeeId = await createEmployee(request, baseEmployeePayload(4, `${PREFIX}-分次补参保`));

    await updateEmployee(request, employeeId, {
      current_account_set_id: ctx.accountSetId,
      social_security_region_id: ctx.socialRegionId,
      social_security_base: 8000,
      social_insurance_enrollment_date: '2026-07-01',
    });

    const firstRows = getDbChangeRows(employeeId);
    expect(firstRows.length).toBe(1);
    const changeId = Number(firstRows[0][0]);
    let items = await syncAndGetItems(request, changeId);
    expect(items.map((item: any) => item.category)).toEqual(['social_security']);

    await updateEmployee(request, employeeId, {
      current_account_set_id: ctx.accountSetId,
      medical_insurance_region_id: ctx.medicalRegionId,
      medical_insurance_base: 8000,
      medical_insurance_enrollment_date: '2026-07-01',
    });

    const secondRows = getDbChangeRows(employeeId);
    expect(secondRows.length).toBe(1);
    expect(Number(secondRows[0][0])).toBe(changeId);
    items = await syncAndGetItems(request, changeId);
    expectCategories(
      items.map((item: any) => item.category),
      ['social_security', 'medical_insurance', 'large_medical_insurance']
    );
  });

  test('同月部分险种已成功后修改其他险种，应复用原任务并只重置发生变化的险种', async ({ request }) => {
    const employeeName = `${PREFIX}-同月复用任务`;
    const employeeId = await createEmployee(request, {
      ...baseEmployeePayload(9, employeeName),
      social_security_region_id: ctx.socialRegionId,
      social_security_base: 6000,
      social_insurance_enrollment_date: '2026-07-01',
      housing_fund_region_id: ctx.housingRegionId,
      housing_fund_config_id: ctx.housingConfigId,
      housing_fund_base: 6000,
      provident_fund_enrollment_date: '2026-07-01',
    });

    const initialRows = getDbChangeRows(employeeId);
    expect(initialRows).toHaveLength(1);
    const changeId = Number(initialRows[0][0]);
    let items = await syncAndGetItems(request, changeId);
    expectCategories(items.map((item: any) => item.category), ['social_security', 'housing_fund']);

    await processCategories(request, changeId, ['social_security']);
    items = await syncAndGetItems(request, changeId);
    expect(Object.fromEntries(items.map((item: any) => [item.category, item.status]))).toEqual({
      housing_fund: 'pending',
      social_security: 'completed',
    });

    await updateEmployee(request, employeeId, {
      current_account_set_id: ctx.accountSetId,
      housing_fund_region_id: ctx.alternativeHousingRegionId,
      housing_fund_config_id: ctx.alternativeHousingConfigId,
      housing_fund_base: 6500,
      provident_fund_enrollment_date: '2026-07-01',
    });

    let rows = getDbChangeRows(employeeId);
    expect(rows).toHaveLength(1);
    expect(Number(rows[0][0])).toBe(changeId);
    expect(Number(rows[0][5])).toBe(ctx.alternativeHousingRegionId);
    expect(Number(rows[0][6])).toBe(ctx.alternativeHousingConfigId);
    expect(rows[0][10]).toBe(employeeName);

    items = await syncAndGetItems(request, changeId);
    expect(Object.fromEntries(items.map((item: any) => [item.category, item.status]))).toEqual({
      housing_fund: 'pending',
      social_security: 'completed',
    });

    await processCategories(request, changeId, ['housing_fund']);
    rows = getDbChangeRows(employeeId);
    expect(rows[0][2]).toBe('completed');
    expect(Number(rows[0][11])).toBe(1);

    await updateEmployee(request, employeeId, {
      current_account_set_id: ctx.accountSetId,
      social_security_region_id: ctx.alternativeSocialRegionId,
      social_security_base: 6500,
      social_insurance_enrollment_date: '2026-07-01',
    });

    rows = getDbChangeRows(employeeId);
    expect(rows).toHaveLength(1);
    expect(Number(rows[0][0])).toBe(changeId);
    expect(rows[0][2]).toBe('pending');
    expect(Number(rows[0][3])).toBe(ctx.alternativeSocialRegionId);
    expect(Number(rows[0][11])).toBe(0);

    items = await syncAndGetItems(request, changeId);
    expect(Object.fromEntries(items.map((item: any) => [item.category, item.status]))).toEqual({
      housing_fund: 'completed',
      social_security: 'pending',
    });

    const visible = await listVisibleChanges(request);
    const visibleChange = visible.find((item: any) => Number(item.id) === changeId);
    expect(visibleChange?.employee_name).toBe(employeeName);
  });

  test('只修改已有绑定的基数，在没有参保人员记录时仍应沉淀为待处理任务', async ({ request }) => {
    const employeeId = await createEmployee(request, {
      ...baseEmployeePayload(5, `${PREFIX}-改基数`),
      social_security_region_id: ctx.socialRegionId,
      social_security_base: 5000,
      social_insurance_enrollment_date: '2026-07-01',
    });

    const initialRows = getDbChangeRows(employeeId);
    expect(initialRows.length).toBe(1);
    await syncAndGetItems(request, Number(initialRows[0][0]));

    await updateEmployee(request, employeeId, {
      current_account_set_id: ctx.accountSetId,
      social_security_base: 9000,
    });

    const rows = getDbChangeRows(employeeId);
    expect(rows.length).toBe(1);
    const items = await syncAndGetItems(request, Number(rows[0][0]));
    expect(items.map((item: any) => item.category)).toEqual(['social_security']);
  });

  test('已有参保人员记录后只改基数，会直接同步参保数据，不生成新的待处理任务', async ({ request }) => {
    const employeeId = await createEmployee(request, {
      ...baseEmployeePayload(6, `${PREFIX}-已有参保改基数`),
      social_security_region_id: ctx.socialRegionId,
      social_security_base: 5000,
      social_insurance_enrollment_date: '2026-07-01',
    });

    const rows = getDbChangeRows(employeeId);
    expect(rows.length).toBe(1);
    const changeId = Number(rows[0][0]);
    await syncAndGetItems(request, changeId);

    runSql(`
      INSERT INTO insurance_personnel (
        employee_id, employee_name, employee_id_number, employee_gender, employee_birth_date, employee_phone,
        project_id, account_set_id, social_security_region_id, employee_social_security_base,
        social_security_types, status, first_confirmation_date, last_updated_at, created_at, updated_at
      )
      SELECT
        employee_id, employee_name, employee_id_number, employee_gender, employee_birth_date, employee_phone,
        project_id, account_set_id, social_security_region_id, employee_social_security_base,
        social_security_types, 'active', CURDATE(), NOW(), NOW(), NOW()
      FROM insurance_changes
      WHERE id = ${changeId}
    `);
    runSql(`UPDATE insurance_changes SET status = 'completed', completed_at = NOW() WHERE id = ${changeId}`);

    await updateEmployee(request, employeeId, {
      current_account_set_id: ctx.accountSetId,
      social_security_base: 9500,
    });

    const afterRows = getDbChangeRows(employeeId);
    expect(afterRows.length).toBe(1);
    expect(afterRows[0][2]).toBe('completed');

    const syncedBase = querySingleNumber(`
      SELECT employee_social_security_base
      FROM insurance_personnel
      WHERE employee_id = ${employeeId}
        AND account_set_id = ${ctx.accountSetId}
      ORDER BY id DESC
      LIMIT 1
    `);
    expect(syncedBase).toBe(9500);
  });

  test('非管理员没有配置项目保险负责人时，任务已生成但列表不可见；配置后可见', async ({ request }) => {
    const employeeToken = await loginAsEmployee(request);
    const employeeId = await createEmployee(request, {
      ...baseEmployeePayload(7, `${PREFIX}-负责人过滤`),
      social_security_region_id: ctx.socialRegionId,
      social_security_base: 5000,
      social_insurance_enrollment_date: '2026-07-01',
    });

    const rows = getDbChangeRows(employeeId);
    expect(rows.length).toBe(1);
    const changeId = Number(rows[0][0]);

    const invisibleResponse = await request.get(apiUrl('insurance-changes'), {
      headers: authHeaders(employeeToken),
      params: { account_set_id: ctx.accountSetId },
    });
    const invisibleBody = await invisibleResponse.json();
    expect(invisibleResponse.status(), JSON.stringify(invisibleBody)).toBe(200);
    expect(invisibleBody.success).toBe(true);
    expect(invisibleBody.data.some((item: any) => Number(item.id) === changeId)).toBe(false);

    runSql(`
      INSERT INTO project_role_users (account_set_id, project_id, role_type, user_id, created_at, updated_at)
      VALUES (${ctx.accountSetId}, ${ctx.projectId}, 'insurance', ${ctx.employeeUserId}, NOW(), NOW())
    `);

    const visibleResponse = await request.get(apiUrl('insurance-changes'), {
      headers: authHeaders(employeeToken),
      params: { account_set_id: ctx.accountSetId },
    });
    const visibleBody = await visibleResponse.json();
    expect(visibleResponse.status(), JSON.stringify(visibleBody)).toBe(200);
    expect(visibleBody.success).toBe(true);
    expect(visibleBody.data.some((item: any) => Number(item.id) === changeId)).toBe(true);
  });

  test('月份筛选不匹配时，任务已生成但列表查不到', async ({ request }) => {
    const employeeId = await createEmployee(request, {
      ...baseEmployeePayload(8, `${PREFIX}-月份过滤`),
      social_security_region_id: ctx.socialRegionId,
      social_security_base: 5000,
      social_insurance_enrollment_date: '2026-07-01',
    });

    const rows = getDbChangeRows(employeeId);
    expect(rows.length).toBe(1);
    const changeId = Number(rows[0][0]);
    const monthExpression = columnExists('insurance_changes', 'task_month')
      ? 'task_month'
      : "DATE_FORMAT(created_at, '%Y-%m')";
    const currentTaskMonth = queryRows(`
      SELECT ${monthExpression}
      FROM insurance_changes
      WHERE id = ${changeId}
      LIMIT 1
    `)[0][0];
    const wrongMonth = currentTaskMonth === '2026-06' ? '2026-05' : '2026-06';

    const wrongResponse = await request.get(apiUrl('insurance-changes'), {
      headers: authHeaders(),
      params: { account_set_id: ctx.accountSetId, month: wrongMonth },
    });
    const wrongBody = await wrongResponse.json();
    expect(wrongResponse.status(), JSON.stringify(wrongBody)).toBe(200);
    expect(wrongBody.success).toBe(true);
    expect(wrongBody.data.some((item: any) => Number(item.id) === changeId)).toBe(false);

    const rightResponse = await request.get(apiUrl('insurance-changes'), {
      headers: authHeaders(),
      params: { account_set_id: ctx.accountSetId, month: currentTaskMonth },
    });
    const rightBody = await rightResponse.json();
    expect(rightResponse.status(), JSON.stringify(rightBody)).toBe(200);
    expect(rightBody.success).toBe(true);
    expect(rightBody.data.some((item: any) => Number(item.id) === changeId)).toBe(true);
  });
});
