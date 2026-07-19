import { test, expect, type APIRequestContext, type APIResponse } from '@playwright/test';
import { execFileSync } from 'child_process';

const DB_HOST = process.env.DB_HOST || '127.0.0.1';
const DB_PORT = process.env.DB_PORT || '3306';
const DB_DATABASE = process.env.DB_DATABASE || 'weiqing';
const DB_USERNAME = process.env.DB_USERNAME || 'root';
const DB_PASSWORD = process.env.DB_PASSWORD || 'root';
const BASE_URL = (process.env.API_BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');
const TEST_PASSWORD = 'Pass123456';
const PASSWORD_HASH = '$2y$10$vatwVgpxXSnmzxkxog.2s.M.cW8G60pnlKlytftZnVbyQqrLJavjW';
const RUN_ID = String(Date.now());
const PREFIX = `approval-interface-${RUN_ID}`;

interface ReimbursementFixture {
  rejected: {
    reimbursementId: number;
    oldInstanceId: number;
  };
  pending: {
    reimbursementId: number;
    oldInstanceId: number;
  };
}

interface InvoiceFixture {
  applicationId: number;
}

let accountSetId = 0;
let initiatorId = 0;
let approverId = 0;
let outsiderId = 0;
let initiatorEmail = '';
let outsiderEmail = '';
let reimbursementFixtures: ReimbursementFixture;
let invoiceFixture: InvoiceFixture;

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

  return output.split(/\r?\n/).filter(Boolean).map((line) => line.split('\t'));
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

function sqlIdList(ids: number[]): string {
  return ids.length ? ids.join(',') : '0';
}

function authHeaders(token: string): Record<string, string> {
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
      password: TEST_PASSWORD,
    },
  });

  const body = await expectJson(response);
  expect(body.success).toBe(true);
  return body.data.token;
}

function createUser(name: string, email: string, role: string): number {
  return querySingleNumber(`
    INSERT INTO users (name, email, password, role, is_active, created_at, updated_at)
    VALUES (${sqlValue(name)}, ${sqlValue(email)}, ${sqlValue(PASSWORD_HASH)}, ${sqlValue(role)}, 1, NOW(), NOW());
    SELECT LAST_INSERT_ID();
  `);
}

function cleanupPrefix(): void {
  const accountSetIds = queryRows(`
    SELECT id FROM account_sets WHERE code = ${sqlValue(`${PREFIX}-account`)}
  `).map(([id]) => Number(id));
  const userIds = queryRows(`
    SELECT id FROM users WHERE email LIKE ${sqlValue(`${PREFIX}-%`)}
  `).map(([id]) => Number(id));

  const accountSetIn = sqlIdList(accountSetIds);
  const userIn = sqlIdList(userIds);

  const approvalInstanceIds = queryRows(`
    SELECT id FROM approval_instances WHERE account_set_id IN (${accountSetIn})
  `).map(([id]) => Number(id));
  const reimbursementIds = queryRows(`
    SELECT id FROM reimbursements WHERE account_set_id IN (${accountSetIn})
  `).map(([id]) => Number(id));
  const invoiceApplicationIds = queryRows(`
    SELECT id FROM invoice_applications WHERE account_set_id IN (${accountSetIn})
  `).map(([id]) => Number(id));

  const approvalInstanceIn = sqlIdList(approvalInstanceIds);
  const reimbursementIn = sqlIdList(reimbursementIds);
  const invoiceApplicationIn = sqlIdList(invoiceApplicationIds);

  const statements = [
    `DELETE FROM approval_attachments WHERE instance_id IN (${approvalInstanceIn})`,
    `DELETE FROM approval_cc_users WHERE instance_id IN (${approvalInstanceIn})`,
    `DELETE FROM approval_records WHERE instance_id IN (${approvalInstanceIn})`,
    `DELETE FROM approval_instances WHERE id IN (${approvalInstanceIn})`,
    `DELETE FROM reimbursement_attachments WHERE reimbursement_id IN (${reimbursementIn})`,
    `DELETE FROM reimbursements WHERE id IN (${reimbursementIn})`,
    `DELETE FROM invoice_content_items WHERE application_id IN (${invoiceApplicationIn})`,
    `DELETE FROM invoice_items WHERE application_id IN (${invoiceApplicationIn})`,
    `DELETE FROM invoice_applications WHERE id IN (${invoiceApplicationIn})`,
    `DELETE FROM pending_tasks WHERE account_set_id IN (${accountSetIn})`,
    `DELETE FROM approval_flow_configs WHERE account_set_id IN (${accountSetIn})`,
    `DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\\\\Models\\\\User' AND tokenable_id IN (${userIn})`,
    `DELETE FROM account_set_users WHERE account_set_id IN (${accountSetIn}) OR user_id IN (${userIn})`,
    `DELETE FROM account_sets WHERE id IN (${accountSetIn})`,
    `DELETE FROM users WHERE id IN (${userIn})`,
  ];

  for (const sql of statements) {
    runSql(sql);
  }
}

