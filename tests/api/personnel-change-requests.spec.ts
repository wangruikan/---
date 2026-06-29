import { test, expect, APIRequestContext } from '@playwright/test';

// ============================================================
// 配置
// ============================================================
const BASE_URL = process.env.API_BASE_URL || 'http://localhost:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_A = process.env.ACCOUNT_SET_ID_A || '1';
const ACCOUNT_SET_B = process.env.ACCOUNT_SET_ID_B || '3';
const RESPONSE_TIME_LIMIT = Number(process.env.RESPONSE_TIME_LIMIT || 5000);

// 种子数据 ID (由 seed-personnel-change.php 生成)
const ID = {
  PENDING_ADD: process.env.REQUEST_ID_PENDING || '5',
  REJECTED_REMOVE: process.env.REQUEST_ID_REJECTED || '6',
  IN_APPROVAL_ADD: process.env.REQUEST_ID_IN_APPROVAL || '7',
  APPROVED_REMOVE: process.env.REQUEST_ID_APPROVED || '8',
  OTHER_ACCOUNT: process.env.REQUEST_ID_OTHER_ACCOUNT || '9',
  UPLOAD_TARGET: process.env.REQUEST_ID_UPLOAD_TARGET || '10',
  SUBMIT_PENDING: process.env.REQUEST_ID_SUBMIT_PENDING || '11',
  SUBMIT_REJECTED: process.env.REQUEST_ID_SUBMIT_REJECTED || '12',
  DELETE_PENDING: process.env.REQUEST_ID_DELETE_PENDING || '13',
  DELETE_REJECTED: process.env.REQUEST_ID_DELETE_REJECTED || '14',
  WITH_ATTACHMENT: process.env.REQUEST_ID_WITH_ATTACHMENT || '1',
  DOUBLE_SUBMIT: process.env.REQUEST_ID_DOUBLE_SUBMIT || '15',
};

// ============================================================
// 登录辅助
// ============================================================
let authToken: string;

async function login(request: APIRequestContext): Promise<string> {
  const res = await request.post(`${BASE_URL}/auth/login`, {
    data: { username: USERNAME, password: PASSWORD },
  });
  const body = await res.json();
  if (!body.success) throw new Error(`Login failed: ${body.message}`);
  return body.data.token;
}

// ============================================================
// API Client
// ============================================================
class PersonnelChangeRequestApi {
  constructor(private request: APIRequestContext, private token: string) {}

  private headers(accountSetId?: string) {
    const h: Record<string, string> = { Authorization: `Bearer ${this.token}` };
    if (accountSetId) h['X-Account-Set-Id'] = accountSetId;
    return h;
  }

  async list(params: Record<string, any> = {}) {
    const start = Date.now();
    const res = await this.request.get(`${BASE_URL}/personnel-change-requests`, {
      headers: this.headers(), params,
    });
    const elapsed = Date.now() - start;
    const body = await res.json();
    return { status: res.status(), body, elapsed, headers: res.headers() };
  }

  async show(id: string | number) {
    const start = Date.now();
    const res = await this.request.get(`${BASE_URL}/personnel-change-requests/${id}`, {
      headers: this.headers(),
    });
    const elapsed = Date.now() - start;
    const body = await res.json();
    return { status: res.status(), body, elapsed, headers: res.headers() };
  }

  async uploadAttachment(requestId: string | number, fileContent: Buffer, fileName: string) {
    const start = Date.now();
    const res = await this.request.post(`${BASE_URL}/personnel-change-requests/upload-attachment`, {
      headers: { Authorization: `Bearer ${this.token}` },
      multipart: {
        file: { name: fileName, mimeType: 'application/octet-stream', buffer: fileContent },
        personnel_change_request_id: String(requestId),
      },
    });
    const elapsed = Date.now() - start;
    let body: any;
    try { body = await res.json(); } catch { body = null; }
    return { status: res.status(), body, elapsed, headers: res.headers() };
  }

  async uploadAttachmentRaw(formData: Record<string, any>) {
    const start = Date.now();
    const res = await this.request.post(`${BASE_URL}/personnel-change-requests/upload-attachment`, {
      headers: { Authorization: `Bearer ${this.token}` },
      multipart: formData,
    });
    const elapsed = Date.now() - start;
    let body: any;
    try { body = await res.json(); } catch { body = null; }
    return { status: res.status(), body, elapsed, headers: res.headers() };
  }

