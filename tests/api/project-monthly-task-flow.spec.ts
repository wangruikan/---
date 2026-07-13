import { test, expect, type APIRequestContext, type APIResponse } from '@playwright/test';
import { execFileSync } from 'child_process';
import * as fs from 'fs';
import * as path from 'path';

const DB_HOST = process.env.DB_HOST || '127.0.0.1';
const DB_PORT = process.env.DB_PORT || '3306';
const DB_DATABASE = process.env.DB_DATABASE || 'weiqing';
const DB_USERNAME = process.env.DB_USERNAME || 'root';
const DB_PASSWORD = process.env.DB_PASSWORD || 'root';
const BASE_URL = (process.env.API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');
const USER_PASSWORD = process.env.E2E_MONTH_TASK_PASSWORD || 'Pass123456';
const PASSWORD_HASH = '$2y$10$vatwVgpxXSnmzxkxog.2s.M.cW8G60pnlKlytftZnVbyQqrLJavjW';
const RUN_ID = String(Date.now());

const PROCESSING_MONTH = shanghaiMonth();
const CURRENT_BUSINESS_MONTH = PROCESSING_MONTH;
const NEXT_BUSINESS_MONTH = shiftMonth(PROCESSING_MONTH, -1);
const JANUARY_PROCESSING_MONTH = `${PROCESSING_MONTH.slice(0, 4)}-01`;
const JANUARY_BUSINESS_MONTH = shiftMonth(JANUARY_PROCESSING_MONTH, -1);

interface Scenario {
  prefix: string;
  accountSetId: number;
  adminId: number;
  approverId: number;
  adminToken: string;
  approverToken: string;
  uploadedFiles: string[];
}

interface ProjectFixture {
  id: number;
  name: string;
  payload: Record<string, unknown>;
}

interface TaskRow {
  id: number;
  taskType: string;
  title: string;
  status: string;
  routeName: string | null;
  routeParams: Record<string, unknown>;
  relatedId: number;
  handlerId: number;
}

function shanghaiMonth(): string {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Shanghai',
    year: 'numeric',
    month: '2-digit',
  }).formatToParts(new Date());
  const year = parts.find((part) => part.type === 'year')?.value;
  const month = parts.find((part) => part.type === 'month')?.value;

  if (!year || !month) {
    throw new Error('Unable to resolve the current Asia/Shanghai month');
  }

  return `${year}-${month}`;
}

function shiftMonth(month: string, offset: number): string {
  const [year, monthNumber] = month.split('-').map(Number);
  const shifted = new Date(Date.UTC(year, monthNumber - 1 + offset, 1));
  return `${shifted.getUTCFullYear()}-${String(shifted.getUTCMonth() + 1).padStart(2, '0')}`;
}

function monthEndDay(month: string): number {
  const [year, monthNumber] = month.split('-').map(Number);
  return new Date(Date.UTC(year, monthNumber, 0)).getUTCDate();
}

function apiUrl(route: string): string {
  return `${BASE_URL}/${route.replace(/^\/+/, '')}`;
}

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
    throw new Error(`Query returned no value: ${sql}`);
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

function authHeaders(token: string, accountSetId: number): Record<string, string> {
  return {
    Authorization: `Bearer ${token}`,
    'X-Account-Set-Id': String(accountSetId),
    Accept: 'application/json',
  };
}

async function expectJson(response: APIResponse, expectedStatus = 200): Promise<any> {
  expect(response.status(), await response.text()).toBe(expectedStatus);
  expect(response.headers()['content-type']).toContain('application/json');
  return response.json();
}

async function login(request: APIRequestContext, email: string): Promise<string> {
  const response = await request.post(apiUrl('auth/login'), {
    data: {
      username: email,
      password: USER_PASSWORD,
    },
  });
  const body = await expectJson(response);
  expect(body.success).toBe(true);
  expect(body.data.token).toEqual(expect.any(String));
  return body.data.token;
}

function cleanupAccountSet(accountSetId: number, prefix: string): void {
  const userFilter = `SELECT id FROM users WHERE email LIKE ${sqlValue(`${prefix}-%`)}`;
  const approvalInstanceFilter = `SELECT id FROM approval_instances WHERE account_set_id = ${accountSetId}`;
  const salaryApprovalFilter = `SELECT id FROM salary_approvals WHERE account_set_id = ${accountSetId}`;
  const projectFilter = `SELECT id FROM projects WHERE account_set_id = ${accountSetId}`;
  const employeeFilter = `SELECT id FROM employees WHERE account_set_id = ${accountSetId}`;

  const cleanupStatements = [
    `DELETE FROM approval_attachments WHERE instance_id IN (${approvalInstanceFilter})`,
    `DELETE FROM approval_cc_users WHERE instance_id IN (${approvalInstanceFilter})`,
    `DELETE FROM approval_records WHERE instance_id IN (${approvalInstanceFilter})`,
    `DELETE FROM salary_payment_records WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM salary_summaries WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM salary_approval_attachments WHERE salary_approval_id IN (${salaryApprovalFilter})`,
    `DELETE FROM salaries WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM salary_approvals WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM approval_instances WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM attendance_records WHERE attendance_sheet_id IN (SELECT id FROM attendance_sheets WHERE account_set_id = ${accountSetId})`,
    `DELETE FROM attendance_statistics WHERE attendance_sheet_id IN (SELECT id FROM attendance_sheets WHERE account_set_id = ${accountSetId})`,
    `DELETE FROM attendance_sheets WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM basis_attachments WHERE basis_record_id IN (SELECT id FROM basis_records WHERE account_set_id = ${accountSetId})`,
    `DELETE FROM basis_records WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM pending_tasks WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM notifications WHERE user_id IN (${userFilter})`,
    `DELETE FROM project_role_users WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM employee_projects WHERE project_id IN (${projectFilter}) OR employee_id IN (${employeeFilter})`,
    `DELETE FROM employees WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM projects WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM approval_flow_configs WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM account_set_users WHERE account_set_id = ${accountSetId}`,
    `DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\\\\Models\\\\User' AND tokenable_id IN (${userFilter})`,
    `UPDATE users SET account_set_id = NULL, current_account_set_id = NULL WHERE email LIKE ${sqlValue(`${prefix}-%`)}`,
    `DELETE FROM account_sets WHERE id = ${accountSetId}`,
    `DELETE FROM users WHERE email LIKE ${sqlValue(`${prefix}-%`)}`,
  ];

  for (const sql of cleanupStatements) {
    runSql(sql);
  }
}

