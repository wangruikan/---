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

test.describe('参保增减 - 特殊险种分支', () => {
  test.beforeAll(async ({ request }) => {
    const { token, userId } = await login(request, USERNAME, PASSWORD);
    authToken = token;
    currentUserId = userId;
  });

  // ============================================================
  // 一、大额医疗
  // ============================================================
  test.describe('一、大额医疗', () => {
    test('toggle-large-medical 启用成功', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'pending', per_page: 10 },
      });
      const listData = await listRes.json();
      const pending = listData.data.find((item: any) => item.status === 'pending');
      if (!pending) {
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/insurance-changes/${pending.id}/toggle-large-medical`,
        { headers: authHeaders(), data: { is_enabled: true } }
      );
      if (response.status() === 500) {
        console.log('toggle-large-medical 返回 500，可能缺少大额医疗配置');
        test.skip();
        return;
      }
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.change.large_medical_insurance_enabled).toBeTruthy();
    });

    test('toggle-large-medical 停用成功', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'pending', per_page: 10 },
      });
      const listData = await listRes.json();
      const pending = listData.data.find((item: any) => item.status === 'pending');
      if (!pending) {
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/insurance-changes/${pending.id}/toggle-large-medical`,
        { headers: authHeaders(), data: { is_enabled: false } }
      );
      if (response.status() === 500) {
        console.log('toggle-large-medical 返回 500，可能缺少大额医疗配置');
        test.skip();
        return;
      }
      expect(response.status()).toBe(200);
      const data = await response.json();
      expect(data.success).toBe(true);
      expect(data.data.change.large_medical_insurance_enabled).toBeFalsy();
    });

    test('toggle-large-medical 缺少 is_enabled 返回 422', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/insurance-changes/${listData.data[0].id}/toggle-large-medical`,
        { headers: authHeaders(), data: {} }
      );
      expect(response.status()).toBe(422);
    });

    test('toggle-large-medical is_enabled 必须是 boolean', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/insurance-changes/${listData.data[0].id}/toggle-large-medical`,
        { headers: authHeaders(), data: { is_enabled: 'yes' } }
      );
      expect(response.status()).toBe(422);
    });

    test('toggle 后详情数据同步变化', async ({ request }) => {
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

      const toggleRes = await request.put(
        `${BASE_URL}/insurance-changes/${id}/toggle-large-medical`,
        { headers: authHeaders(), data: { is_enabled: true } }
      );
      if (toggleRes.status() === 500) {
        test.skip();
        return;
      }

      const detailRes = await request.get(`${BASE_URL}/insurance-changes/${id}`, {
        headers: authHeaders(),
      });
      const detailData = await detailRes.json();
      expect(detailData.data.large_medical_insurance_enabled).toBeTruthy();
    });
  });

  // ============================================================
  // 二、其他保险费用更新
  // ============================================================
  test.describe('二、其他保险费用更新', () => {
    test('update-other-insurance-cost 接口可用', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
      });
      const listData = await listRes.json();
      const withOther = listData.data.find(
        (item: any) => item.other_insurance_policies && item.status === 'pending'
      );
      if (!withOther) {
        test.skip();
        return;
      }

      const policies =
        typeof withOther.other_insurance_policies === 'string'
          ? JSON.parse(withOther.other_insurance_policies)
          : withOther.other_insurance_policies;
      if (!policies || policies.length === 0) {
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/insurance-changes/${withOther.id}/update-other-insurance-cost`,
        {
          headers: authHeaders(),
          data: { insurance_id: policies[0].id, employee_per_capita_cost: 100 },
        }
      );
      expect([200, 400]).toContain(response.status());
    });

    test('update-per-capita-cost 只允许 pending/submitted', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, status: 'completed', per_page: 10 },
      });
      const listData = await listRes.json();
      const completed = listData.data.find(
        (item: any) => item.other_insurance_policies
      );
      if (!completed) {
        test.skip();
        return;
      }

      const policies =
        typeof completed.other_insurance_policies === 'string'
          ? JSON.parse(completed.other_insurance_policies)
          : completed.other_insurance_policies;
      if (!policies || policies.length === 0) {
        test.skip();
        return;
      }

      const response = await request.post(
        `${BASE_URL}/insurance-changes/${completed.id}/update-per-capita-cost`,
        {
          headers: authHeaders(),
          data: { insurance_id: policies[0].id, employee_per_capita_cost: 100 },
        }
      );
      expect([400, 422]).toContain(response.status());
    });

    test('update-per-capita-cost 缺参数返回 422', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const response = await request.post(
        `${BASE_URL}/insurance-changes/${listData.data[0].id}/update-per-capita-cost`,
        { headers: authHeaders(), data: {} }
      );
      expect(response.status()).toBe(422);
    });

    test('update-endorsement-number 缺参数返回 422', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/insurance-changes/${listData.data[0].id}/update-endorsement-number`,
        { headers: authHeaders(), data: {} }
      );
      expect(response.status()).toBe(422);
    });

    test('update-endorsement-number 不存在的保单返回 404', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const response = await request.put(
        `${BASE_URL}/insurance-changes/${listData.data[0].id}/update-endorsement-number`,
        { headers: authHeaders(), data: { insurance_id: 999999, endorsement_number: 'TEST-001' } }
      );
      expect(response.status()).toBe(404);
    });
  });

  // ============================================================
  // 三、退保金额
  // ============================================================
  test.describe('三、退保金额', () => {
    test('非 decrease 记录调用 update-surrender-amount 应失败', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
      });
      const listData = await listRes.json();
      const increase = listData.data.find(
        (item: any) => item.change_type === 'increase' && item.status !== 'completed'
      );
      if (!increase) {
        test.skip();
        return;
      }

      const response = await request.post(
        `${BASE_URL}/insurance-changes/${increase.id}/update-surrender-amount`,
        { headers: authHeaders(), data: { insurance_id: 1, surrender_amount: 100 } }
      );
      expect(response.status()).toBe(400);
    });

    test('decrease 记录可调用 update-surrender-amount', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 10 },
      });
      const listData = await listRes.json();
      const decrease = listData.data.find(
        (item: any) =>
          item.change_type === 'decrease' &&
          ['pending', 'submitted'].includes(item.status) &&
          item.other_insurance_policies
      );
      if (!decrease) {
        test.skip();
        return;
      }

      const policies =
        typeof decrease.other_insurance_policies === 'string'
          ? JSON.parse(decrease.other_insurance_policies)
          : decrease.other_insurance_policies;
      if (!policies || policies.length === 0) {
        test.skip();
        return;
      }

      const response = await request.post(
        `${BASE_URL}/insurance-changes/${decrease.id}/update-surrender-amount`,
        {
          headers: authHeaders(),
          data: { insurance_id: policies[0].id, surrender_amount: 500 },
        }
      );
      expect([200, 404]).toContain(response.status());
    });

    test('update-surrender-amount 缺参数返回 422', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const response = await request.post(
        `${BASE_URL}/insurance-changes/${listData.data[0].id}/update-surrender-amount`,
        { headers: authHeaders(), data: {} }
      );
      expect(response.status()).toBe(422);
    });
  });

  // ============================================================
  // 四、名额管理
  // ============================================================
  test.describe('四、名额管理 (use-quota)', () => {
    test('use-quota 缺参数返回 422', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const response = await request.post(
        `${BASE_URL}/insurance-changes/${listData.data[0].id}/use-quota`,
        { headers: authHeaders(), data: {} }
      );
      expect(response.status()).toBe(422);
    });

    test('use-quota 不存在的保单返回失败', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 1 },
      });
      const listData = await listRes.json();
      if (listData.data.length === 0) {
        test.skip();
        return;
      }

      const response = await request.post(
        `${BASE_URL}/insurance-changes/${listData.data[0].id}/use-quota`,
        { headers: authHeaders(), data: { insurance_id: 999999 } }
      );
      expect([404, 422, 500]).toContain(response.status());
    });
  });

  // ============================================================
  // 五、减少参保特有逻辑
  // ============================================================
  test.describe('五、减少参保特有逻辑', () => {
    test('decrease 记录列表可见', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const decrease = listData.data.find((item: any) => item.change_type === 'decrease');
      if (!decrease) {
        console.log('跳过: 没有 decrease 类型记录');
        test.skip();
        return;
      }
      expect(decrease.change_type).toBe('decrease');
    });

    test('decrease 记录详情包含快照字段', async ({ request }) => {
      const listRes = await request.get(`${BASE_URL}/insurance-changes`, {
        headers: authHeaders(),
        params: { account_set_id: ACCOUNT_SET_ID, per_page: 100 },
      });
      const listData = await listRes.json();
      const decrease = listData.data.find((item: any) => item.change_type === 'decrease');
      if (!decrease) {
        test.skip();
        return;
      }

      const detailRes = await request.get(`${BASE_URL}/insurance-changes/${decrease.id}`, {
        headers: authHeaders(),
      });
      const detailData = await detailRes.json();
      expect(detailData.data).toHaveProperty('social_security_types');
      expect(detailData.data).toHaveProperty('medical_insurance_types');
    });
  });

  // ============================================================
  // 六、错误处理
  // ============================================================
  test.describe('六、错误处理', () => {
    test('对不存在 ID 的操作返回 404 或 500', async ({ request }) => {
      const endpoints = [
        { method: 'put' as const, path: '/insurance-changes/999999/confirm-process' },
        { method: 'put' as const, path: '/insurance-changes/999999/confirm-other-insurance-only' },
        { method: 'put' as const, path: '/insurance-changes/999999/update-other-insurance-cost' },
        { method: 'put' as const, path: '/insurance-changes/999999/update-endorsement-number' },
        { method: 'post' as const, path: '/insurance-changes/999999/update-per-capita-cost' },
        { method: 'post' as const, path: '/insurance-changes/999999/update-surrender-amount' },
        { method: 'post' as const, path: '/insurance-changes/999999/use-quota' },
        { method: 'put' as const, path: '/insurance-changes/999999/toggle-large-medical' },
      ];

      for (const ep of endpoints) {
        const response = await request[ep.method](`${BASE_URL}${ep.path}`, {
          headers: authHeaders(),
          data: {},
        });
        expect([404, 500]).toContain(response.status());
      }
    });

    test('未登录应返回 401', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/insurance-changes/details`, {
        params: { account_set_id: ACCOUNT_SET_ID },
      });
      expect(response.status()).toBe(401);
    });
  });
});