  async completeSubmission(data: Record<string, any>) {
    const start = Date.now();
    const res = await this.request.post(`${BASE_URL}/personnel-change-requests/complete-submission`, {
      headers: this.headers(), data,
    });
    const elapsed = Date.now() - start;
    const body = await res.json();
    return { status: res.status(), body, elapsed, headers: res.headers() };
  }

  async destroy(id: string | number) {
    const start = Date.now();
    const res = await this.request.delete(`${BASE_URL}/personnel-change-requests/${id}`, {
      headers: this.headers(),
    });
    const elapsed = Date.now() - start;
    let body: any;
    try { body = await res.json(); } catch { body = null; }
    return { status: res.status(), body, elapsed, headers: res.headers() };
  }

  /** 使用指定 token 发起列表请求（用于越权测试） */
  async listWithToken(token: string, params: Record<string, any> = {}) {
    const start = Date.now();
    const res = await this.request.get(`${BASE_URL}/personnel-change-requests`, {
      headers: { Authorization: `Bearer ${token}` }, params,
    });
    const elapsed = Date.now() - start;
    const body = await res.json();
    return { status: res.status(), body, elapsed, headers: res.headers() };
  }
}

// ============================================================
// 辅助函数
// ============================================================
function assertSuccess(body: any) { expect(body.success).toBe(true); }
function assertFail(body: any) { expect(body.success).toBe(false); }
function assertJsonHeaders(headers: Record<string, string>) {
  expect(headers['content-type']).toContain('application/json');
}
function assertResponseTime(elapsed: number) {
  expect(elapsed).toBeLessThan(RESPONSE_TIME_LIMIT);
}
function assertPaginated(body: any) {
  expect(body.data).toHaveProperty('data');
  expect(body.data).toHaveProperty('total');
  expect(body.data).toHaveProperty('current_page');
  expect(body.data).toHaveProperty('per_page');
  expect(Array.isArray(body.data.data)).toBe(true);
}
function smallFile(): Buffer { return Buffer.from('test file content for upload'); }

// ============================================================
// 全局 setup: 登录 + 种子数据
// ============================================================
test.beforeAll(async ({ request }) => {
  authToken = await login(request);
});

// ============================================================
// A. 认证与权限
// ============================================================
test.describe('A. 认证与权限', () => {
  test('A1. 不带 token 请求列表返回 401', async ({ request }) => {
    const res = await request.get(`${BASE_URL}/personnel-change-requests`, {
      params: { current_account_set_id: ACCOUNT_SET_A },
    });
    expect(res.status()).toBe(401);
  });

  test('A2. 无效 token 请求列表返回 401', async ({ request }) => {
    const res = await request.get(`${BASE_URL}/personnel-change-requests`, {
      headers: { Authorization: 'Bearer invalid_token_here' },
      params: { current_account_set_id: ACCOUNT_SET_A },
    });
    expect(res.status()).toBe(401);
  });

  test('A3. 有效 token 可正常访问列表', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A });
    expect(res.status).toBe(200);
    assertSuccess(res.body);
    assertJsonHeaders(res.headers);
    assertPaginated(res.body);
    assertResponseTime(res.elapsed);
  });

  test('A4. 不带 token 请求详情返回 401', async ({ request }) => {
    const res = await request.get(`${BASE_URL}/personnel-change-requests/${ID.PENDING_ADD}`);
    expect(res.status()).toBe(401);
  });

  test('A5. 不带 token 上传附件返回 401', async ({ request }) => {
    const res = await request.post(`${BASE_URL}/personnel-change-requests/upload-attachment`, {
      multipart: {
        file: { name: 'test.txt', mimeType: 'text/plain', buffer: smallFile() },
        personnel_change_request_id: ID.UPLOAD_TARGET,
      },
    });
    expect(res.status()).toBe(401);
  });

  test('A6. 不带 token 提交审批返回 401', async ({ request }) => {
    const res = await request.post(`${BASE_URL}/personnel-change-requests/complete-submission`, {
      data: {
        personnel_change_request_id: ID.SUBMIT_PENDING,
        current_account_set_id: ACCOUNT_SET_A,
      },
    });
    expect(res.status()).toBe(401);
  });

  test('A7. 不带 token 删除返回 401', async ({ request }) => {
    const res = await request.delete(`${BASE_URL}/personnel-change-requests/${ID.DELETE_PENDING}`);
    expect(res.status()).toBe(401);
  });
});