function cleanupPrefix(prefix: string): void {
  const accountSetRows = queryRows(`
    SELECT id
    FROM account_sets
    WHERE code = ${sqlValue(`${prefix}-account`)}
  `);

  for (const [accountSetId] of accountSetRows) {
    cleanupAccountSet(Number(accountSetId), prefix);
  }

  runSql(`
    DELETE FROM personal_access_tokens
    WHERE tokenable_type = 'App\\\\Models\\\\User'
      AND tokenable_id IN (SELECT id FROM users WHERE email LIKE ${sqlValue(`${prefix}-%`)})
  `);
  runSql(`DELETE FROM users WHERE email LIKE ${sqlValue(`${prefix}-%`)}`);
}

async function createScenario(request: APIRequestContext, label: string): Promise<Scenario> {
  const prefix = `api-month-${RUN_ID}-${label}`;
  cleanupPrefix(prefix);

  const adminEmail = `${prefix}-admin@example.com`;
  const approverEmail = `${prefix}-approver@example.com`;

  runSql(`
    INSERT INTO users (
      name, email, password, role, account_set_id, current_account_set_id, is_active, created_at, updated_at
    ) VALUES
      (${sqlValue(`${prefix}-admin`)}, ${sqlValue(adminEmail)}, ${sqlValue(PASSWORD_HASH)}, 'admin', NULL, NULL, 1, NOW(), NOW()),
      (${sqlValue(`${prefix}-approver`)}, ${sqlValue(approverEmail)}, ${sqlValue(PASSWORD_HASH)}, 'employee', NULL, NULL, 1, NOW(), NOW())
  `);

  const userRows = queryRows(`
    SELECT email, id
    FROM users
    WHERE email IN (${sqlValue(adminEmail)}, ${sqlValue(approverEmail)})
  `);
  const userIds = Object.fromEntries(userRows.map(([email, id]) => [email, Number(id)]));
  const adminId = userIds[adminEmail];
  const approverId = userIds[approverEmail];

  runSql(`
    INSERT INTO account_sets (name, code, status, created_by, created_at, updated_at)
    VALUES (
      ${sqlValue(`${prefix}-account`)},
      ${sqlValue(`${prefix}-account`)},
      'active',
      ${adminId},
      NOW(),
      NOW()
    )
  `);

  const accountSetId = querySingleNumber(`
    SELECT id FROM account_sets WHERE code = ${sqlValue(`${prefix}-account`)} LIMIT 1
  `);

  runSql(`
    UPDATE users
    SET account_set_id = ${accountSetId}, current_account_set_id = ${accountSetId}, updated_at = NOW()
    WHERE id IN (${adminId}, ${approverId})
  `);
  runSql(`
    INSERT INTO account_set_users (
      account_set_id, user_id, role, approval_level, approval_level_name, is_default, created_at, updated_at
    ) VALUES
      (${accountSetId}, ${adminId}, 'owner', NULL, NULL, 1, NOW(), NOW()),
      (${accountSetId}, ${approverId}, 'viewer', 1, '第1级审批', 0, NOW(), NOW())
  `);
  runSql(`
    INSERT INTO approval_flow_configs (account_set_id, business_type, enabled_levels, created_at, updated_at)
    VALUES
      (${accountSetId}, '考勤申请', '[1]', NOW(), NOW()),
      (${accountSetId}, '工资表审批', '[1]', NOW(), NOW())
  `);

  return {
    prefix,
    accountSetId,
    adminId,
    approverId,
    adminToken: await login(request, adminEmail),
    approverToken: await login(request, approverEmail),
    uploadedFiles: [],
  };
}

function cleanupScenario(scenario: Scenario): void {
  cleanupAccountSet(scenario.accountSetId, scenario.prefix);

  for (const relativeFile of scenario.uploadedFiles) {
    const absoluteFile = path.resolve('storage/app/public', relativeFile);
    if (fs.existsSync(absoluteFile)) {
      fs.rmSync(absoluteFile, { force: true });
    }
  }
}