function createReimbursementScenario(status: 'rejected' | 'pending', key: string): { reimbursementId: number; oldInstanceId: number } {
  const reimbursementId = querySingleNumber(`
    INSERT INTO reimbursements
      (account_set_id, company_name, applicant, amount, category, project, reason, status, created_by, created_at, updated_at)
    VALUES
      (${accountSetId}, ${sqlValue(`${PREFIX}-company`)}, ${sqlValue(`${PREFIX}-applicant`)}, 321.00, '报销', ${sqlValue(`${PREFIX}-project`)}, ${sqlValue(`${PREFIX}-${key}-reason`)}, ${sqlValue(status)}, ${initiatorId}, NOW(), NOW());
    SELECT LAST_INSERT_ID();
  `);

  const oldInstanceId = querySingleNumber(`
    INSERT INTO approval_instances
      (account_set_id, business_type, business_id, current_step, total_steps, status, stamp_method, created_by, created_at, updated_at, completed_at)
    VALUES
      (${accountSetId}, '报销申请', ${reimbursementId}, 2, 2, ${sqlValue(status === 'rejected' ? 'rejected' : 'pending')}, 'online', ${initiatorId}, NOW(), NOW(), ${status === 'rejected' ? 'NOW()' : 'NULL'});
    SELECT LAST_INSERT_ID();
  `);

  runSql(`
    UPDATE reimbursements
    SET approval_flow_id = ${oldInstanceId}
    WHERE id = ${reimbursementId};
  `);

  runSql(`
    INSERT INTO approval_records
      (instance_id, step_order, step_name, approver_id, approver_name, status, comment, approved_at, created_at, updated_at)
    VALUES
      (${oldInstanceId}, 1, '第1级审批', ${approverId}, ${sqlValue(`${PREFIX}-approver`)}, ${sqlValue(status === 'rejected' ? 'rejected' : 'pending')}, ${status === 'rejected' ? sqlValue('驳回测试') : 'NULL'}, ${status === 'rejected' ? 'NOW()' : 'NULL'}, NOW(), NOW());
  `);

  runSql(`
    INSERT INTO approval_attachments
      (instance_id, file_path, file_name, file_size, file_type, uploaded_by, created_at)
    VALUES
      (${oldInstanceId}, 'tests/api/test-document.pdf', ${sqlValue(`${key}.pdf`)}, 302, 'application/pdf', ${initiatorId}, NOW());
  `);

  runSql(`
    INSERT INTO reimbursement_attachments
      (reimbursement_id, file_name, file_path, file_type, file_size, created_at, updated_at)
    VALUES
      (${reimbursementId}, ${sqlValue(`${key}.pdf`)}, 'tests/api/test-document.pdf', 'application/pdf', 302, NOW(), NOW());
  `);

  return { reimbursementId, oldInstanceId };
}

function createInvoiceApplicationScenario(): { applicationId: number } {
  const applicationNo = `INV${RUN_ID}001`;

  const applicationId = querySingleNumber(`
    INSERT INTO invoice_applications
      (
        account_set_id, application_no, task_name, year, month, project_name, remark, status, approval_status,
        submitter_id, submitted_at, created_by, period_year, period_month, company_name, application_date,
        invoice_method, invoice_type, deduction_amount, tax_rate, amount_excluding_tax, invoice_tax_amount,
        invoice_amount, tax_amount, invoice_date, invoice_number, attachments, created_at, updated_at
      )
    VALUES
      (
        ${accountSetId},
        ${sqlValue(applicationNo)},
        ${sqlValue(`${PREFIX}-invoice-task`)},
        2026,
        7,
        ${sqlValue(`${PREFIX}-invoice-project`)},
        ${sqlValue('invoice resubmit coverage')},
        'normal',
        NULL,
        ${initiatorId},
        NULL,
        ${initiatorId},
        2026,
        6,
        ${sqlValue(`${PREFIX}-invoice-company`)},
        '2026-07-19',
        'none',
        ${sqlValue('普通发票')},
        0,
        0,
        0,
        0,
        1000,
        0,
        '2026-07-19',
        ${sqlValue(`NO-${RUN_ID}`)},
        ${sqlValue('[{\"path\":\"tests/api/test-document.pdf\",\"filename\":\"invoice-attachment.pdf\",\"size\":302}]')},
        NOW(),
        NOW()
      );
    SELECT LAST_INSERT_ID();
  `);

  return { applicationId };
}