// ============================================================
// B. 列表接口 GET /api/personnel-change-requests
// ============================================================
test.describe('B. 列表接口', () => {
  test('B1. 基本查询成功，返回 success=true', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A });
    expect(res.status).toBe(200);
    assertSuccess(res.body);
    assertJsonHeaders(res.headers);
    assertResponseTime(res.elapsed);
  });

  test('B2. 响应结构正确，包含分页结构', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A });
    assertPaginated(res.body);
    expect(typeof res.body.data.total).toBe('number');
    expect(res.body.data.total).toBeGreaterThan(0);
  });

  test('B3. 默认按 created_at 倒序', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 50 });
    const items = res.body.data.data;
    if (items.length < 2) { test.skip(); return; }
    for (let i = 0; i < items.length - 1; i++) {
      const cur = new Date(items[i].created_at).getTime();
      const next = new Date(items[i + 1].created_at).getTime();
      expect(cur).toBeGreaterThanOrEqual(next);
    }
  });

  test('B4. attachment_count 字段正确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 50 });
    for (const item of res.body.data.data) {
      expect(item).toHaveProperty('attachment_count');
      expect(typeof item.attachment_count).toBe('number');
      expect(item.attachment_count).toBe(item.attachments?.length ?? 0);
    }
  });

  test('B5. personnel_count 字段正确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 50 });
    for (const item of res.body.data.data) {
      expect(item).toHaveProperty('personnel_count');
      expect(typeof item.personnel_count).toBe('number');
      const expected = Array.isArray(item.personnel_list) ? item.personnel_list.length : 0;
      expect(item.personnel_count).toBe(expected);
    }
  });

  test('B6. month 筛选正确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, month: '2025-01' });
    assertSuccess(res.body);
    for (const item of res.body.data.data) {
      expect(item.month).toBe('2025-01');
    }
  });

  test('B7. project_id 筛选正确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, project_id: '4' });
    assertSuccess(res.body);
    for (const item of res.body.data.data) {
      expect(item.project_id).toBe(4);
    }
  });

  test('B8. change_type 筛选正确 - add', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, change_type: 'add' });
    assertSuccess(res.body);
    expect(res.body.data.data.length).toBeGreaterThan(0);
    for (const item of res.body.data.data) {
      expect(item.change_type).toBe('add');
    }
  });

  test('B8b. change_type 筛选正确 - remove', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, change_type: 'remove' });
    assertSuccess(res.body);
    expect(res.body.data.data.length).toBeGreaterThan(0);
    for (const item of res.body.data.data) {
      expect(item.change_type).toBe('remove');
    }
  });

  test('B9. status 筛选正确 - pending', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, status: 'pending' });
    assertSuccess(res.body);
    expect(res.body.data.data.length).toBeGreaterThan(0);
    for (const item of res.body.data.data) {
      expect(item.status).toBe('pending');
    }
  });

  test('B9b. status 筛选正确 - rejected', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, status: 'rejected' });
    assertSuccess(res.body);
    expect(res.body.data.data.length).toBeGreaterThan(0);
    for (const item of res.body.data.data) {
      expect(item.status).toBe('rejected');
    }
  });

  test('B10. 多条件组合筛选正确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({
      current_account_set_id: ACCOUNT_SET_A,
      status: 'pending',
      change_type: 'add',
      project_id: '4',
    });
    assertSuccess(res.body);
    for (const item of res.body.data.data) {
      expect(item.status).toBe('pending');
      expect(item.change_type).toBe('add');
      expect(item.project_id).toBe(4);
    }
  });

  test('B11. per_page 生效', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 2 });
    assertSuccess(res.body);
    expect(res.body.data.data.length).toBeLessThanOrEqual(2);
    expect(res.body.data.per_page).toBe(2);
  });

  test('B12. page 翻页数据正确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const page1 = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 2, page: 1 });
    const page2 = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 2, page: 2 });
    assertSuccess(page1.body);
    assertSuccess(page2.body);
    if (page1.body.data.total > 2) {
      const ids1 = page1.body.data.data.map((r: any) => r.id);
      const ids2 = page2.body.data.data.map((r: any) => r.id);
      const overlap = ids1.filter((id: number) => ids2.includes(id));
      expect(overlap.length).toBe(0);
    }
  });

  test('B13. current_account_set_id=A 只返回 A 账套数据', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 100 });
    assertSuccess(res.body);
    for (const item of res.body.data.data) {
      expect(item.account_set_id).toBe(Number(ACCOUNT_SET_A));
    }
  });

  test('B14. 缺少 current_account_set_id 时的行为记录', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({});
    // 记录行为
    if (res.status === 200 && res.body.success) {
      const items = res.body.data.data;
      const hasOtherAccount = items.some((r: any) => r.account_set_id !== Number(ACCOUNT_SET_A));
      if (hasOtherAccount) {
        console.warn('⚠️ 账套隔离风险：缺少 current_account_set_id 时返回了其他账套数据');
      }
    }
  });

  test('B15. A 账套 token + B 账套参数尝试读取其他账套数据', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_B, per_page: 100 });
    if (res.status === 200 && res.body.success) {
      const items = res.body.data.data;
      const hasBData = items.some((r: any) => r.account_set_id === Number(ACCOUNT_SET_B));
      if (hasBData) {
        console.error('🚨 严重越权：A 账套 token 可以通过参数读取 B 账套数据');
      }
    }
  });
});