function buildProjectPayload(
  scenario: Scenario,
  label: string,
  salaryPaymentMonth: 'current' | 'next',
  startMonth: string,
): Record<string, unknown> {
  return {
    name: `${scenario.prefix}-${label}`,
    code: `${scenario.prefix}-${label}`,
    description: 'Playwright API 本月次月链路测试',
    status: 'active',
    start_date: `${startMonth}-01`,
    end_date: `${shiftMonth(PROCESSING_MONTH, 6)}-28`,
    salary_payment_date: 15,
    salary_payment_month: salaryPaymentMonth,
    insurance_import_month: 'none',
    requires_attendance: true,
    require_attendance: true,
    requires_salary_basis: true,
    requires_attendance_basis: true,
    delivery_frequency: 'monthly',
    delivery_method: 'electronic',
    registration_form_type: 'onboarding',
    invoice_infos: [
      {
        remark: 'API测试开票信息',
        company_name: `${scenario.prefix}-company`,
        tax_number: `${RUN_ID}${label}`.slice(0, 30),
        company_address: 'API测试地址',
        company_phone: '010-12345678',
        bank_name: 'API测试银行',
        bank_account: `${RUN_ID}001`,
        bank_code: 'TESTBANK001',
      },
    ],
    current_account_set_id: scenario.accountSetId,
  };
}

async function createProject(
  request: APIRequestContext,
  scenario: Scenario,
  label: string,
  salaryPaymentMonth: 'current' | 'next',
  startMonth: string,
): Promise<ProjectFixture> {
  const payload = buildProjectPayload(scenario, label, salaryPaymentMonth, startMonth);
  const response = await request.post(apiUrl('projects'), {
    headers: authHeaders(scenario.adminToken, scenario.accountSetId),
    data: payload,
  });
  const body = await expectJson(response);
  expect(body.success).toBe(true);
  expect(body.data.id).toEqual(expect.any(Number));

  const projectId = Number(body.data.id);
  const roleResponse = await request.post(apiUrl(`projects/${projectId}/role-users`), {
    headers: authHeaders(scenario.adminToken, scenario.accountSetId),
    data: {
      current_account_set_id: scenario.accountSetId,
      salary_user_ids: [scenario.adminId],
      insurance_user_ids: [],
      delivery_user_ids: [],
      role_manager_user_ids: [scenario.adminId],
    },
  });
  const roleBody = await expectJson(roleResponse);
  expect(roleBody.success).toBe(true);
  expect(roleBody.data.roles.salary.user_ids).toEqual([scenario.adminId]);

  return {
    id: projectId,
    name: String(payload.name),
    payload,
  };
}

async function triggerTasks(
  request: APIRequestContext,
  scenario: Scenario,
  month: string,
): Promise<{ body: any; duration: number }> {
  const startedAt = Date.now();
  const response = await request.get(apiUrl('pending-tasks'), {
    headers: authHeaders(scenario.adminToken, scenario.accountSetId),
    params: {
      current_account_set_id: String(scenario.accountSetId),
      month,
      status: 'pending',
    },
  });
  const duration = Date.now() - startedAt;
  const body = await expectJson(response);
  expect(body.success).toBe(true);
  return { body, duration };
}

function loadProjectTasks(scenario: Scenario, projectIds?: number[]): TaskRow[] {
  const projectFilter = projectIds?.length
    ? ` AND related_id IN (${projectIds.join(',')})`
    : '';
  return queryRows(`
    SELECT id, task_type, title, status, route_name, route_params, related_id, handler_id
    FROM pending_tasks
    WHERE account_set_id = ${scenario.accountSetId}
      AND related_type = 'Project'
      ${projectFilter}
    ORDER BY id
  `).map(([id, taskType, title, status, routeName, routeParams, relatedId, handlerId]) => ({
    id: Number(id),
    taskType,
    title,
    status,
    routeName: routeName || null,
    routeParams: routeParams ? JSON.parse(routeParams) : {},
    relatedId: Number(relatedId),
    handlerId: Number(handlerId),
  }));
}

function expectTaskMonth(
  tasks: TaskRow[],
  project: ProjectFixture,
  taskType: string,
  titleMonth: string,
  routeMonth: string,
): TaskRow {
  const task = tasks.find(
    (item) => item.relatedId === project.id && item.taskType === taskType && item.status === 'pending',
  );
  expect(task, `${project.name} 缺少 ${taskType} 待办`).toBeTruthy();
  expect(task!.title).toContain(titleMonth);
  expect(task!.routeParams.month).toBe(routeMonth);
  expect(Number(task!.routeParams.project_id)).toBe(project.id);
  return task!;
}

function seedEmployee(
  scenario: Scenario,
  project: ProjectFixture,
  label: string,
  startMonth: string,
): number {
  const idNumber = `${RUN_ID.slice(-10)}${String(project.id).padStart(8, '0')}`.slice(-18);
  runSql(`
    INSERT INTO employees (
      account_set_id, name, id_number, gender, birth_date, hire_date, contract_start_date,
      contract_status, project_ids, basic_salary, bank_account_holder, bank_account,
      bank_name, created_at, updated_at
    ) VALUES (
      ${scenario.accountSetId},
      ${sqlValue(`${scenario.prefix}-${label}-employee`)},
      ${sqlValue(idNumber)},
      'male',
      '1990-01-01',
      ${sqlValue(`${startMonth}-01`)},
      ${sqlValue(`${startMonth}-01`)},
      'active',
      ${sqlValue(JSON.stringify([project.id]))},
      5000.00,
      ${sqlValue(`${scenario.prefix}-${label}-employee`)},
      '6222000000000000',
      'API测试银行',
      NOW(),
      NOW()
    )
  `);
  const employeeId = querySingleNumber(`
    SELECT id FROM employees WHERE id_number = ${sqlValue(idNumber)} LIMIT 1
  `);
  runSql(`
    INSERT INTO employee_projects (
      employee_id, project_id, employee_number, start_date, status, created_at, updated_at
    ) VALUES (
      ${employeeId}, ${project.id}, ${sqlValue(`${project.payload.code}-001`)},
      ${sqlValue(`${startMonth}-01`)}, 'active', NOW(), NOW()
    )
  `);
  return employeeId;
}

