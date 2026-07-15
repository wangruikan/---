import { test, expect, APIRequestContext } from '@playwright/test';
import { execFileSync } from 'child_process';

const DB_HOST = process.env.DB_HOST || '127.0.0.1';
const DB_PORT = process.env.DB_PORT || '3306';
const DB_DATABASE = process.env.DB_DATABASE || 'weiqing';
const DB_USERNAME = process.env.DB_USERNAME || 'root';
const DB_PASSWORD = process.env.DB_PASSWORD || 'root';
const BASE_URL = process.env.API_BASE_URL || 'http://localhost:8000/api';
const PASSWORD_HASH =
  process.env.E2E_ROLE_TEST_PASSWORD_HASH ||
  '$2y$10$vatwVgpxXSnmzxkxog.2s.M.cW8G60pnlKlytftZnVbyQqrLJavjW';
const USER_PASSWORD = process.env.E2E_ROLE_TEST_PASSWORD || 'Pass123456';

const RUN_ID = String(Date.now());
const PREFIX = `api-role-test-${RUN_ID}`;

type UserKey = 'admin' | 'manager' | 'insurance' | 'salary' | 'delivery' | 'outsider';

interface TestContext {
  accountSetId: number;
  projectIds: {
    managed: number;
    other: number;
  };
  userIds: Record<UserKey, number>;
  logins: Record<UserKey, string>;
}

interface LoginResult {
  token: string;
  userId: number;
}

interface RoleUsersResponse {
  success: boolean;
  data: {
    project_id: number;
    project_name: string;
    roles: Record<
      string,
      {
        label: string;
        user_ids: number[];
        users: Array<{ id: number; name: string }>;
      }
    >;
  };
}

const ctx: Partial<TestContext> = {};
const tokens: Partial<Record<UserKey, string>> = {};

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