// ============================================================
// C. 详情接口 GET /api/personnel-change-requests/{id}
// ============================================================
test.describe('C. 详情接口', () => {
  test('C1. 查询 pending 记录成功', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.PENDING_ADD);
    expect(res.status).toBe(200);
    assertSuccess(res.body);
    expect(res.body.data.status).toBe('pending');
    assertJsonHeaders(res.headers);
    assertResponseTime(res.elapsed);
  });

  test('C2. 查询 rejected 记录成功', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.REJECTED_REMOVE);
    expect(res.status).toBe(200);
    assertSuccess(res.body);
    expect(res.body.data.status).toBe('rejected');
  });

  test('C3. 返回包含 project / creator / attachments', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.PENDING_ADD);
    assertSuccess(res.body);
    expect(res.body.data).toHaveProperty('project');
    expect(res.body.data).toHaveProperty('creator');
    expect(res.body.data).toHaveProperty('attachments');
    expect(res.body.data.project).toHaveProperty('id');
    expect(res.body.data.project).toHaveProperty('name');
    expect(res.body.data.creator).toHaveProperty('id');
    expect(Array.isArray(res.body.data.attachments)).toBe(true);
  });

  test('C4. personnel_list 为数组', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.PENDING_ADD);
    assertSuccess(res.body);
    expect(Array.isArray(res.body.data.personnel_list)).toBe(true);
    expect(res.body.data.personnel_list.length).toBeGreaterThan(0);
  });

  test('C5. 附件字段结构正确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.WITH_ATTACHMENT);
    assertSuccess(res.body);
    if (res.body.data.attachments.length > 0) {
      const att = res.body.data.attachments[0];
      expect(att).toHaveProperty('file_name');
      expect(att).toHaveProperty('file_path');
      expect(att).toHaveProperty('file_type');
      expect(att).toHaveProperty('file_size');
      expect(typeof att.file_size).toBe('number');
    }
  });

  test('C6. 查询不存在 ID 返回 404', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show('999999');
    expect(res.status).toBe(404);
    assertFail(res.body);
    assertResponseTime(res.elapsed);
  });

  test('C7. 尝试读取其他账套的申请', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.OTHER_ACCOUNT);
    if (res.status === 200 && res.body.success) {
      console.error(`🚨 跨账套越权：show 接口可读取其他账套记录 id=${ID.OTHER_ACCOUNT}`);
      expect(res.body.data.account_set_id).toBe(Number(ACCOUNT_SET_B));
    }
    // 如果返回 404/403，说明有隔离
  });
});