async function uploadBasisAttachment(
  request: APIRequestContext,
  scenario: Scenario,
  recordId: number,
  fileName: string,
): Promise<any> {
  const response = await request.post(apiUrl('basis-records/upload-attachment'), {
    headers: authHeaders(scenario.adminToken, scenario.accountSetId),
    multipart: {
      basis_record_id: String(recordId),
      file: {
        name: fileName,
        mimeType: 'text/plain',
        buffer: Buffer.from(`Playwright API monthly task test ${scenario.prefix} ${fileName}`),
      },
    },
  });
  const body = await expectJson(response);
  expect(body.success).toBe(true);
  expect(body.data.file_path).toEqual(expect.any(String));
  scenario.uploadedFiles.push(body.data.file_path);
  return body.data;
}

function findPendingApprovalRecord(
  scenario: Scenario,
  businessType: string,
  businessId: number,
): number {
  return querySingleNumber(`
    SELECT approval_records.id
    FROM approval_records
    INNER JOIN approval_instances ON approval_instances.id = approval_records.instance_id
    WHERE approval_instances.account_set_id = ${scenario.accountSetId}
      AND approval_instances.business_type = ${sqlValue(businessType)}
      AND approval_instances.business_id = ${businessId}
      AND approval_records.status = 'pending'
      AND approval_records.approver_id = ${scenario.approverId}
    ORDER BY approval_records.id DESC
    LIMIT 1
  `);
}