function createFixtures(): void {
  cleanupPrefix();

  initiatorEmail = `${PREFIX}-initiator@example.test`;
  outsiderEmail = `${PREFIX}-outsider@example.test`;

  initiatorId = createUser(`${PREFIX}-initiator`, initiatorEmail, 'employee');
  approverId = createUser(`${PREFIX}-approver`, `${PREFIX}-approver@example.test`, 'manager');
  outsiderId = createUser(`${PREFIX}-outsider`, outsiderEmail, 'employee');

  accountSetId = querySingleNumber(`
    INSERT INTO account_sets (name, code, status, created_by, created_at, updated_at)
    VALUES (${sqlValue(`${PREFIX}-account`)}, ${sqlValue(`${PREFIX}-account`)}, 'active', ${initiatorId}, NOW(), NOW());
    SELECT LAST_INSERT_ID();
  `);

  runSql(`
    INSERT INTO account_set_users
      (account_set_id, user_id, role, approval_level, approval_level_name, is_default, created_at, updated_at)
    VALUES
      (${accountSetId}, ${initiatorId}, 'owner', 1, '第1级审批', 1, NOW(), NOW()),
      (${accountSetId}, ${approverId}, 'viewer', 2, '第2级审批', 1, NOW(), NOW()),
      (${accountSetId}, ${outsiderId}, 'viewer', NULL, NULL, 1, NOW(), NOW());
  `);

  runSql(`
    UPDATE users
    SET account_set_id = ${accountSetId}, current_account_set_id = ${accountSetId}
    WHERE id IN (${initiatorId}, ${approverId}, ${outsiderId});
  `);

  runSql(`
    INSERT INTO approval_flow_configs (account_set_id, business_type, enabled_levels, created_at, updated_at)
    VALUES
      (${accountSetId}, '报销申请', '[2]', NOW(), NOW()),
      (${accountSetId}, '发票申请', '[1,2]', NOW(), NOW());
  `);

  reimbursementFixtures = {
    rejected: createReimbursementScenario('rejected', 'reimbursement-rejected'),
    pending: createReimbursementScenario('pending', 'reimbursement-pending'),
  };

  invoiceFixture = createInvoiceApplicationScenario();
}