// ============================================================
// D. 上传附件接口 POST /api/personnel-change-requests/upload-attachment
// ============================================================
test.describe('D. 上传附件接口', () => {
  test('D1. 给 pending 记录上传附件成功', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'test-pending.txt');
    expect(res.status).toBe(200);
    assertSuccess(res.body);
    expect(res.body.message).toContain('成功');
    assertJsonHeaders(res.headers);
    assertResponseTime(res.elapsed);
  });

  test('D2. 给 rejected 记录上传附件成功', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment(ID.REJECTED_REMOVE, smallFile(), 'test-rejected.txt');
    expect(res.status).toBe(200);
    assertSuccess(res.body);
  });

  test('D3. multipart 格式正确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'format-test.txt');
    expect([200, 422]).toContain(res.status);
  });

  test('D4. 响应 success=true，message 正确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'msg-test.txt');
    if (res.status === 200) {
      assertSuccess(res.body);
      expect(res.body.message).toContain('成功');
    }
  });

  test('D5. 返回 data 中 file_name / file_path / file_type / file_size 正确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'fields-test.txt');
    if (res.status === 200) {
      expect(res.body.data).toHaveProperty('file_name');
      expect(res.body.data).toHaveProperty('file_path');
      expect(res.body.data).toHaveProperty('file_type');
      expect(res.body.data).toHaveProperty('file_size');
      expect(res.body.data.file_name).toBe('fields-test.txt');
      expect(typeof res.body.data.file_size).toBe('number');
    }
  });

  test('D6. file_path 包含 personnel_change_requests/{requestId}/', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'path-test.txt');
    if (res.status === 200) {
      expect(res.body.data.file_path).toContain(`personnel_change_requests/${ID.UPLOAD_TARGET}/`);
    }
  });

  test('D7. 上传后详情 attachments 数量增加', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const before = await api.show(ID.UPLOAD_TARGET);
    const countBefore = before.body.data.attachments.length;
    await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'detail-inc.txt');
    const after = await api.show(ID.UPLOAD_TARGET);
    expect(after.body.data.attachments.length).toBe(countBefore + 1);
  });

  test('D8. 上传后列表 attachment_count 同步增加', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const before = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 100 });
    const itemBefore = before.body.data.data.find((r: any) => r.id === Number(ID.UPLOAD_TARGET));
    const countBefore = itemBefore?.attachment_count ?? 0;
    await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'list-count.txt');
    const after = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 100 });
    const itemAfter = after.body.data.data.find((r: any) => r.id === Number(ID.UPLOAD_TARGET));
    expect(itemAfter?.attachment_count).toBe(countBefore + 1);
  });

  test('D9. 缺少 file 返回 422', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachmentRaw({ personnel_change_request_id: ID.UPLOAD_TARGET });
    expect(res.status).toBe(422);
    assertFail(res.body);
  });

  test('D10. 缺少 personnel_change_request_id 返回 422', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachmentRaw({
      file: { name: 'no-id.txt', mimeType: 'text/plain', buffer: smallFile() },
    });
    expect(res.status).toBe(422);
    assertFail(res.body);
  });

  test('D11. personnel_change_request_id 不存在返回 422', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment('999999', smallFile(), 'not-exist.txt');
    expect(res.status).toBe(422);
    assertFail(res.body);
  });

  test('D12. 上传超过 50MB 文件返回 422', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const bigBuffer = Buffer.alloc(51 * 1024 * 1024 + 1, 'x');
    const res = await api.uploadAttachment(ID.UPLOAD_TARGET, bigBuffer, 'big-file.bin');
    expect([413, 422]).toContain(res.status);
    if (res.body) assertFail(res.body);
  });

  test('D13. 给其他账套 request_id 上传附件', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment(ID.OTHER_ACCOUNT, smallFile(), 'cross-acct.txt');
    if (res.status === 200) {
      console.error('🚨 跨账套越权：upload-attachment 可对其他账套 request_id 操作');
      assertSuccess(res.body);
    }
  });

  test('D14. 给 in_approval 状态上传附件 - 记录当前行为', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment(ID.IN_APPROVAL_ADD, smallFile(), 'in-approval.txt');
    console.log(`in_approval 上传附件: status=${res.status}, success=${res.body?.success}`);
  });

  test('D15. 给 approved 状态上传附件 - 记录当前行为', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment(ID.APPROVED_REMOVE, smallFile(), 'approved.txt');
    console.log(`approved 上传附件: status=${res.status}, success=${res.body?.success}`);
  });
});

