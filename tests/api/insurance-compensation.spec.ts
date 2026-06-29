import { test, expect, APIRequestContext } from '@playwright/test';

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

test.describe('补差 API 测试', () => {
  test.beforeAll(async ({ request }) => {
    const { token, userId } = await login(request, USERNAME, PASSWORD);
    authToken = token;
    currentUserId = userId;
  });

  // ============================================================
  // 一、社保补差
  // ============================================================
  test.describe('一、社保补差', () => {
    test('获取社保补差列表成功', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/insurance-changes/social-security-compensation`,
        { headers: authHeaders(), params: { account_set_id: ACCOUNT_SET_ID } }
      );
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);
    });

    test('缺少 account_set_id 返回 400', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/insurance-changes/social-security-compensation`,
        { headers: authHeaders() }
      );
      expect(response.status()).toBe(400);
    });

    test('按月份筛选成功', async ({ request }) => {
      const now = new Date();
      const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
      const response = await request.get(
        `${BASE_URL}/insurance-changes/social-security-compensation`,
        { headers: authHeaders(), params: { account_set_id: ACCOUNT_SET_ID, month } }
      );
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
    });

    test('按 region_name 筛选成功', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/insurance-changes/social-security-compensation`,
        { headers: authHeaders(), params: { account_set_id: ACCOUNT_SET_ID, region_name: '全部' } }
      );
      expect(response.status()).toBe(200);
    });

    test('补差记录结构合理', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/insurance-changes/social-security-compensation`,
        { headers: authHeaders(), params: { account_set_id: ACCOUNT_SET_ID } }
      );
      const data = await response.json();
      if (data.data.length === 0) {
        test.skip();
        return;
      }
      const row = data.data[0];
      expect(row).toHaveProperty('id');
      expect(row).toHaveProperty('employee_id');
      expect(row).toHaveProperty('employee_name');
    });
  });

  // ============================================================
  // 二、医保补差
  // ============================================================
  test.describe('二、医保补差', () => {
    test('获取医保补差列表成功', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/insurance-changes/medical-insurance-compensation`,
        { headers: authHeaders(), params: { account_set_id: ACCOUNT_SET_ID } }
      );
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);
    });

    test('缺少 account_set_id 返回 400', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/insurance-changes/medical-insurance-compensation`,
        { headers: authHeaders() }
      );
      expect(response.status()).toBe(400);
    });

    test('补差记录结构合理', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/insurance-changes/medical-insurance-compensation`,
        { headers: authHeaders(), params: { account_set_id: ACCOUNT_SET_ID } }
      );
      const data = await response.json();
      if (data.data.length === 0) {
        test.skip();
        return;
      }
      const row = data.data[0];
      expect(row).toHaveProperty('id');
      expect(row).toHaveProperty('employee_id');
      expect(row).toHaveProperty('employee_name');
    });
  });

  // ============================================================
  // 三、公积金补差
  // ============================================================
  test.describe('三、公积金补差', () => {
    test('获取公积金补差列表成功', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/insurance-changes/housing-fund-compensation`,
        { headers: authHeaders(), params: { account_set_id: ACCOUNT_SET_ID } }
      );
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(Array.isArray(data.data)).toBe(true);
    });

    test('缺少 account_set_id 返回 400', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/insurance-changes/housing-fund-compensation`,
        { headers: authHeaders() }
      );
      expect(response.status()).toBe(400);
    });

    test('按月份筛选成功', async ({ request }) => {
      const now = new Date();
      const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
      const response = await request.get(
        `${BASE_URL}/insurance-changes/housing-fund-compensation`,
        { headers: authHeaders(), params: { account_set_id: ACCOUNT_SET_ID, month } }
      );
      expect(response.status()).toBe(200);
    });

    test('按 region_name 筛选成功', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/insurance-changes/housing-fund-compensation`,
        { headers: authHeaders(), params: { account_set_id: ACCOUNT_SET_ID, region_name: '全部' } }
      );
      expect(response.status()).toBe(200);
    });

    test('补差记录结构合理', async ({ request }) => {
      const response = await request.get(
        `${BASE_URL}/insurance-changes/housing-fund-compensation`,
        { headers: authHeaders(), params: { account_set_id: ACCOUNT_SET_ID } }
      );
      const data = await response.json();
      if (data.data.length === 0) {
        test.skip();
        return;
      }
      const row = data.data[0];
      expect(row).toHaveProperty('id');
      expect(row).toHaveProperty('employee_id');
      expect(row).toHaveProperty('employee_name');
    });
  });

  // ============================================================
  // 四、触发补差计算
  // ============================================================
  test.describe('四、触发补差计算', () => {
    test('trigger-compensation 缺少 account_set_id 返回 400', async ({ request }) => {
      const response = await request.post(
        `${BASE_URL}/insurance-changes/trigger-compensation`,
        { headers: authHeaders(), data: {} }
      );
      expect(response.status()).toBe(400);
    });

    test('trigger-compensation 成功', async ({ request }) => {
      const response = await request.post(
        `${BASE_URL}/insurance-changes/trigger-compensation`,
        { headers: authHeaders(), params: { account_set_id: ACCOUNT_SET_ID } }
      );
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.message).toContain('补差');
    });
  });

  // ============================================================
  // 五、契约检查
  // ============================================================
  test.describe('五、契约检查', () => {
    test('三个补差接口响应时间合理', async ({ request }) => {
      const endpoints = [
        '/insurance-changes/social-security-compensation',
        '/insurance-changes/medical-insurance-compensation',
        '/insurance-changes/housing-fund-compensation',
      ];

      for (const endpoint of endpoints) {
        const start = Date.now();
        await request.get(`${BASE_URL}${endpoint}`, {
          headers: authHeaders(),
          params: { account_set_id: ACCOUNT_SET_ID },
        });
        const elapsed = Date.now() - start;
        expect(elapsed).toBeLessThan(10000);
      }
    });

    test('未登录访问补差接口返回 401', async ({ request }) => {
      const endpoints = [
        '/insurance-changes/social-security-compensation',
        '/insurance-changes/medical-insurance-compensation',
        '/insurance-changes/housing-fund-compensation',
      ];

      for (const endpoint of endpoints) {
        const response = await request.get(`${BASE_URL}${endpoint}`, {
          params: { account_set_id: ACCOUNT_SET_ID },
        });
        expect(response.status()).toBe(401);
      }
    });
  });
});