function sqlValue(value: string | number | null): string {
  if (value === null) {
    return 'NULL';
  }

  if (typeof value === 'number') {
    return String(value);
  }

  return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function loginName(key: UserKey): string {
  return `${PREFIX}-${key}`;
}

function loginEmail(key: UserKey): string {
  return `${loginName(key)}@example.com`;
}

function apiUrl(path: string): string {
  return `${BASE_URL}/${path.replace(/^\/+/, '')}`;
}

async function login(request: APIRequestContext, username: string): Promise<LoginResult> {
  const response = await request.post(apiUrl('auth/login'), {
    data: {
      username,
      password: USER_PASSWORD,
    },
  });

  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(data.success).toBe(true);

  return {
    token: data.data.token,
    userId: Number(data.data.user.id),
  };
}

function authHeaders(token: string, accountSetId: number): Record<string, string> {
  return {
    Authorization: `Bearer ${token}`,
    'X-Account-Set-Id': String(accountSetId),
    Accept: 'application/json',
  };
}

function buildCleanupSql(): string[] {
  if (!ctx.accountSetId) {
    return [
      `DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\\\\Models\\\\User' AND tokenable_id IN (SELECT id FROM users WHERE email LIKE '${PREFIX}%@example.com')`,
      `DELETE FROM users WHERE email LIKE '${PREFIX}%@example.com'`,
      `DELETE FROM account_sets WHERE code = '${PREFIX}-account-set'`,
    ];
  }

  return [
    `DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\\\\Models\\\\User' AND tokenable_id IN (SELECT id FROM users WHERE email LIKE '${PREFIX}%@example.com')`,
    `DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE email LIKE '${PREFIX}%@example.com')`,
    `DELETE FROM pending_tasks WHERE account_set_id = ${ctx.accountSetId}`,
    `DELETE FROM document_delivery_attachments WHERE delivery_id IN (SELECT id FROM document_deliveries WHERE account_set_id = ${ctx.accountSetId})`,
    `DELETE FROM document_delivery_items WHERE delivery_id IN (SELECT id FROM document_deliveries WHERE account_set_id = ${ctx.accountSetId})`,
    `DELETE FROM document_delivery_reminders WHERE delivery_id IN (SELECT id FROM document_deliveries WHERE account_set_id = ${ctx.accountSetId})`,
    `DELETE FROM document_deliveries WHERE account_set_id = ${ctx.accountSetId}`,
    `DELETE FROM basis_records WHERE account_set_id = ${ctx.accountSetId}`,
    `DELETE FROM project_role_users WHERE account_set_id = ${ctx.accountSetId}`,
    `DELETE FROM account_set_users WHERE account_set_id = ${ctx.accountSetId}`,
    `DELETE FROM projects WHERE account_set_id = ${ctx.accountSetId}`,
    `DELETE FROM users WHERE email LIKE '${PREFIX}%@example.com'`,
    `DELETE FROM account_sets WHERE id = ${ctx.accountSetId}`,
  ];
}

function cleanupSeedData(): void {
  for (const sql of buildCleanupSql()) {
    runSql(sql);
  }
}

function seedUsers(): Record<UserKey, number> {
  runSql(`
    INSERT INTO users (
      name, nickname, email, password, role, account_set_id, current_account_set_id, is_active, created_at, updated_at
    ) VALUES
      (${sqlValue(loginName('admin'))}, NULL, ${sqlValue(loginEmail('admin'))}, ${sqlValue(PASSWORD_HASH)}, 'admin', NULL, NULL, 1, NOW(), NOW()),
      (${sqlValue(loginName('manager'))}, NULL, ${sqlValue(loginEmail('manager'))}, ${sqlValue(PASSWORD_HASH)}, 'employee', NULL, NULL, 1, NOW(), NOW()),
      (${sqlValue(loginName('insurance'))}, NULL, ${sqlValue(loginEmail('insurance'))}, ${sqlValue(PASSWORD_HASH)}, 'employee', NULL, NULL, 1, NOW(), NOW()),
      (${sqlValue(loginName('salary'))}, NULL, ${sqlValue(loginEmail('salary'))}, ${sqlValue(PASSWORD_HASH)}, 'employee', NULL, NULL, 1, NOW(), NOW()),
      (${sqlValue(loginName('delivery'))}, NULL, ${sqlValue(loginEmail('delivery'))}, ${sqlValue(PASSWORD_HASH)}, 'employee', NULL, NULL, 1, NOW(), NOW()),
      (${sqlValue(loginName('outsider'))}, NULL, ${sqlValue(loginEmail('outsider'))}, ${sqlValue(PASSWORD_HASH)}, 'employee', NULL, NULL, 1, NOW(), NOW())
  `);

  const rows = queryRows(`
    SELECT email, id
    FROM users
    WHERE email IN (
      ${sqlValue(loginEmail('admin'))},
      ${sqlValue(loginEmail('manager'))},
      ${sqlValue(loginEmail('insurance'))},
      ${sqlValue(loginEmail('salary'))},
      ${sqlValue(loginEmail('delivery'))},
      ${sqlValue(loginEmail('outsider'))}
    )
  `);

  const map = Object.fromEntries(rows.map(([email, id]) => [email, Number(id)]));

  return {
    admin: map[loginEmail('admin')],
    manager: map[loginEmail('manager')],
    insurance: map[loginEmail('insurance')],
    salary: map[loginEmail('salary')],
    delivery: map[loginEmail('delivery')],
    outsider: map[loginEmail('outsider')],
  };
}

function seedAccountSet(adminUserId: number): number {
  runSql(`
    INSERT INTO account_sets (
      name, code, status, created_by, created_at, updated_at
    ) VALUES (
      ${sqlValue(`${PREFIX}-account-set`)},
      ${sqlValue(`${PREFIX}-account-set`)},
      'active',
      ${adminUserId},
      NOW(),
      NOW()
    )
  `);

  return querySingleNumber(`
    SELECT id
    FROM account_sets
    WHERE code = ${sqlValue(`${PREFIX}-account-set`)}
    LIMIT 1
  `);
}

function seedAccountSetUsers(accountSetId: number, userIds: Record<UserKey, number>): void {
  runSql(`
    UPDATE users
    SET account_set_id = ${accountSetId}, current_account_set_id = ${accountSetId}, updated_at = NOW()
    WHERE id IN (${Object.values(userIds).join(',')})
  `);

  runSql(`
    INSERT INTO account_set_users (
      account_set_id, user_id, role, approval_level, approval_level_name, is_default, created_at, updated_at
    ) VALUES
      (${accountSetId}, ${userIds.admin}, 'owner', NULL, NULL, 1, NOW(), NOW()),
      (${accountSetId}, ${userIds.manager}, 'viewer', NULL, NULL, 1, NOW(), NOW()),
      (${accountSetId}, ${userIds.insurance}, 'viewer', NULL, NULL, 1, NOW(), NOW()),
      (${accountSetId}, ${userIds.salary}, 'viewer', NULL, NULL, 1, NOW(), NOW()),
      (${accountSetId}, ${userIds.delivery}, 'viewer', NULL, NULL, 1, NOW(), NOW()),
      (${accountSetId}, ${userIds.outsider}, 'viewer', NULL, NULL, 1, NOW(), NOW())
  `);
}

function seedProjects(accountSetId: number): { managed: number; other: number } {
  runSql(`
    INSERT INTO projects (
      account_set_id, name, code, status, salary_payment_month, insurance_import_month,
      require_attendance, requires_attendance, requires_salary_basis, requires_attendance_basis,
      created_at, updated_at
    ) VALUES
      (${accountSetId}, ${sqlValue(`${PREFIX}-managed-project`)}, ${sqlValue(`${PREFIX}-P1`)}, 'active', 'current', 'current', 1, 1, 0, 0, NOW(), NOW()),
      (${accountSetId}, ${sqlValue(`${PREFIX}-other-project`)}, ${sqlValue(`${PREFIX}-P2`)}, 'active', 'current', 'current', 1, 1, 0, 0, NOW(), NOW())
  `);

  const rows = queryRows(`
    SELECT code, id
    FROM projects
    WHERE code IN (${sqlValue(`${PREFIX}-P1`)}, ${sqlValue(`${PREFIX}-P2`)})
  `);
  const map = Object.fromEntries(rows.map(([code, id]) => [code, Number(id)]));

  return {
    managed: map[`${PREFIX}-P1`],
    other: map[`${PREFIX}-P2`],
  };
}

function seedPendingDeliveries(accountSetId: number, projectIds: { managed: number; other: number }, handlerId: number): void {
  runSql(`
    INSERT INTO document_deliveries (
      config_id, account_set_id, project_id, delivery_cycle, delivery_method, delivery_release_month,
      delivery_period, display_month, status, handler_id, required_documents, created_at, updated_at
    ) VALUES
      (NULL, ${accountSetId}, ${projectIds.managed}, 'monthly', 'electronic', 'current', '2099-01', '2099-01', 'pending', ${handlerId}, ${sqlValue('["负责人测试资料A"]')}, NOW(), NOW()),
      (NULL, ${accountSetId}, ${projectIds.other}, 'monthly', 'electronic', 'current', '2099-01', '2099-01', 'pending', ${handlerId}, ${sqlValue('["负责人测试资料B"]')}, NOW(), NOW())
  `);
}

async function seedTestContext(request: APIRequestContext): Promise<void> {
  cleanupSeedData();

  const userIds = seedUsers();
  const accountSetId = seedAccountSet(userIds.admin);
  seedAccountSetUsers(accountSetId, userIds);
  const projectIds = seedProjects(accountSetId);
  seedPendingDeliveries(accountSetId, projectIds, userIds.delivery);

  ctx.accountSetId = accountSetId;
  ctx.userIds = userIds;
  ctx.projectIds = projectIds;
  ctx.logins = {
    admin: loginEmail('admin'),
    manager: loginEmail('manager'),
    insurance: loginEmail('insurance'),
    salary: loginEmail('salary'),
    delivery: loginEmail('delivery'),
    outsider: loginEmail('outsider'),
  };

  for (const key of Object.keys(ctx.logins) as UserKey[]) {
    const result = await login(request, ctx.logins[key]);
    tokens[key] = result.token;
    expect(result.userId).toBe(ctx.userIds[key]);
  }
}

test.describe.serial('项目 4 负责人 API 测试', () => {
  test.beforeAll(async ({ request }) => {
    await seedTestContext(request);
  });

  test.afterAll(async () => {
    cleanupSeedData();
  });

  test('项目人员候选列表应返回当前账套的启用用户', async ({ request }) => {
    const response = await request.get(apiUrl('users'), {
      headers: authHeaders(tokens.admin!, ctx.accountSetId!),
      params: {
        all: 'true',
        current_account_set_only: true,
        current_account_set_id: ctx.accountSetId,
        is_active: true,
      },
    });

    expect(response.status()).toBe(200);
    const data = await response.json();
    expect(data.success).toBe(true);
    expect(Array.isArray(data.data)).toBe(true);
    expect(new Set(data.data.map((user: any) => Number(user.id)))).toEqual(
      new Set(Object.values(ctx.userIds!))
    );
  });

  test('管理员可以配置 4 个负责人位置', async ({ request }) => {
    const response = await request.post(apiUrl(`projects/${ctx.projectIds!.managed}/role-users`), {
      headers: authHeaders(tokens.admin!, ctx.accountSetId!),
      data: {
        current_account_set_id: ctx.accountSetId,
        role_manager_user_ids: [ctx.userIds!.manager],
        insurance_user_ids: [ctx.userIds!.insurance],
        salary_user_ids: [ctx.userIds!.salary],
        delivery_user_ids: [ctx.userIds!.delivery],
      },
    });

    expect(response.status()).toBe(200);
    const data = (await response.json()) as RoleUsersResponse;
    expect(data.success).toBe(true);
    expect(data.data.roles.role_manager.user_ids).toEqual([ctx.userIds!.manager]);
    expect(data.data.roles.insurance.user_ids).toEqual([ctx.userIds!.insurance]);
    expect(data.data.roles.salary.user_ids).toEqual([ctx.userIds!.salary]);
    expect(data.data.roles.delivery.user_ids).toEqual([ctx.userIds!.delivery]);
  });

  test('负责人设置人可以查看并保存 4 个负责人配置', async ({ request }) => {
    const getResponse = await request.get(apiUrl(`projects/${ctx.projectIds!.managed}/role-users`), {
      headers: authHeaders(tokens.manager!, ctx.accountSetId!),
      params: {
        current_account_set_id: ctx.accountSetId,
      },
    });

    expect(getResponse.status()).toBe(200);
    const getData = (await getResponse.json()) as RoleUsersResponse;
    expect(getData.success).toBe(true);
    expect(getData.data.roles.role_manager.user_ids).toContain(ctx.userIds!.manager);

    const saveResponse = await request.post(apiUrl(`projects/${ctx.projectIds!.managed}/role-users`), {
      headers: authHeaders(tokens.manager!, ctx.accountSetId!),
      data: {
        current_account_set_id: ctx.accountSetId,
        role_manager_user_ids: [ctx.userIds!.manager],
        insurance_user_ids: [ctx.userIds!.insurance],
        salary_user_ids: [ctx.userIds!.salary],
        delivery_user_ids: [ctx.userIds!.delivery],
      },
    });

    expect(saveResponse.status()).toBe(200);
    const saveData = (await saveResponse.json()) as RoleUsersResponse;
    expect(saveData.success).toBe(true);
  });

  test('非负责人设置人不能维护负责人配置', async ({ request }) => {
    const getResponse = await request.get(apiUrl(`projects/${ctx.projectIds!.managed}/role-users`), {
      headers: authHeaders(tokens.outsider!, ctx.accountSetId!),
      params: {
        current_account_set_id: ctx.accountSetId,
      },
    });
    expect(getResponse.status()).toBe(403);
    const getData = await getResponse.json();
    expect(getData.message).toContain('负责人设置人');

    const saveResponse = await request.post(apiUrl(`projects/${ctx.projectIds!.managed}/role-users`), {
      headers: authHeaders(tokens.salary!, ctx.accountSetId!),
      data: {
        current_account_set_id: ctx.accountSetId,
        role_manager_user_ids: [ctx.userIds!.manager],
        insurance_user_ids: [ctx.userIds!.insurance],
        salary_user_ids: [ctx.userIds!.salary],
        delivery_user_ids: [ctx.userIds!.delivery],
      },
    });
    expect(saveResponse.status()).toBe(403);
  });

  test('项目列表的 can_manage_role_users 仅对管理员和负责人设置人生效', async ({ request }) => {
    const managerResponse = await request.get(apiUrl('projects'), {
      headers: authHeaders(tokens.manager!, ctx.accountSetId!),
      params: {
        all: 'true',
        current_account_set_id: ctx.accountSetId,
      },
    });
    expect(managerResponse.status()).toBe(200);
    const managerData = await managerResponse.json();
    const managedProjectForManager = managerData.data.data.find(
      (item: any) => item.id === ctx.projectIds!.managed
    );
    expect(managedProjectForManager).toBeTruthy();
    expect(Boolean(managedProjectForManager.can_manage_role_users)).toBe(true);

    const outsiderResponse = await request.get(apiUrl('projects'), {
      headers: authHeaders(tokens.outsider!, ctx.accountSetId!),
      params: {
        all: 'true',
        current_account_set_id: ctx.accountSetId,
      },
    });
    expect(outsiderResponse.status()).toBe(200);
    const outsiderData = await outsiderResponse.json();
    const managedProjectForOutsider = outsiderData.data.data.find(
      (item: any) => item.id === ctx.projectIds!.managed
    );
    expect(managedProjectForOutsider).toBeTruthy();
    expect(Boolean(managedProjectForOutsider.can_manage_role_users)).toBe(false);
  });

  test('薪资员只看到自己负责项目的待制作考勤表，未分配人员为空', async ({ request }) => {
    const salaryResponse = await request.get(apiUrl('attendance/pending-projects'), {
      headers: authHeaders(tokens.salary!, ctx.accountSetId!),
      params: {
        month: '2099-01',
        current_account_set_id: ctx.accountSetId,
      },
    });

    expect(salaryResponse.status()).toBe(200);
    const salaryData = await salaryResponse.json();
    expect(Array.isArray(salaryData.data)).toBe(true);
    expect(salaryData.data.map((item: any) => item.id)).toEqual([ctx.projectIds!.managed]);

    const outsiderResponse = await request.get(apiUrl('attendance/pending-projects'), {
      headers: authHeaders(tokens.outsider!, ctx.accountSetId!),
      params: {
        month: '2099-01',
        current_account_set_id: ctx.accountSetId,
      },
    });
    expect(outsiderResponse.status()).toBe(200);
    const outsiderData = await outsiderResponse.json();
    expect(outsiderData.data).toEqual([]);
  });

  test('保险负责人只看到自己负责项目的汇总待发起任务，未分配人员为空', async ({ request }) => {
    const insuranceResponse = await request.get(apiUrl('process-approvals/pending-projects'), {
      headers: authHeaders(tokens.insurance!, ctx.accountSetId!),
      params: {
        month: '2099-01',
        current_account_set_id: ctx.accountSetId,
      },
    });

    expect(insuranceResponse.status()).toBe(200);
    const insuranceData = await insuranceResponse.json();
    expect(Array.isArray(insuranceData.data)).toBe(true);
    expect(insuranceData.data).toHaveLength(2);
    expect(new Set(insuranceData.data.map((item: any) => item.project_id))).toEqual(
      new Set([ctx.projectIds!.managed])
    );

    const outsiderResponse = await request.get(apiUrl('process-approvals/pending-projects'), {
      headers: authHeaders(tokens.outsider!, ctx.accountSetId!),
      params: {
        month: '2099-01',
        current_account_set_id: ctx.accountSetId,
      },
    });
    expect(outsiderResponse.status()).toBe(200);
    const outsiderData = await outsiderResponse.json();
    expect(outsiderData.data).toEqual([]);
  });

  test('交付员只看到自己负责项目的待交付记录，未分配人员为空', async ({ request }) => {
    const deliveryResponse = await request.get(apiUrl('document-deliveries/my-pending'), {
      headers: authHeaders(tokens.delivery!, ctx.accountSetId!),
      params: {
        current_account_set_id: ctx.accountSetId,
        delivery_period: '2099-01',
      },
    });

    expect(deliveryResponse.status()).toBe(200);
    const deliveryData = await deliveryResponse.json();
    expect(Array.isArray(deliveryData.data)).toBe(true);
    expect(deliveryData.data).toHaveLength(1);
    expect(deliveryData.data[0].project_id).toBe(ctx.projectIds!.managed);

    const outsiderResponse = await request.get(apiUrl('document-deliveries/my-pending'), {
      headers: authHeaders(tokens.outsider!, ctx.accountSetId!),
      params: {
        current_account_set_id: ctx.accountSetId,
        delivery_period: '2099-01',
      },
    });
    expect(outsiderResponse.status()).toBe(200);
    const outsiderData = await outsiderResponse.json();
    expect(outsiderData.data).toEqual([]);
  });

  test('负责人设置人不自动拥有保险、薪资、交付业务待办', async ({ request }) => {
    const attendanceResponse = await request.get(apiUrl('attendance/pending-projects'), {
      headers: authHeaders(tokens.manager!, ctx.accountSetId!),
      params: {
        month: '2099-01',
        current_account_set_id: ctx.accountSetId,
      },
    });
    expect(attendanceResponse.status()).toBe(200);
    const attendanceData = await attendanceResponse.json();
    expect(attendanceData.data).toEqual([]);

    const processResponse = await request.get(apiUrl('process-approvals/pending-projects'), {
      headers: authHeaders(tokens.manager!, ctx.accountSetId!),
      params: {
        month: '2099-01',
        current_account_set_id: ctx.accountSetId,
      },
    });
    expect(processResponse.status()).toBe(200);
    const processData = await processResponse.json();
    expect(processData.data).toEqual([]);

    const deliveryResponse = await request.get(apiUrl('document-deliveries/my-pending'), {
      headers: authHeaders(tokens.manager!, ctx.accountSetId!),
      params: {
        current_account_set_id: ctx.accountSetId,
        delivery_period: '2099-01',
      },
    });
    expect(deliveryResponse.status()).toBe(200);
    const deliveryData = await deliveryResponse.json();
    expect(deliveryData.data).toEqual([]);
  });
});