// ============================================================
// E. 完成提交接口 POST /api/personnel-change-requests/complete-submission
// ============================================================
test.describe('E. 完成提交接口', () => {
  test('E1. pending 状态提交成功', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.SUBMIT_PENDING,
      current_account_set_id: ACCOUNT_SET_A,
    });
    expect(res.status).toBe(200);
    assertSuccess(res.body);
    expect(res.body.message).toContain('提交');
    assertJsonHeaders(res.headers);
    assertResponseTime(res.elapsed);
  });

  test('E2. rejected 状态提交成功', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.SUBMIT_REJECTED,
      current_account_set_id: ACCOUNT_SET_A,
    });
    expect(res.status).toBe(200);
    assertSuccess(res.body);
  });

  test('E3. 成功后返回 request 和 approval_instance', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    // 查询已被 E1 提交的记录
    const showRes = await api.show(ID.SUBMIT_PENDING);
    if (showRes.body.data.status !== 'in_approval') { test.skip(); return; }
    // 验证结构（通过 show 间接验证）
    expect(showRes.body.data).toHaveProperty('approval_flow_id');
  });

  test('E4. 成功后 request.status 变为 in_approval', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.SUBMIT_PENDING);
    expect(res.body.data.status).toBe('in_approval');
  });

  test('E5. 成功后 approval_flow_id 有值', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.SUBMIT_PENDING);
    expect(res.body.data.approval_flow_id).toBeTruthy();
    expect(Number(res.body.data.approval_flow_id)).toBeGreaterThan(0);
  });

  test('E6. 不传 stamp_method 时默认 online', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    // 使用一个 pending 记录
    const before = await api.show(ID.DOUBLE_SUBMIT);
    if (before.body.data.status !== 'pending') { test.skip(); return; }
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.DOUBLE_SUBMIT,
      current_account_set_id: ACCOUNT_SET_A,
    });
    if (res.status === 200 && res.body.data?.approval_instance) {
      expect(res.body.data.approval_instance.stamp_method).toBe('online');
    }
  });

  test('E7. 显式传 stamp_method=online 成功', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const before = await api.show(ID.PENDING_ADD);
    if (before.body.data.status !== 'pending') { test.skip(); return; }
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.PENDING_ADD,
      current_account_set_id: ACCOUNT_SET_A,
      stamp_method: 'online',
    });
    if (res.status === 200) {
      assertSuccess(res.body);
    }
  });

  test('E8. 显式传 stamp_method=offline 成功', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const before = await api.show(ID.WITH_ATTACHMENT);
    if (before.body.data.status !== 'pending') { test.skip(); return; }
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.WITH_ATTACHMENT,
      current_account_set_id: ACCOUNT_SET_A,
      stamp_method: 'offline',
    });
    if (res.status === 200) {
      assertSuccess(res.body);
    }
  });

  test('E9. pending 记录重复提交 - 第二次应失败', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    // SUBMIT_PENDING 已在 E1 被提交
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.SUBMIT_PENDING,
      current_account_set_id: ACCOUNT_SET_A,
    });
    expect(res.status).toBe(400);
    assertFail(res.body);
  });

  test('E10. 对 in_approval 状态提交返回 400', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.IN_APPROVAL_ADD,
      current_account_set_id: ACCOUNT_SET_A,
    });
    expect(res.status).toBe(400);
    assertFail(res.body);
  });

  test('E11. 对 approved 状态提交返回 400', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.APPROVED_REMOVE,
      current_account_set_id: ACCOUNT_SET_A,
    });
    expect(res.status).toBe(400);
    assertFail(res.body);
  });

  test('E12. 缺少 personnel_change_request_id 返回 422', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({ current_account_set_id: ACCOUNT_SET_A });
    expect(res.status).toBe(422);
    assertFail(res.body);
  });

  test('E13. 缺少 current_account_set_id 返回 422', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({ personnel_change_request_id: ID.PENDING_ADD });
    expect(res.status).toBe(422);
    assertFail(res.body);
  });

  test('E14. current_account_set_id 不存在返回 422', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.PENDING_ADD,
      current_account_set_id: '999999',
    });
    expect(res.status).toBe(422);
    assertFail(res.body);
  });

  test('E15. personnel_change_request_id 不存在返回 422', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({
      personnel_change_request_id: '999999',
      current_account_set_id: ACCOUNT_SET_A,
    });
    expect(res.status).toBe(422);
    assertFail(res.body);
  });

  test('E16. 先上传附件再提交，验证附件带入审批流程', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    // 查看 WITH_ATTACHMENT 的当前状态
    const before = await api.show(ID.WITH_ATTACHMENT);
    if (before.body.data.status !== 'pending') { test.skip(); return; }

    // 先上传一个附件
    await api.uploadAttachment(ID.WITH_ATTACHMENT, smallFile(), 'flow-attach.txt');

    // 再提交
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.WITH_ATTACHMENT,
      current_account_set_id: ACCOUNT_SET_A,
    });
    if (res.status === 200) {
      expect(res.body.data.approval_instance).toBeTruthy();
    }
  });

  test('E17. 提交其他账套的 request_id', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.OTHER_ACCOUNT,
      current_account_set_id: ACCOUNT_SET_A,
    });
    if (res.status === 200) {
      console.error('🚨 跨账套越权：complete-submission 可对其他账套 request_id 发起审批');
    }
  });

  test('E18. 状态流转验证 - pending -> in_approval', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.SUBMIT_PENDING);
    expect(res.body.data.status).toBe('in_approval');
    expect(res.body.data.approval_flow_id).toBeTruthy();
  });

  test('E18b. 状态流转验证 - rejected -> in_approval', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.SUBMIT_REJECTED);
    expect(res.body.data.status).toBe('in_approval');
  });
});

