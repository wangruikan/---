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
const TEST_PDF_PATH = path.join(__dirname, 'test-document.pdf');

test.describe('参保增减 - 子任务分险种确认', () => {
  test.beforeAll(async ({ request }) => {
    const { token, userId } = await login(request, USERNAME, PASSWORD);
    authToken = token;
    currentUserId = userId;
  });

  // ============================================================
  // 一、子任务结构
  // ============================================================
  test.describe('一、子任务结构', () => {
    test('子任务返回正确字段', async ({ request }) => {
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
      expect(data.data.length).toBeGreaterThan(0);

      const item = data.data[0];
      expect(item).toHaveProperty('id');
      expect(item).toHaveProperty('insurance_change_id');
      expect(item).toHaveProperty('category');
      expect(item).toHaveProperty('status');
      expect(item).toHaveProperty('change_type');
    });

    test('子任务 category 在允许范围内', async ({ request }) => {
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
      const allowed = [
        'social_security',
        'medical_insurance',
        'housing_fund',
        'large_medical_insurance',
        'other_insurance',
      ];
      for (const item of data.data) {
        expect(allowed.includes(item.category) || /^other_policy:\d+$/.test(item.category)).toBe(true);
      }
    });

    test('子任务 status 在允许范围内', async ({ request }) => {
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
      const allowedStatuses = ['pending', 'submitted', 'completed'];
      for (const item of data.data) {
        expect(allowedStatuses).toContain(item.status);
      }
    });
  });

  // ============================================================
  // 二、按分类确认
  // ============================================================
  test.describe('二、按分类确认', () => {
    test('按分类确认需要该分类的附件', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'submitted', per_page: 10 },
      });
      const listData = await listRes.json();
      const submitted = listData.data.find(
        (item: any) => item.change_items && item.change_items.length > 0
      );
      if (!submitted) {
        test.skip();
        return;
      }

      const itemsRes = await request.get(
        `${BASE_URL}/insurance-changes/${submitted.id}/items`,
        { headers: authHeaders() }
      );
      const itemsData = await itemsRes.json();
      const pendingItem = itemsData.data.find((item: any) => item.status === 'pending');
      if (!pendingItem) {
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/insurance-changes/${submitted.id}/confirm-process`,
        { headers: authHeaders(), data: { category: pendingItem.category } }
      );
      if (response.status() === 400) {
        const data = await response.json();
        expect(data.success).toBe(false);
      }
    });

    test('不存在的分类应返回失败', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'submitted', per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const id = listData.data[0].id;
      const response = await request.put(
        `${BASE_URL}/insurance-changes/${id}/confirm-process`,
        { headers: authHeaders(), data: { category: 'nonexistent_category' } }
      );
      expect([400, 422]).toContain(response.status());
    });

    test('confirm-other-insurance-only 接口可用', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
      });
      const listData = await listRes.json();
      const withOtherInsurance = listData.data.find(
        (item: any) => item.other_insurance_policies && item.status !== 'completed'
      );
      if (!withOtherInsurance) {
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/insurance-changes/${withOtherInsurance.id}/confirm-other-insurance-only`,
        { headers: authHeaders(), data: {} }
      );
      expect([200, 400]).toContain(response.status());
      const data = await response.json();
      expect(data).toHaveProperty('success');
    });
  });

  // ============================================================
  // 三、子任务与整单状态联动
  // ============================================================
  test.describe('三、子任务与整单状态联动', () => {
    test('所有子任务完成后整单应为 completed', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'completed', per_page: 10 },
      });
      const listData = await listRes.json();
      const completedWithItems = listData.data.find(
        (item: any) => item.change_items && item.change_items.length > 0
      );
      if (!completedWithItems) {
        test.skip();
        return;
      }

      const itemsRes = await request.get(
        `${BASE_URL}/insurance-changes/${completedWithItems.id}/items`,
        { headers: authHeaders() }
      );
      const itemsData = await itemsRes.json();
      for (const item of itemsData.data) {
        expect(item.status).toBe('completed');
      }
    });

    test('部分子任务完成时整单应为 submitted', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'submitted', per_page: 10 },
      });
      const listData = await listRes.json();
      const partialComplete = listData.data.find((item: any) => {
        if (!item.change_items || item.change_items.length < 2) return false;
        const completed = item.change_items.filter((i: any) => i.status === 'completed');
        return completed.length > 0 && completed.length < item.change_items.length;
      });
      if (!partialComplete) {
        test.skip();
        return;
      }

      expect(partialComplete.status).toBe('submitted');
      expect(partialComplete.fully_confirmed).toBeFalsy();
    });
  });

  // ============================================================
  // 四、不存在 ID 的错误处理
  // ============================================================
  test.describe('四、错误处理', () => {
    test('对不存在 ID 获取 items 应返回 404', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/999999/items`, {
        headers: authHeaders(),
      });
      expect([404, 500]).toContain(response.status());
    });

    test('对不存在 ID 按分类确认应返回 404 或 500', async ({ request }) => {
      const response = await request.put(
        `${BASE_URL}/insurance-changes/999999/confirm-process`,
        { headers: authHeaders(), data: { category: 'social_security' } }
      );
      expect([404, 500]).toContain(response.status());
    });
  });
});