test.describe('重新发起审批接口覆盖', () => {
  test.beforeAll(() => {
    createFixtures();
  });

  test.afterAll(() => {
    cleanupPrefix();
  });

  test('通用 approvals/resubmit 可以重新发起被驳回的报销申请', async ({ request }) => {
    const token = await login(request, initiatorEmail);
    const { reimbursementId, oldInstanceId } = reimbursementFixtures.rejected;

    const response = await request.post(apiUrl('approvals/resubmit'), {
      headers: authHeaders(token),
      data: {
        business_type: '报销申请',
        business_id: reimbursementId,
        stamp_method: 'online',
        stamp_selection_mode: 'none',
      },
    });
    const body = await expectJson(response);
    expect(body.success).toBe(true);
    expect(body.message).toBe('重新发起成功');
    expect(body.data.instance_id).toEqual(expect.any(Number));

    const [reimbursementRow] = queryRows(`
      SELECT status, approval_flow_id
      FROM reimbursements
      WHERE id = ${reimbursementId}
    `);
    expect(reimbursementRow[0]).toBe('pending');
    const newInstanceId = Number(reimbursementRow[1]);
    expect(newInstanceId).not.toBe(oldInstanceId);

    const oldInstanceRows = queryRows(`
      SELECT id FROM approval_instances WHERE id = ${oldInstanceId}
    `);
    expect(oldInstanceRows).toHaveLength(0);

    const newRecordRows = queryRows(`
      SELECT step_order, status, approver_id
      FROM approval_records
      WHERE instance_id = ${newInstanceId}
      ORDER BY step_order
    `);
    expect(newRecordRows).toEqual([
      ['1', 'pending', String(approverId)],
    ]);
  });

  test('通用 approvals/resubmit 会拦住非发起人', async ({ request }) => {
    const token = await login(request, outsiderEmail);
    const { reimbursementId } = reimbursementFixtures.pending;

    const response = await request.post(apiUrl('approvals/resubmit'), {
      headers: authHeaders(token),
      data: {
        business_type: '报销申请',
        business_id: reimbursementId,
      },
    });
    const body = await expectJson(response, 403);
    expect(body.success).toBe(false);
    expect(body.message).toBe('只有发起人才能重新发起');
  });

  test('通用 approvals/resubmit 会拦住非驳回状态的报销申请', async ({ request }) => {
    const token = await login(request, initiatorEmail);
    const { reimbursementId, oldInstanceId } = reimbursementFixtures.pending;

    const response = await request.post(apiUrl('approvals/resubmit'), {
      headers: authHeaders(token),
      data: {
        business_type: '报销申请',
        business_id: reimbursementId,
      },
    });
    const body = await expectJson(response, 400);
    expect(body.success).toBe(false);
    expect(body.message).toBe('当前状态不允许重新发起');

    const [row] = queryRows(`
      SELECT status, approval_flow_id
      FROM reimbursements
      WHERE id = ${reimbursementId}
    `);
    expect(row).toEqual(['pending', String(oldInstanceId)]);
  });

  test('发票申请 invoice-applications/{id}/resubmit 可以重新提交进入审批', async ({ request }) => {
    const token = await login(request, initiatorEmail);
    const { applicationId } = invoiceFixture;

    const response = await request.post(apiUrl(`invoice-applications/${applicationId}/resubmit`), {
      headers: authHeaders(token),
      data: {
        stamp_method: 'online',
        stamp_selection_mode: 'none',
      },
    });
    const body = await expectJson(response);
    expect(body.success).toBe(true);
    expect(body.message).toBe('提交成功');

    const [applicationRow] = queryRows(`
      SELECT approval_status, approval_instance_id, submitter_id
      FROM invoice_applications
      WHERE id = ${applicationId}
    `);
    expect(applicationRow[0]).toBe('pending');
    expect(Number(applicationRow[1])).toBeGreaterThan(0);
    expect(applicationRow[2]).toBe(String(initiatorId));

    const invoiceInstanceId = Number(applicationRow[1]);
    const recordRows = queryRows(`
      SELECT step_order, status, approver_id
      FROM approval_records
      WHERE instance_id = ${invoiceInstanceId}
      ORDER BY step_order
    `);
    expect(recordRows).toEqual([
      ['1', 'approved', String(initiatorId)],
      ['2', 'pending', String(approverId)],
    ]);
  });

  test('发票申请 invoice-applications/{id}/resubmit 会拦住非填写节点人员', async ({ request }) => {
    const token = await login(request, outsiderEmail);
    const applicationNo = `INV${RUN_ID}002`;
    const applicationId = querySingleNumber(`
      INSERT INTO invoice_applications
        (
          account_set_id, application_no, task_name, year, month, project_name, remark, status, approval_status,
          submitter_id, submitted_at, created_by, period_year, period_month, company_name, application_date,
          invoice_method, invoice_type, deduction_amount, tax_rate, amount_excluding_tax, invoice_tax_amount,
          invoice_amount, tax_amount, invoice_date, invoice_number, attachments, created_at, updated_at
        )
      VALUES
        (
          ${accountSetId},
          ${sqlValue(applicationNo)},
          ${sqlValue(`${PREFIX}-invoice-task-outsider`)},
          2026,
          7,
          ${sqlValue(`${PREFIX}-invoice-project-outsider`)},
          ${sqlValue('invoice outsider test')},
          'normal',
          NULL,
          ${initiatorId},
          NULL,
          ${initiatorId},
          2026,
          6,
          ${sqlValue(`${PREFIX}-invoice-company`)},
          '2026-07-19',
          'none',
          ${sqlValue('普通发票')},
          0,
          0,
          0,
          0,
          1000,
          0,
          '2026-07-19',
          ${sqlValue(`NO-${RUN_ID}-2`)},
          ${sqlValue('[{\"path\":\"tests/api/test-document.pdf\",\"filename\":\"invoice-attachment-2.pdf\",\"size\":302}]')},
          NOW(),
          NOW()
        );
      SELECT LAST_INSERT_ID();
    `);

    const response = await request.post(apiUrl(`invoice-applications/${applicationId}/resubmit`), {
      headers: authHeaders(token),
      data: {
        stamp_method: 'online',
        stamp_selection_mode: 'none',
      },
    });
    const body = await expectJson(response, 403);
    expect(body.success).toBe(false);
    expect(body.message).toBe('只有第一个有效审批节点人员才能填写并提交审批');
  });
});