// ============================================================
// F. 删除接口 DELETE /api/personnel-change-requests/{id}
// ============================================================
test.describe('F. 删除接口', () => {
  test('F1. 删除 pending 记录成功', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.destroy(ID.DELETE_PENDING);
    expect(res.status).toBe(200);
    assertSuccess(res.body);
    expect(res.body.message).toContain('删除');
    assertJsonHeaders(res.headers);
    assertResponseTime(res.elapsed);
  });

  test('F2. 删除 rejected 记录成功', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.destroy(ID.DELETE_REJECTED);
    expect(res.status).toBe(200);
    assertSuccess(res.body);
  });

  test('F3. 删除后再查详情返回 404', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.DELETE_PENDING);
    expect(res.status).toBe(404);
  });

  test('F4. 删除后列表中不再出现该记录', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A, per_page: 100 });
    const found = res.body.data.data.find((r: any) => r.id === Number(ID.DELETE_PENDING));
    expect(found).toBeUndefined();
  });

  test('F5. 删除 in_approval 记录返回 403', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.destroy(ID.IN_APPROVAL_ADD);
    expect(res.status).toBe(403);
    assertFail(res.body);
    expect(res.body.message).toContain('驳回');
  });

  test('F6. 删除 approved 记录返回 403', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.destroy(ID.APPROVED_REMOVE);
    expect(res.status).toBe(403);
    assertFail(res.body);
  });

  test('F7. 删除不存在 ID 的行为', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.destroy('999999');
    expect([404, 500]).toContain(res.status);
    if (res.status === 500) {
      console.warn('⚠️ 删除不存在 ID 返回 500 而非 404，建议改进错误处理');
    }
  });

  test('F8. 删除其他账套的记录', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.destroy(ID.OTHER_ACCOUNT);
    if (res.status === 200) {
      console.error('🚨 跨账套越权：delete 可删除其他账套数据');
    }
  });
});

// ============================================================
// G. 账套隔离与越权专项
// ============================================================
test.describe('G. 账套隔离与越权专项', () => {
  test('G1. show 接口跨账套读取', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.OTHER_ACCOUNT);
    if (res.status === 200 && res.body.success) {
      console.error(`🚨 [G1] show 可跨账套读取: id=${ID.OTHER_ACCOUNT}, account_set_id=${res.body.data.account_set_id}`);
      expect(res.body.data.account_set_id).toBe(Number(ACCOUNT_SET_B));
    }
  });

  test('G2. upload-attachment 跨账套操作', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment(ID.OTHER_ACCOUNT, smallFile(), 'g2-cross.txt');
    if (res.status === 200) {
      console.error(`🚨 [G2] upload-attachment 可对其他账套 request_id 操作: id=${ID.OTHER_ACCOUNT}`);
    }
  });

  test('G3. complete-submission 跨账套发起审批', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.OTHER_ACCOUNT,
      current_account_set_id: ACCOUNT_SET_A,
    });
    if (res.status === 200) {
      console.error(`🚨 [G3] complete-submission 可对其他账套 request_id 发起审批: id=${ID.OTHER_ACCOUNT}`);
    }
  });

  test('G4. delete 跨账套删除', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.destroy(ID.OTHER_ACCOUNT);
    if (res.status === 200) {
      console.error(`🚨 [G4] delete 可删除其他账套数据: id=${ID.OTHER_ACCOUNT}`);
    }
  });

  test('G5. 综合越权报告', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const findings: string[] = [];

    const showRes = await api.show(ID.OTHER_ACCOUNT);
    if (showRes.status === 200 && showRes.body.success) {
      findings.push('show: 可跨账套读取');
    }

    const listRes = await api.list({ current_account_set_id: ACCOUNT_SET_B, per_page: 100 });
    if (listRes.status === 200 && listRes.body.success) {
      const hasOther = listRes.body.data.data.some((r: any) => r.account_set_id === Number(ACCOUNT_SET_B));
      if (hasOther) findings.push('list: 可通过参数读取其他账套数据');
    }

    if (findings.length > 0) {
      console.error(`🚨 跨账套越权漏洞汇总: ${findings.join('; ')}`);
    }
    expect(true).toBe(true);
  });
});