test.describe.serial('项目本月/次月完整 API 链路', () => {
  test('处理月映射、依据列表、待办路由和重复触发保持一致', async ({ request }) => {
    const scenario = await createScenario(request, 'mapping');
    try {
      const currentProject = await createProject(
        request,
        scenario,
        'current',
        'current',
        CURRENT_BUSINESS_MONTH,
      );
      const nextProject = await createProject(
        request,
        scenario,
        'next',
        'next',
        NEXT_BUSINESS_MONTH,
      );

      const firstTrigger = await triggerTasks(request, scenario, PROCESSING_MONTH);
      expect(firstTrigger.duration).toBeLessThan(5000);

      const tasks = loadProjectTasks(scenario, [currentProject.id, nextProject.id]);
      expect(tasks).toHaveLength(8);

      expectTaskMonth(tasks, currentProject, 'salary_basis', CURRENT_BUSINESS_MONTH, PROCESSING_MONTH);
      expectTaskMonth(tasks, currentProject, 'attendance_basis', CURRENT_BUSINESS_MONTH, PROCESSING_MONTH);
      expectTaskMonth(tasks, currentProject, 'salary_sheet', CURRENT_BUSINESS_MONTH, CURRENT_BUSINESS_MONTH);
      expectTaskMonth(tasks, currentProject, 'attendance_sheet', CURRENT_BUSINESS_MONTH, CURRENT_BUSINESS_MONTH);
      expectTaskMonth(tasks, nextProject, 'salary_basis', NEXT_BUSINESS_MONTH, PROCESSING_MONTH);
      expectTaskMonth(tasks, nextProject, 'attendance_basis', NEXT_BUSINESS_MONTH, PROCESSING_MONTH);
      expectTaskMonth(tasks, nextProject, 'salary_sheet', NEXT_BUSINESS_MONTH, NEXT_BUSINESS_MONTH);
      expectTaskMonth(tasks, nextProject, 'attendance_sheet', NEXT_BUSINESS_MONTH, NEXT_BUSINESS_MONTH);

      const basisRows = queryRows(`
        SELECT project_id, type, month
        FROM basis_records
        WHERE account_set_id = ${scenario.accountSetId}
          AND project_id IN (${currentProject.id}, ${nextProject.id})
        ORDER BY project_id, type
      `);
      expect(basisRows).toEqual([
        [String(currentProject.id), 'attendance', CURRENT_BUSINESS_MONTH],
        [String(currentProject.id), 'salary', CURRENT_BUSINESS_MONTH],
        [String(nextProject.id), 'attendance', NEXT_BUSINESS_MONTH],
        [String(nextProject.id), 'salary', NEXT_BUSINESS_MONTH],
      ]);

      for (const type of ['salary', 'attendance']) {
        const response = await request.get(apiUrl('basis-records'), {
          headers: authHeaders(scenario.adminToken, scenario.accountSetId),
          params: {
            current_account_set_id: String(scenario.accountSetId),
            type,
            month: PROCESSING_MONTH,
          },
        });
        const body = await expectJson(response);
        const records = body.data.filter((item: any) =>
          [currentProject.id, nextProject.id].includes(Number(item.project_id)),
        );
        expect(records).toHaveLength(2);
        expect(
          Object.fromEntries(records.map((item: any) => [Number(item.project_id), item.month])),
        ).toEqual({
          [currentProject.id]: CURRENT_BUSINESS_MONTH,
          [nextProject.id]: NEXT_BUSINESS_MONTH,
        });
      }

      const taskIdsBefore = tasks.map((task) => task.id);
      await triggerTasks(request, scenario, PROCESSING_MONTH);
      const tasksAfter = loadProjectTasks(scenario, [currentProject.id, nextProject.id]);
      expect(tasksAfter.map((task) => task.id)).toEqual(taskIdsBefore);

      const unauthenticated = await request.get(apiUrl('pending-tasks'), {
        params: { current_account_set_id: String(scenario.accountSetId), month: PROCESSING_MONTH },
      });
      expect(unauthenticated.status()).toBe(401);
    } finally {
      cleanupScenario(scenario);
    }
  });

  test('本月和次月项目都能从依据上传走到考勤、工资审批完成', async ({ request }) => {
    const scenario = await createScenario(request, 'full-flow');
    try {
      for (const flowCase of [
        { label: 'current', setting: 'current' as const, businessMonth: CURRENT_BUSINESS_MONTH },
        { label: 'next', setting: 'next' as const, businessMonth: NEXT_BUSINESS_MONTH },
      ]) {
        const businessMonth = flowCase.businessMonth;
        const invalidMonth = flowCase.setting === 'next'
          ? PROCESSING_MONTH
          : shiftMonth(PROCESSING_MONTH, -1);
        const project = await createProject(
          request,
          scenario,
          `${flowCase.label}-flow`,
          flowCase.setting,
          businessMonth,
        );
        const employeeId = seedEmployee(
          scenario,
          project,
          `${flowCase.label}-flow`,
          businessMonth,
        );
        await triggerTasks(request, scenario, PROCESSING_MONTH);

      const initialAttendanceResponse = await request.get(apiUrl('attendance/pending-projects'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        params: {
          current_account_set_id: String(scenario.accountSetId),
          month: businessMonth,
        },
      });
      const initialAttendanceBody = await expectJson(initialAttendanceResponse);
      const initialProject = initialAttendanceBody.data.find((item: any) => Number(item.id) === project.id);
      expect(initialProject).toMatchObject({
        month: businessMonth,
        requires_attendance_basis: true,
        basis_ready: false,
        can_create: false,
      });

      const wrongMonthResponse = await request.get(apiUrl('attendance/pending-projects'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        params: {
          current_account_set_id: String(scenario.accountSetId),
          month: invalidMonth,
        },
      });
      const wrongMonthBody = await expectJson(wrongMonthResponse);
      expect(wrongMonthBody.data.some((item: any) => Number(item.id) === project.id)).toBe(false);

      const basisRows = queryRows(`
        SELECT id, type
        FROM basis_records
        WHERE account_set_id = ${scenario.accountSetId}
          AND project_id = ${project.id}
          AND month = ${sqlValue(businessMonth)}
        ORDER BY type
      `);
      expect(basisRows).toHaveLength(2);
      for (const [recordId, type] of basisRows) {
        await uploadBasisAttachment(
          request,
          scenario,
          Number(recordId),
          `${scenario.prefix}-${flowCase.label}-${type}.txt`,
        );
      }

      let completedTasks = loadProjectTasks(scenario, [project.id]).filter(
        (task) => task.status === 'completed',
      );
      expect(completedTasks.map((task) => task.taskType).sort()).toEqual([
        'attendance_basis',
        'salary_basis',
      ]);

      const attendanceReadyResponse = await request.get(apiUrl('attendance/pending-projects'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        params: {
          current_account_set_id: String(scenario.accountSetId),
          month: businessMonth,
        },
      });
      const attendanceReadyBody = await expectJson(attendanceReadyResponse);
      expect(
        attendanceReadyBody.data.find((item: any) => Number(item.id) === project.id),
      ).toMatchObject({ basis_ready: true, can_create: true });

      const invalidAttendanceResponse = await request.post(apiUrl('attendance'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        data: {
          current_account_set_id: scenario.accountSetId,
          project_id: project.id,
          month: invalidMonth,
          work_days: monthEndDay(invalidMonth),
        },
      });
      const invalidAttendanceBody = await expectJson(invalidAttendanceResponse, 422);
      expect(invalidAttendanceBody.message).toContain('工资发放设置');

      const createAttendanceResponse = await request.post(apiUrl('attendance'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        data: {
          current_account_set_id: scenario.accountSetId,
          project_id: project.id,
          month: businessMonth,
          work_days: monthEndDay(businessMonth),
          notes: `Playwright API ${flowCase.label} 考勤表`,
        },
      });
      const createAttendanceBody = await expectJson(createAttendanceResponse);
      expect(createAttendanceBody.data).toMatchObject({
        project_id: project.id,
        month: businessMonth,
        total_employees: 1,
        status: 'draft',
      });
      const attendanceSheetId = Number(createAttendanceBody.data.id);

      const payrollBeforeApprovalResponse = await request.get(apiUrl('payroll/pending-projects'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        params: {
          current_account_set_id: String(scenario.accountSetId),
          month: businessMonth,
        },
      });
      const payrollBeforeApprovalBody = await expectJson(payrollBeforeApprovalResponse);
      expect(
        payrollBeforeApprovalBody.data.find((item: any) => Number(item.id) === project.id),
      ).toMatchObject({
        month: businessMonth,
        attendance_approved: false,
        salary_basis_ready: true,
        can_create: false,
        disabled_reason: '请先审批本月考勤表',
      });

      const submitAttendanceResponse = await request.post(
        apiUrl(`attendance/${attendanceSheetId}/submit`),
        {
          headers: authHeaders(scenario.adminToken, scenario.accountSetId),
          data: {
            current_account_set_id: scenario.accountSetId,
            notes: '提交次月考勤审批',
          },
        },
      );
      const submitAttendanceBody = await expectJson(submitAttendanceResponse);
      expect(submitAttendanceBody.success).toBe(true);

      const attendanceApprovalRecordId = findPendingApprovalRecord(
        scenario,
        '考勤申请',
        attendanceSheetId,
      );
      const approveAttendanceResponse = await request.post(
        apiUrl(`approvals/records/${attendanceApprovalRecordId}/approve`),
        {
          headers: authHeaders(scenario.approverToken, scenario.accountSetId),
          data: { comment: 'Playwright API 考勤审批通过' },
        },
      );
      const approveAttendanceBody = await expectJson(approveAttendanceResponse);
      expect(approveAttendanceBody.success).toBe(true);
      expect(
        runSql(`SELECT status FROM attendance_sheets WHERE id = ${attendanceSheetId}`),
      ).toBe('approved');

      const attendanceTask = loadProjectTasks(scenario, [project.id]).find(
        (task) => task.taskType === 'attendance_sheet',
      );
      expect(attendanceTask?.status).toBe('completed');

      const payrollReadyResponse = await request.get(apiUrl('payroll/pending-projects'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        params: {
          current_account_set_id: String(scenario.accountSetId),
          month: businessMonth,
        },
      });
      const payrollReadyBody = await expectJson(payrollReadyResponse);
      expect(
        payrollReadyBody.data.find((item: any) => Number(item.id) === project.id),
      ).toMatchObject({
        attendance_approved: true,
        salary_basis_ready: true,
        can_create: true,
        disabled_reason: null,
      });

      const generateSalaryResponse = await request.post(apiUrl('salaries/generate'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        data: {
          current_account_set_id: scenario.accountSetId,
          project_id: project.id,
          month: businessMonth,
          period_start: 1,
          period_end: monthEndDay(businessMonth),
        },
      });
      const generateSalaryBody = await expectJson(generateSalaryResponse);
      expect(generateSalaryBody).toMatchObject({
        success: true,
        data: {
          count: 1,
          project_id: project.id,
          month: businessMonth,
        },
      });
      expect(
        querySingleNumber(`
          SELECT COUNT(*) FROM salaries
          WHERE account_set_id = ${scenario.accountSetId}
            AND project_id = ${project.id}
            AND employee_id = ${employeeId}
            AND month = ${sqlValue(businessMonth)}
        `),
      ).toBe(1);

      const payrollAfterGenerateResponse = await request.get(apiUrl('payroll/pending-projects'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        params: {
          current_account_set_id: String(scenario.accountSetId),
          month: businessMonth,
        },
      });
      const payrollAfterGenerateBody = await expectJson(payrollAfterGenerateResponse);
      expect(payrollAfterGenerateBody.data.some((item: any) => Number(item.id) === project.id)).toBe(false);

      const draftBatchId = Number(generateSalaryBody.data.draft_batch_id);
      const salarySubmitResponse = await request.post(apiUrl('salary-approvals/submit'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        data: {
          current_account_set_id: scenario.accountSetId,
          project_id: project.id,
          month: businessMonth,
          draft_batch_id: draftBatchId,
          approval_type: 'online',
          remarks: 'Playwright API 工资审批',
        },
      });
      const salarySubmitBody = await expectJson(salarySubmitResponse);
      expect(salarySubmitBody.success).toBe(true);
      const salaryApprovalId = Number(salarySubmitBody.data.id);

      const completeSalarySubmissionResponse = await request.post(
        apiUrl('salary-approvals/complete-submission'),
        {
          headers: authHeaders(scenario.adminToken, scenario.accountSetId),
          data: {
            current_account_set_id: scenario.accountSetId,
            salary_approval_id: salaryApprovalId,
          },
        },
      );
      const completeSalarySubmissionBody = await expectJson(completeSalarySubmissionResponse);
      expect(completeSalarySubmissionBody.success).toBe(true);

      const salaryApprovalRecordId = findPendingApprovalRecord(
        scenario,
        '工资表审批',
        salaryApprovalId,
      );
      const approveSalaryResponse = await request.post(
        apiUrl(`approvals/records/${salaryApprovalRecordId}/approve`),
        {
          headers: authHeaders(scenario.approverToken, scenario.accountSetId),
          data: { comment: 'Playwright API 工资审批通过' },
        },
      );
      const approveSalaryBody = await expectJson(approveSalaryResponse);
      expect(approveSalaryBody.success).toBe(true);
      expect(
        runSql(`SELECT status FROM salary_approvals WHERE id = ${salaryApprovalId}`),
      ).toBe('approved');

      completedTasks = loadProjectTasks(scenario, [project.id]).filter(
        (task) => task.status === 'completed',
      );
      expect(completedTasks.map((task) => task.taskType).sort()).toEqual([
        'attendance_basis',
        'attendance_sheet',
        'salary_basis',
        'salary_sheet',
      ]);

      const completedListResponse = await request.get(apiUrl('pending-tasks'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        params: {
          current_account_set_id: String(scenario.accountSetId),
          month: PROCESSING_MONTH,
          status: 'completed',
        },
      });
      const completedListBody = await expectJson(completedListResponse);
      const completedTypes = completedListBody.data.data
        .filter((task: any) => Number(task.related_id) === project.id)
        .map((task: any) => task.task_type)
        .sort();
      expect(completedTypes).toEqual([
        'attendance_basis',
        'attendance_sheet',
        'salary_basis',
        'salary_sheet',
      ]);
      }
    } finally {
      cleanupScenario(scenario);
    }
  });

  test('次月与本月双向切换立即重建未完成任务，并保留已完成历史', async ({ request }) => {
    const scenario = await createScenario(request, 'switch');
    try {
      const project = await createProject(
        request,
        scenario,
        'switch-project',
        'next',
        NEXT_BUSINESS_MONTH,
      );
      const employeeId = seedEmployee(scenario, project, 'switch', NEXT_BUSINESS_MONTH);
      await triggerTasks(request, scenario, PROCESSING_MONTH);

      const salaryBasisId = querySingleNumber(`
        SELECT id FROM basis_records
        WHERE account_set_id = ${scenario.accountSetId}
          AND project_id = ${project.id}
          AND type = 'salary'
          AND month = ${sqlValue(NEXT_BUSINESS_MONTH)}
        LIMIT 1
      `);
      await uploadBasisAttachment(
        request,
        scenario,
        salaryBasisId,
        `${scenario.prefix}-completed-history.txt`,
      );
      const completedHistoryTask = loadProjectTasks(scenario, [project.id]).find(
        (task) => task.taskType === 'salary_basis' && task.status === 'completed',
      );
      expect(completedHistoryTask?.title).toContain(NEXT_BUSINESS_MONTH);

      runSql(`
        INSERT INTO salaries (
          account_set_id, employee_id, id_card, employee_name, project_id, month,
          period_start, period_end, insurance_import_setting, status, created_at, updated_at
        ) VALUES (
          ${scenario.accountSetId}, ${employeeId}, '000000000000000000',
          ${sqlValue(`${scenario.prefix}-history`)}, ${project.id}, ${sqlValue(NEXT_BUSINESS_MONTH)},
          1, ${monthEndDay(NEXT_BUSINESS_MONTH)}, 'none', 'approved', NOW(), NOW()
        )
      `);

      const currentPayload = {
        ...project.payload,
        salary_payment_month: 'current',
      };
      const switchToCurrentResponse = await request.put(apiUrl(`projects/${project.id}`), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        data: currentPayload,
      });
      const switchToCurrentBody = await expectJson(switchToCurrentResponse);
      expect(switchToCurrentBody.data.salary_payment_month).toBe('current');

      let tasks = loadProjectTasks(scenario, [project.id]);
      expect(tasks.find((task) => task.id === completedHistoryTask?.id)?.status).toBe('completed');
      const pendingCurrentTasks = tasks.filter((task) => task.status === 'pending');
      expect(pendingCurrentTasks).toHaveLength(4);
      for (const task of pendingCurrentTasks) {
        expect(task.title).toContain(CURRENT_BUSINESS_MONTH);
        expect(task.routeParams.month).toBe(CURRENT_BUSINESS_MONTH);
      }
      expect(
        querySingleNumber(`
          SELECT COUNT(*) FROM basis_records
          WHERE account_set_id = ${scenario.accountSetId}
            AND project_id = ${project.id}
            AND month = ${sqlValue(CURRENT_BUSINESS_MONTH)}
        `),
      ).toBe(2);

      const nextPayload = {
        ...project.payload,
        salary_payment_month: 'next',
      };
      const switchBackResponse = await request.put(apiUrl(`projects/${project.id}`), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        data: nextPayload,
      });
      const switchBackBody = await expectJson(switchBackResponse);
      expect(switchBackBody.data.salary_payment_month).toBe('next');

      tasks = loadProjectTasks(scenario, [project.id]);
      expect(tasks.find((task) => task.id === completedHistoryTask?.id)?.status).toBe('completed');
      const pendingNextTasks = tasks.filter((task) => task.status === 'pending');
      expect(pendingNextTasks.map((task) => task.taskType).sort()).toEqual([
        'attendance_basis',
        'attendance_sheet',
        'salary_sheet',
      ]);
      for (const task of pendingNextTasks) {
        expect(task.title).toContain(NEXT_BUSINESS_MONTH);
        const expectedRouteMonth = task.taskType.endsWith('_basis')
          ? PROCESSING_MONTH
          : NEXT_BUSINESS_MONTH;
        expect(task.routeParams.month).toBe(expectedRouteMonth);
      }
      expect(
        tasks.some(
          (task) => task.status === 'pending' && task.title.includes(CURRENT_BUSINESS_MONTH),
        ),
      ).toBe(false);
    } finally {
      cleanupScenario(scenario);
    }
  });

  test('项目起始月、跨年回退和非法月份边界正确', async ({ request }) => {
    const scenario = await createScenario(request, 'boundary');
    try {
      const notReleasedProject = await createProject(
        request,
        scenario,
        'not-released',
        'next',
        PROCESSING_MONTH,
      );
      await triggerTasks(request, scenario, PROCESSING_MONTH);
      expect(loadProjectTasks(scenario, [notReleasedProject.id])).toEqual([]);
      expect(
        querySingleNumber(`
          SELECT COUNT(*) FROM basis_records
          WHERE account_set_id = ${scenario.accountSetId}
            AND project_id = ${notReleasedProject.id}
        `),
      ).toBe(0);

      const januaryProject = await createProject(
        request,
        scenario,
        'january-next',
        'next',
        JANUARY_BUSINESS_MONTH,
      );
      await triggerTasks(request, scenario, JANUARY_PROCESSING_MONTH);
      const januaryTasks = loadProjectTasks(scenario, [januaryProject.id]);
      expect(januaryTasks).toHaveLength(4);
      expectTaskMonth(
        januaryTasks,
        januaryProject,
        'salary_basis',
        JANUARY_BUSINESS_MONTH,
        JANUARY_PROCESSING_MONTH,
      );
      expectTaskMonth(
        januaryTasks,
        januaryProject,
        'attendance_basis',
        JANUARY_BUSINESS_MONTH,
        JANUARY_PROCESSING_MONTH,
      );
      expectTaskMonth(
        januaryTasks,
        januaryProject,
        'salary_sheet',
        JANUARY_BUSINESS_MONTH,
        JANUARY_BUSINESS_MONTH,
      );
      expectTaskMonth(
        januaryTasks,
        januaryProject,
        'attendance_sheet',
        JANUARY_BUSINESS_MONTH,
        JANUARY_BUSINESS_MONTH,
      );

      const invalidAttendanceResponse = await request.get(apiUrl('attendance/pending-projects'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        params: {
          current_account_set_id: String(scenario.accountSetId),
          month: '2026-13',
        },
      });
      const invalidAttendanceBody = await expectJson(invalidAttendanceResponse, 422);
      expect(invalidAttendanceBody.errors.month).toBeTruthy();

      const invalidPayrollResponse = await request.get(apiUrl('payroll/pending-projects'), {
        headers: authHeaders(scenario.adminToken, scenario.accountSetId),
        params: {
          current_account_set_id: String(scenario.accountSetId),
          month: 'invalid-month',
        },
      });
      const invalidPayrollBody = await expectJson(invalidPayrollResponse, 422);
      expect(invalidPayrollBody.errors.month).toBeTruthy();
    } finally {
      cleanupScenario(scenario);
    }
  });

  test('复制上月按业务月复制，不会对次月项目重复减月', async ({ request }) => {
    const scenario = await createScenario(request, 'copy');
    try {
      const project = await createProject(
        request,
        scenario,
        'copy-next',
        'next',
        NEXT_BUSINESS_MONTH,
      );
      await triggerTasks(request, scenario, PROCESSING_MONTH);

      const sourceMonth = shiftMonth(NEXT_BUSINESS_MONTH, -1);
      runSql(`
        INSERT INTO basis_records (
          account_set_id, project_id, type, month, description, created_by, created_at, updated_at
        ) VALUES (
          ${scenario.accountSetId}, ${project.id}, 'salary', ${sqlValue(sourceMonth)},
          'Playwright API 复制来源', ${scenario.adminId}, NOW(), NOW()
        )
      `);
      const sourceRecordId = querySingleNumber(`
        SELECT id FROM basis_records
        WHERE account_set_id = ${scenario.accountSetId}
          AND project_id = ${project.id}
          AND type = 'salary'
          AND month = ${sqlValue(sourceMonth)}
        LIMIT 1
      `);
      const sourcePath = `basis_attachments/salary/${sourceMonth}/${scenario.prefix}-source.txt`;
      runSql(`
        INSERT INTO basis_attachments (
          basis_record_id, file_name, file_path, file_type, file_size, created_at, updated_at
        ) VALUES (
          ${sourceRecordId}, ${sqlValue(`${scenario.prefix}-source.txt`)},
          ${sqlValue(sourcePath)}, 'other', 128, NOW(), NOW()
        )
      `);

      for (let attempt = 0; attempt < 2; attempt += 1) {
        const copyResponse = await request.post(apiUrl('basis-records/copy-last-month'), {
          headers: authHeaders(scenario.adminToken, scenario.accountSetId),
          data: {
            current_account_set_id: scenario.accountSetId,
            project_id: project.id,
            type: 'salary',
            month: NEXT_BUSINESS_MONTH,
            month_is_basis: true,
            description: 'Playwright API 复制上月',
          },
        });
        const copyBody = await expectJson(copyResponse);
        expect(copyBody.success).toBe(true);
        expect(copyBody.message).toContain(`${sourceMonth} -> ${NEXT_BUSINESS_MONTH}`);
        expect(copyBody.data.month).toBe(NEXT_BUSINESS_MONTH);
      }

      const targetRecordId = querySingleNumber(`
        SELECT id FROM basis_records
        WHERE account_set_id = ${scenario.accountSetId}
          AND project_id = ${project.id}
          AND type = 'salary'
          AND month = ${sqlValue(NEXT_BUSINESS_MONTH)}
        LIMIT 1
      `);
      expect(
        querySingleNumber(`
          SELECT COUNT(*) FROM basis_attachments
          WHERE basis_record_id = ${targetRecordId}
            AND file_path = ${sqlValue(sourcePath)}
        `),
      ).toBe(1);
      expect(
        querySingleNumber(`
          SELECT COUNT(*) FROM basis_records
          WHERE account_set_id = ${scenario.accountSetId}
            AND project_id = ${project.id}
            AND type = 'salary'
            AND month = ${sqlValue(shiftMonth(sourceMonth, -1))}
        `),
      ).toBe(0);

      const salaryBasisTask = loadProjectTasks(scenario, [project.id]).find(
        (task) => task.taskType === 'salary_basis',
      );
      expect(salaryBasisTask?.status).toBe('completed');
    } finally {
      cleanupScenario(scenario);
    }
  });
});
