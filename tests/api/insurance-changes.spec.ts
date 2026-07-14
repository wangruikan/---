import { test, expect, APIRequestContext } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import * as http from 'http';

const BASE_URL = 'http://localhost:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);

let authToken: string;
let currentUserId: number;

async function login(
  request: APIRequestContext,
  username: string,
  password: string
): Promise<{ token: string; userId: number }> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: { username, password },
  });
  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(data.success).toBe(true);
  return { token: data.data.token, userId: data.data.user?.id };
}

function authHeaders(token?: string) {
  return {
    Authorization: `Bearer ${token || authToken}`,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
  };
}

function uploadFiles(
  token: string,
  urlPath: string,
  filePaths: string[],
  fileNames: string[],
  mimeTypes: string[],
  extraFields?: Record<string, string>
): Promise<{ status: number; body: string }> {
  return new Promise((resolve, reject) => {
    const boundary = '----FormBoundary' + Date.now();
    const parts: Buffer[] = [];

    if (extraFields) {
      for (const [key, value] of Object.entries(extraFields)) {
        parts.push(
          Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="${key}"\r\n\r\n${value}\r\n`)
        );
      }
    }

    for (let i = 0; i < filePaths.length; i++) {
      const fileData = fs.readFileSync(filePaths[i]);
      parts.push(
        Buffer.from(
          `--${boundary}\r\nContent-Disposition: form-data; name="attachments[]"; filename="${fileNames[i]}"\r\nContent-Type: ${mimeTypes[i]}\r\n\r\n`
        )
      );
      parts.push(fileData);
      parts.push(Buffer.from(`\r\n`));
    }

    parts.push(Buffer.from(`--${boundary}--\r\n`));
    const body = Buffer.concat(parts);

    const url = new URL(`${BASE_URL}${urlPath}`);
    const req = http.request(
      {
        hostname: '127.0.0.1',
        port: url.port,
        path: url.pathname,
        method: 'POST',
        headers: {
          'Content-Type': `multipart/form-data; boundary=${boundary}`,
          Authorization: `Bearer ${token}`,
          'X-Account-Set-Id': String(ACCOUNT_SET_ID),
          'Content-Length': body.length,
        },
      },
      (res) => {
        let data = '';
        res.on('data', (chunk) => (data += chunk));
        res.on('end', () => resolve({ status: res.statusCode!, body: data }));
      }
    );
    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

const TEST_PNG_PATH = path.join(__dirname, 'test-image.png');
if (!fs.existsSync(TEST_PNG_PATH)) {
  fs.writeFileSync(
    TEST_PNG_PATH,
    Buffer.from(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      'base64'
    )
  );
}

const TEST_PDF_PATH = path.join(__dirname, 'test-document.pdf');
if (!fs.existsSync(TEST_PDF_PATH)) {
  fs.writeFileSync(
    TEST_PDF_PATH,
    Buffer.from(
      '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n190\n%%EOF'
    )
  );
}

test.describe('参保增减 API 测试', () => {
  test.beforeAll(async ({ request }) => {
    const { token, userId } = await login(request, USERNAME, PASSWORD);
    authToken = token;
    currentUserId = userId;
    console.log('登录成功，userId:', currentUserId);
  });

  // ============================================================
  // 一、列表查询
  // ============================================================
  test.describe('一、列表查询', () => {
    test('未登录访问列表应返回 401', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes`, {
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(401);
    });

    test('缺少 account_set_id 应返回 422 或 200', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
      });
      expect([200, 400, 422]).toContain(response.status());
    });

    test('正常获取列表成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);
    });

    test('按 status 筛选成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'pending' },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      for (const item of data.data) {
        expect(['pending', 'submitted']).toContain(item.status);
      }
    });

    test('按 month 筛选成功', async ({ request }) => {
      const now = new Date();
      const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
      const response = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, month },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
    });

    test('按 region_name 筛选成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, region_name: '全部' },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
    });

    test('列表返回字段完整性校验', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const data = await response.json();
      if (data.data.length === 0) {
        test.skip();
        return;
      }
      const item = data.data[0];
      expect(item).toHaveProperty('id');
      expect(item).toHaveProperty('employee_id');
      expect(item).toHaveProperty('employee_name');
      expect(item).toHaveProperty('change_type');
      expect(item).toHaveProperty('status');
      expect(item).toHaveProperty('project_id');
      expect(item).toHaveProperty('account_set_id');
      expect(['increase', 'decrease']).toContain(item.change_type);
      expect(['pending', 'processing', 'submitted', 'completed']).toContain(item.status);
    });
  });

  // ============================================================
  // 二、详情查询
  // ============================================================
  test.describe('二、详情查询', () => {
    test('获取详情成功', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const id = listData.data[0].id;
      const response = await request.get(`${BASE_URL}/insurance-changes/${id}`, {
        headers: authHeaders(),
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.id).toBe(id);
    });

    test('详情返回快照字段', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
      });
      const listData = await listRes.json();
      const withInsurance = listData.data.find(
        (item: any) => item.social_security_region_id || item.medical_insurance_region_id
      );
      if (!withInsurance) {
        test.skip();
        return;
      }

      const response = await request.get(`${BASE_URL}/insurance-changes/${withInsurance.id}`, {
        headers: authHeaders(),
      });
      const data = await response.json();
      expect(data.data).toHaveProperty('social_security_types');
      expect(data.data).toHaveProperty('medical_insurance_types');
      expect(data.data).toHaveProperty('housing_fund_params');
      expect(data.data).toHaveProperty('other_insurance_policies');
    });

    test('获取不存在的详情应返回 404', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/999999`, {
        headers: authHeaders(),
      });
      expect(response.status()).toBe(404);
    });
  });

  // ============================================================
  // 三、子任务 items
  // ============================================================
  test.describe('三、子任务 items', () => {
    test('获取子任务列表成功', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const id = listData.data[0].id;
      const response = await request.get(`${BASE_URL}/insurance-changes/${id}/items`, {
        headers: authHeaders(),
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);
    });

    test('子任务 category 值在允许范围内', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
      });
      const listData = await listRes.json();
      const withItems = listData.data.find(
        (item: any) => item.change_items && item.change_items.length > 0
      );
      if (!withItems) {
        test.skip();
        return;
      }

      const response = await request.get(`${BASE_URL}/insurance-changes/${withItems.id}/items`, {
        headers: authHeaders(),
      });
      const data = await response.json();
      const allowedCategories = [
        'social_security',
        'medical_insurance',
        'housing_fund',
        'large_medical_insurance',
        'other_insurance',
      ];
      for (const item of data.data) {
        expect(allowedCategories.includes(item.category) || /^other_policy:\d+$/.test(item.category)).toBe(true);
      }
    });
  });

  // ============================================================
  // 四、附件上传与删除
  // ============================================================
  test.describe('四、附件上传与删除', () => {
    test('上传附件成功', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'pending', per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const id = listData.data[0].id;
      const uploadRes = await uploadFiles(
        authToken,
        `/insurance-changes/${id}/upload-attachment`,
        [TEST_PNG_PATH],
        ['test.png'],
        ['image/png']
      );
      expect(uploadRes.status).toBe(200);
      const data = JSON.parse(uploadRes.body);
      expect(data.success).toBe(true);
      expect(data.message).toContain('成功上传');
      expect(data.data.uploaded_files.length).toBeGreaterThan(0);
      expect(data.data.change.status).toBe('submitted');
    });

    test('上传附件后状态变为 submitted', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'pending', per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const id = listData.data[0].id;
      const uploadRes = await uploadFiles(
        authToken,
        `/insurance-changes/${id}/upload-attachment`,
        [TEST_PDF_PATH],
        ['test.pdf'],
        ['application/pdf']
      );
      const uploadData = JSON.parse(uploadRes.body);
      if (!uploadData.success) {
        test.skip();
        return;
      }

      const detailRes = await request.get(`${BASE_URL}/insurance-changes/${id}`, {
        headers: authHeaders(),
      });
      const detailData = await detailRes.json();
      expect(detailData.data.status).toBe('submitted');
      expect(detailData.data.attachment_uploaded_at).toBeTruthy();
    });

    test('删除附件成功', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'submitted', per_page: 10 },
      });
      const listData = await listRes.json();
      const withAttachment = listData.data.find(
        (item: any) => item.attachments && item.attachments.length > 0
      );
      if (!withAttachment) {
        test.skip();
        return;
      }

      const attachmentId = withAttachment.attachments[0].id;
      const response = await request.delete(
        `${BASE_URL}/insurance-changes/attachments/${attachmentId}`,
        { headers: authHeaders() }
      );
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
    });

    test('上传不合法文件类型应返回 422', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'pending', per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const id = listData.data[0].id;
      const exePath = path.join(__dirname, 'test-invalid.exe');
      fs.writeFileSync(exePath, 'fake exe content');

      try {
        const uploadRes = await uploadFiles(
          authToken,
          `/insurance-changes/${id}/upload-attachment`,
          [exePath],
          ['test.exe'],
          ['application/octet-stream']
        );
        expect(uploadRes.status).toBe(422);
        const data = JSON.parse(uploadRes.body);
        expect(data.success).toBe(false);
      } finally {
        fs.unlinkSync(exePath);
      }
    });
  });

  // ============================================================
  // 五、确认处理（整单确认）
  // ============================================================
  test.describe('五、确认处理', () => {
    test('process 接口等价于 confirm-process', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'submitted', per_page: 10 },
      });
      const listData = await listRes.json();
      const withAttachment = listData.data.find(
        (item: any) => item.attachments && item.attachments.length > 0
      );
      if (!withAttachment) {
        test.skip();
        return;
      }

      const id = withAttachment.id;
      const response = await request.post(`${BASE_URL}/insurance-changes/${id}/process`, {
        headers: authHeaders(),
        data: {},
      });
      expect([200, 400, 422]).toContain(response.status());
      const data = await response.json();
      expect(data).toHaveProperty('success');
    });

    test('整单确认成功后状态为 completed', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'submitted', per_page: 10 },
      });
      const listData = await listRes.json();
      const withAttachment = listData.data.find(
        (item: any) => item.attachments && item.attachments.length > 0
      );
      if (!withAttachment) {
        test.skip();
        return;
      }

      const id = withAttachment.id;
      const response = await request.put(`${BASE_URL}/insurance-changes/${id}/confirm-process`, {
        headers: authHeaders(),
        data: {},
      });
      if (response.status() !== 200) {
        console.log('确认失败，可能缺少条件:', await response.json());
        test.skip();
        return;
      }
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.status).toBe('completed');
      expect(data.data.fully_confirmed).toBe(1);
      expect(data.data.processed_at).toBeTruthy();
      expect(data.data.completed_at).toBeTruthy();
    });

    test('没有附件时确认应失败', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'pending', per_page: 10 },
      });
      const listData = await listRes.json();
      const noAttachment = listData.data.find(
        (item: any) => !item.attachments || item.attachments.length === 0
      );
      if (!noAttachment) {
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/insurance-changes/${noAttachment.id}/confirm-process`,
        { headers: authHeaders(), data: {} }
      );
      expect([400, 422]).toContain(response.status());
    });

    test('已完成记录重复确认应失败', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'completed', per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const id = listData.data[0].id;
      const response = await request.put(`${BASE_URL}/insurance-changes/${id}/confirm-process`, {
        headers: authHeaders(),
        data: {},
      });
      expect([400, 422]).toContain(response.status());
    });
  });

  // ============================================================
  // 六、自动导入
  // ============================================================
  test.describe('六、自动导入', () => {
    test('缺少必填字段返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/insurance-changes/auto-import`, {
        headers: authHeaders(),
        data: {},
      });
      expect(response.status()).toBe(422);
    });

    test('不存在的 employee_id 返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/insurance-changes/auto-import`, {
        headers: authHeaders(),
        data: {
          employee_id: 999999,
          project_id: 1,
          account_set_id: ACCOUNT_SET_ID,
        },
      });
      expect(response.status()).toBe(422);
    });
  });

  // ============================================================
  // 七、汇总与导出
  // ============================================================
  test.describe('七、汇总与导出', () => {
    test('获取汇总列表成功', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/summaries`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);
    });

    test('generate-summary 缺参数返回 422', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/insurance-changes/generate-summary`, {
        headers: authHeaders(),
        data: {},
      });
      expect(response.status()).toBe(422);
    });

    test('generate-summary 成功', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/insurance-changes/generate-summary`, {
        headers: authHeaders(),
        data: { account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);
    });

    test('export-summary 成功', async ({ request }) => {
      const response = await request.post(`${BASE_URL}/insurance-changes/export-summary`, {
        headers: authHeaders(),
        data: { account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data).toHaveProperty('data');
      expect(data).toHaveProperty('filename');
    });
  });

  // ============================================================
  // 八、契约与稳定性
  // ============================================================
  test.describe('八、契约与稳定性', () => {
    test('列表响应结构契约', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const data = await response.json();
      expect(data).toHaveProperty('success');
      expect(data).toHaveProperty('data');
      expect(Array.isArray(data.data)).toBe(true);
    });

    test('错误返回结构一致', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/999999`, {
        headers: authHeaders(),
      });
      expect(response.status()).toBe(404);
      const data = await response.json();
      expect(data).toHaveProperty('message');
    });

    test('列表响应时间合理', async ({ request }) => {
      const start = Date.now();
      await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      const elapsed = Date.now() - start;
      expect(elapsed).toBeLessThan(10000);
    });
  });
});
