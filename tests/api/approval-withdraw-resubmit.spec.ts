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
const PREFIX = `withdraw-resubmit-${RUN_ID}`;

interface ScenarioFixture {
  paymentRequestId: number;
  approvalInstanceId: number;
}

interface TestFixtures {
  withdrawableReimbursement: ScenarioFixture;
  pendingReimbursement: ScenarioFixture;
  withdrawnNoAttachmentReimbursement: ScenarioFixture;
  withdrawnSalary: ScenarioFixture;
  withdrawnInsurance: ScenarioFixture;
}

let accountSetId = 0;
let initiatorId = 0;
let approverId = 0;
let thirdApproverId = 0;
let initiatorEmail = '';
let fixtures: TestFixtures;

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
  const paymentRequestIds = queryRows(`
    SELECT id FROM payment_requests WHERE account_set_id IN (${accountSetIn})
  `).map(([id]) => Number(id));
  const approvalInstanceIn = sqlIdList(approvalInstanceIds);
  const paymentRequestIn = sqlIdList(paymentRequestIds);

  const statements = [
    `DELETE FROM approval_attachments WHERE instance_id IN (${approvalInstanceIn})`,
    `DELETE FROM approval_cc_users WHERE instance_id IN (${approvalInstanceIn})`,
    `DELETE FROM approval_records WHERE instance_id IN (${approvalInstanceIn})`,
    `DELETE FROM approval_instances WHERE id IN (${approvalInstanceIn})`,
    `DELETE FROM payment_request_attachments WHERE payment_request_id IN (${paymentRequestIn})`,
    `DELETE FROM payment_requests WHERE id IN (${paymentRequestIn})`,
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

function createUser(name: string, email: string, role: string): number {
  return querySingleNumber(`
    INSERT INTO users (name, email, password, role, is_active, created_at, updated_at)
    VALUES (${sqlValue(name)}, ${sqlValue(email)}, ${sqlValue(PASSWORD_HASH)}, ${sqlValue(role)}, 1, NOW(), NOW());
    SELECT LAST_INSERT_ID();
  `);
}

function createPaymentScenario(options: {
  key: string;
  paymentType: 'reimbursement' | 'salary' | 'insurance';
  businessType: string;
  paymentStatus: 'pending' | 'rejected';
  approvalStatus: 'pending' | 'withdrawn' | 'rejected';
  currentStep: number;
  totalSteps: number;
  records: Array<{
    stepOrder: number;
    approverId: number;
    approverName: string;
    status: 'approved' | 'pending' | 'waiting' | 'withdrawn';
    comment?: string | null;
    approvedAt?: 'now' | null;
  }>;
  withAttachment: boolean;
}): ScenarioFixture {
  const amount = options.paymentType === 'salary' ? 888.88 : options.paymentType === 'insurance' ? 666.66 : 123.45;
  const paymentRequestId = querySingleNumber(`
    INSERT INTO payment_requests
      (payment_type, category, account_set_id, project_ids, amount, status, payment_method, submitted_by, submitted_at, remarks, created_at, updated_at)
    VALUES
      (${sqlValue(options.paymentType)}, ${sqlValue(options.paymentType)}, ${accountSetId}, '[]', ${amount}, ${sqlValue(options.paymentStatus)}, 'transfer', ${initiatorId}, NOW(), ${sqlValue(`${PREFIX}-${options.key}`)}, NOW(), NOW());
    SELECT LAST_INSERT_ID();
  `);

  const approvalInstanceId = querySingleNumber(`
    INSERT INTO approval_instances
      (account_set_id, business_type, business_id, current_step, total_steps, status, stamp_method, created_by, created_at, updated_at, completed_at)
    VALUES
      (${accountSetId}, ${sqlValue(options.businessType)}, ${paymentRequestId}, ${options.currentStep}, ${options.totalSteps}, ${sqlValue(options.approvalStatus)}, 'online', ${initiatorId}, NOW(), NOW(), ${options.approvalStatus === 'pending' ? 'NULL' : 'NOW()'});
    SELECT LAST_INSERT_ID();
  `);

  runSql(`
    UPDATE payment_requests
    SET approval_instance_id = ${approvalInstanceId}
    WHERE id = ${paymentRequestId};
  `);

  for (const record of options.records) {
    runSql(`
      INSERT INTO approval_records
        (instance_id, step_order, step_name, approver_id, approver_name, status, comment, approved_at, created_at, updated_at)
      VALUES
        (
          ${approvalInstanceId},
          ${record.stepOrder},
          ${sqlValue(`第${record.stepOrder}级审批`)},
          ${record.approverId},
          ${sqlValue(record.approverName)},
          ${sqlValue(record.status)},
          ${record.comment === undefined ? 'NULL' : sqlValue(record.comment)},
          ${record.approvedAt === 'now' ? 'NOW()' : 'NULL'},
          NOW(),
          NOW()
        );
    `);
  }

  if (options.withAttachment) {
    runSql(`
      INSERT INTO payment_request_attachments
        (payment_request_id, filename, file_path, file_size, mime_type, attachment_type, uploaded_by, created_at, updated_at)
      VALUES
        (${paymentRequestId}, ${sqlValue(`${options.key}.pdf`)}, 'tests/api/test-document.pdf', 302, 'application/pdf', 'attachment', ${initiatorId}, NOW(), NOW());
    `);
  }

  return {
    paymentRequestId,
    approvalInstanceId,
  };
}

function createFixture(): void {
  cleanupPrefix();

  initiatorEmail = `${PREFIX}-initiator@example.test`;
  const approverEmail = `${PREFIX}-approver@example.test`;
  const thirdApproverEmail = `${PREFIX}-approver-3@example.test`;

  initiatorId = createUser(`${PREFIX}-initiator`, initiatorEmail, 'employee');
  approverId = createUser(`${PREFIX}-approver`, approverEmail, 'manager');
  thirdApproverId = createUser(`${PREFIX}-approver-3`, thirdApproverEmail, 'manager');

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
      (${accountSetId}, ${thirdApproverId}, 'viewer', 3, '第3级审批', 0, NOW(), NOW());
  `);

  runSql(`
    UPDATE users
    SET account_set_id = ${accountSetId}, current_account_set_id = ${accountSetId}
    WHERE id IN (${initiatorId}, ${approverId}, ${thirdApproverId});
  `);

  runSql(`
    INSERT INTO approval_flow_configs (account_set_id, business_type, enabled_levels, created_at, updated_at)
    VALUES (${accountSetId}, '报销付款申请', '[2]', NOW(), NOW());
  `);

  fixtures = {
    withdrawableReimbursement: createPaymentScenario({
      key: 'reimbursement-withdrawable',
      paymentType: 'reimbursement',
      businessType: '报销付款申请',
      paymentStatus: 'pending',
      approvalStatus: 'pending',
      currentStep: 2,
      totalSteps: 3,
      records: [
        { stepOrder: 1, approverId: initiatorId, approverName: `${PREFIX}-initiator`, status: 'approved', comment: '经办提交', approvedAt: 'now' },
        { stepOrder: 2, approverId: approverId, approverName: `${PREFIX}-approver`, status: 'pending' },
        { stepOrder: 3, approverId: thirdApproverId, approverName: `${PREFIX}-approver-3`, status: 'waiting' },
      ],
      withAttachment: true,
    }),
    pendingReimbursement: createPaymentScenario({
      key: 'reimbursement-pending',
      paymentType: 'reimbursement',
      businessType: '报销付款申请',
      paymentStatus: 'pending',
      approvalStatus: 'pending',
      currentStep: 2,
      totalSteps: 2,
      records: [
        { stepOrder: 1, approverId: initiatorId, approverName: `${PREFIX}-initiator`, status: 'approved', comment: '经办提交', approvedAt: 'now' },
        { stepOrder: 2, approverId: approverId, approverName: `${PREFIX}-approver`, status: 'pending' },
      ],
      withAttachment: true,
    }),
    withdrawnNoAttachmentReimbursement: createPaymentScenario({
      key: 'reimbursement-withdrawn-no-attachment',
      paymentType: 'reimbursement',
      businessType: '报销付款申请',
      paymentStatus: 'rejected',
      approvalStatus: 'withdrawn',
      currentStep: 2,
      totalSteps: 2,
      records: [
        { stepOrder: 1, approverId: initiatorId, approverName: `${PREFIX}-initiator`, status: 'approved', comment: '经办提交', approvedAt: 'now' },
        { stepOrder: 2, approverId: approverId, approverName: `${PREFIX}-approver`, status: 'withdrawn' },
      ],
      withAttachment: false,
    }),
    withdrawnSalary: createPaymentScenario({
      key: 'salary-withdrawn',
      paymentType: 'salary',
      businessType: '工资付款申请',
      paymentStatus: 'rejected',
      approvalStatus: 'withdrawn',
      currentStep: 2,
      totalSteps: 2,
      records: [
        { stepOrder: 1, approverId: initiatorId, approverName: `${PREFIX}-initiator`, status: 'approved', comment: '经办提交', approvedAt: 'now' },
        { stepOrder: 2, approverId: approverId, approverName: `${PREFIX}-approver`, status: 'withdrawn' },
      ],
      withAttachment: true,
    }),
    withdrawnInsurance: createPaymentScenario({
      key: 'insurance-withdrawn',
      paymentType: 'insurance',
      businessType: '保险汇总付款申请',
      paymentStatus: 'rejected',
      approvalStatus: 'withdrawn',
      currentStep: 2,
      totalSteps: 2,
      records: [
        { stepOrder: 1, approverId: initiatorId, approverName: `${PREFIX}-initiator`, status: 'approved', comment: '经办提交', approvedAt: 'now' },
        { stepOrder: 2, approverId: approverId, approverName: `${PREFIX}-approver`, status: 'withdrawn' },
      ],
      withAttachment: true,
    }),
  };
}

test.describe('审批撤回后业务重新提交', () => {
  test.beforeAll(() => {
    createFixture();
  });

  test.afterAll(() => {
    cleanupPrefix();
  });

  test('撤回后付款申请回到可重提状态，并能重新发起审批', async ({ request }) => {
    const token = await login(request, initiatorEmail);
    const { paymentRequestId, approvalInstanceId } = fixtures.withdrawableReimbursement;

    const withdrawResponse = await request.post(apiUrl(`approvals/${approvalInstanceId}/withdraw`), {
      headers: authHeaders(token),
    });
    const withdrawBody = await expectJson(withdrawResponse);
    expect(withdrawBody.success).toBe(true);
    expect(withdrawBody.data.status).toBe('withdrawn');

    const afterWithdraw = queryRows(`
      SELECT pr.status, ai.status
      FROM payment_requests pr
      JOIN approval_instances ai ON ai.id = pr.approval_instance_id
      WHERE pr.id = ${paymentRequestId}
    `);
    expect(afterWithdraw).toEqual([['rejected', 'withdrawn']]);

    const recordRows = queryRows(`
      SELECT step_order, status
      FROM approval_records
      WHERE instance_id = ${approvalInstanceId}
      ORDER BY step_order
    `);
    expect(recordRows).toEqual([
      ['1', 'approved'],
      ['2', 'withdrawn'],
      ['3', 'withdrawn'],
    ]);

    const resubmitResponse = await request.post(apiUrl(`payment-applications/${paymentRequestId}/resubmit`), {
      headers: authHeaders(token),
      data: {
        stamp_method: 'online',
        stamp_selection_mode: 'none',
        description: `${PREFIX}-resubmitted`,
      },
    });
    const resubmitBody = await expectJson(resubmitResponse);
    expect(resubmitBody.success).toBe(true);
    expect(resubmitBody.message).toBe('重新发起审批成功');

    const [paymentRow] = queryRows(`
      SELECT status, approval_instance_id
      FROM payment_requests
      WHERE id = ${paymentRequestId}
    `);
    expect(paymentRow[0]).toBe('pending');
    const newApprovalInstanceId = Number(paymentRow[1]);
    expect(newApprovalInstanceId).not.toBe(approvalInstanceId);

    const instanceRows = queryRows(`
      SELECT id, status
      FROM approval_instances
      WHERE id IN (${approvalInstanceId}, ${newApprovalInstanceId})
      ORDER BY id
    `);
    expect(instanceRows).toContainEqual([String(approvalInstanceId), 'withdrawn']);
    expect(instanceRows).toContainEqual([String(newApprovalInstanceId), 'pending']);

    const newRecords = queryRows(`
      SELECT step_order, status
      FROM approval_records
      WHERE instance_id = ${newApprovalInstanceId}
      ORDER BY step_order
    `);
    expect(newRecords.length).toBeGreaterThan(0);
    expect(newRecords).toContainEqual(['1', 'pending']);
  });

  test('未撤回或未驳回的付款申请仍然不能重新发起审批', async ({ request }) => {
    const token = await login(request, initiatorEmail);
    const { paymentRequestId } = fixtures.pendingReimbursement;

    const response = await request.post(apiUrl(`payment-applications/${paymentRequestId}/resubmit`), {
      headers: authHeaders(token),
      data: {
        stamp_method: 'online',
        stamp_selection_mode: 'none',
      },
    });
    const body = await expectJson(response, 400);
    expect(body.success).toBe(false);
    expect(body.message).toBe('只有被驳回或撤回的申请才能重新发起审批');
  });

  test('撤回后若没有附件，重新发起审批会被拦住且状态回滚', async ({ request }) => {
    const token = await login(request, initiatorEmail);
    const { paymentRequestId, approvalInstanceId } = fixtures.withdrawnNoAttachmentReimbursement;

    const response = await request.post(apiUrl(`payment-applications/${paymentRequestId}/resubmit`), {
      headers: authHeaders(token),
      data: {
        stamp_method: 'online',
        stamp_selection_mode: 'none',
        description: `${PREFIX}-should-fail-no-attachment`,
      },
    });
    const body = await expectJson(response, 400);
    expect(body.success).toBe(false);
    expect(body.message).toBe('请至少上传一个附件后再重新发起审批');

    const paymentRows = queryRows(`
      SELECT status, approval_instance_id, remarks
      FROM payment_requests
      WHERE id = ${paymentRequestId}
    `);
    expect(paymentRows).toEqual([[
      'rejected',
      String(approvalInstanceId),
      `${PREFIX}-reimbursement-withdrawn-no-attachment`,
    ]]);
  });

  test('工资和保险付款申请仍然要求回原模块重新发起', async ({ request }) => {
    const token = await login(request, initiatorEmail);

    for (const fixture of [fixtures.withdrawnSalary, fixtures.withdrawnInsurance]) {
      const response = await request.post(apiUrl(`payment-applications/${fixture.paymentRequestId}/resubmit`), {
        headers: authHeaders(token),
        data: {
          stamp_method: 'online',
          stamp_selection_mode: 'none',
        },
      });
      const body = await expectJson(response, 400);
      expect(body.success).toBe(false);
      expect(body.message).toBe('工资或汇总付款申请请回原模块重新发起');
    }
  });
});