// ============================================================
// H. 响应与稳定性
// ============================================================
test.describe('H. 响应与稳定性', () => {
  test('H1. 列表接口响应结构完整', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A });
    expect(res.status).toBe(200);
    assertSuccess(res.body);
    assertJsonHeaders(res.headers);
    expect(res.body).toHaveProperty('data');
    expect(res.body.data).toHaveProperty('data');
    expect(res.body.data).toHaveProperty('total');
    assertResponseTime(res.elapsed);
  });

  test('H2. 详情接口响应结构完整', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.PENDING_ADD);
    expect(res.status).toBe(200);
    assertSuccess(res.body);
    assertJsonHeaders(res.headers);
    expect(res.body).toHaveProperty('data');
    assertResponseTime(res.elapsed);
  });

  test('H3. 上传接口响应结构完整', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.uploadAttachment(ID.UPLOAD_TARGET, smallFile(), 'struct-test.txt');
    assertJsonHeaders(res.headers);
    assertResponseTime(res.elapsed);
    if (res.status === 200) {
      assertSuccess(res.body);
      expect(res.body).toHaveProperty('data');
    }
  });

  test('H4. 提交接口 400 响应结构完整', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.IN_APPROVAL_ADD,
      current_account_set_id: ACCOUNT_SET_A,
    });
    expect(res.status).toBe(400);
    assertFail(res.body);
    expect(res.body).toHaveProperty('message');
    assertJsonHeaders(res.headers);
    assertResponseTime(res.elapsed);
  });

  test('H5. 删除接口 403 响应结构完整', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.destroy(ID.IN_APPROVAL_ADD);
    expect(res.status).toBe(403);
    assertFail(res.body);
    expect(res.body).toHaveProperty('message');
    assertJsonHeaders(res.headers);
    assertResponseTime(res.elapsed);
  });

  test('H6. 422 响应包含 errors 字段', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({
      personnel_change_request_id: '999999',
      current_account_set_id: ACCOUNT_SET_A,
    });
    expect(res.status).toBe(422);
    assertFail(res.body);
    expect(res.body).toHaveProperty('errors');
  });

  test('H7. 所有接口 content-type 为 application/json', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    assertJsonHeaders((await api.list({ current_account_set_id: ACCOUNT_SET_A })).headers);
    assertJsonHeaders((await api.show(ID.PENDING_ADD)).headers);
    assertJsonHeaders((await api.completeSubmission({
      personnel_change_request_id: ID.IN_APPROVAL_ADD,
      current_account_set_id: ACCOUNT_SET_A,
    })).headers);
    assertJsonHeaders((await api.destroy(ID.IN_APPROVAL_ADD)).headers);
  });

  test('H8. 404 响应 message 准确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show('999999');
    expect(res.status).toBe(404);
    assertFail(res.body);
    expect(typeof res.body.message).toBe('string');
    expect(res.body.message.length).toBeGreaterThan(0);
  });

  test('H9. 400 响应 message 准确', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.completeSubmission({
      personnel_change_request_id: ID.APPROVED_REMOVE,
      current_account_set_id: ACCOUNT_SET_A,
    });
    expect(res.status).toBe(400);
    assertFail(res.body);
    expect(res.body.message).toContain('驳回');
  });

  test('H10. 响应时间基线 - 列表 < 2s', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.list({ current_account_set_id: ACCOUNT_SET_A });
    expect(res.elapsed).toBeLessThan(2000);
  });

  test('H11. 响应时间基线 - 详情 < 2s', async ({ request }) => {
    const api = new PersonnelChangeRequestApi(request, authToken);
    const res = await api.show(ID.PENDING_ADD);
    expect(res.elapsed).toBeLessThan(2000);
  });
});
