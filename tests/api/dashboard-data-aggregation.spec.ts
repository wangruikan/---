import { test, expect, APIRequestContext, APIResponse } from '@playwright/test';

const BASE_URL = process.env.API_BASE_URL || 'http://localhost:8000/api';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const RESPONSE_TIME_LIMIT = Number(process.env.RESPONSE_TIME_LIMIT || 5000);

interface LoginResponse {
  success: boolean;
  data: {
    token: string;
  };
}

function authHeaders(token: string) {
  return {
    Authorization: `Bearer ${token}`,
    'X-Account-Set-Id': String(ACCOUNT_SET_ID),
  };
}

function assertJsonResponse(response: APIResponse) {
  expect(response.headers()['content-type']).toContain('application/json');
}

async function login(request: APIRequestContext): Promise<string> {
  const response = await request.post(`${BASE_URL}/auth/login`, {
    data: {
      username: USERNAME,
      password: PASSWORD,
    },
  });

  expect(response.status()).toBe(200);
  const body = (await response.json()) as LoginResponse;
  expect(body.success).toBe(true);
  expect(body.data.token).toBeTruthy();
  return body.data.token;
}

test.describe('Dashboard 首页聚合 API', () => {
  test('统一接口返回首页首屏需要的列表和统计数据', async ({ request }) => {
    const token = await login(request);
    const headers = authHeaders(token);

    const startedAt = Date.now();
    const response = await request.get(`${BASE_URL}/dashboard/data`, {
      headers,
      params: {
        account_set_id: ACCOUNT_SET_ID,
      },
    });
    const elapsed = Date.now() - startedAt;

    expect(response.status()).toBe(200);
    expect(elapsed).toBeLessThan(RESPONSE_TIME_LIMIT);
    assertJsonResponse(response);

    const body = await response.json();
    expect(body.success).toBe(true);
    expect(body.data).toHaveProperty('stats');
    expect(body.data).toHaveProperty('projects');
    expect(body.data).toHaveProperty('reminders');
    expect(body.data).toHaveProperty('employeeDistribution');
    expect(body.data).toHaveProperty('contractStatistics');
    expect(body.data).toHaveProperty('myTasks');
    expect(body.data).toHaveProperty('quickStats');
    expect(body.data).toHaveProperty('assessmentRecords');
    expect(body.data).toHaveProperty('monthlyWorkStats');

    expect(Array.isArray(body.data.reminders)).toBe(true);
    expect(Array.isArray(body.data.employeeDistribution)).toBe(true);
    expect(Array.isArray(body.data.contractStatistics)).toBe(true);
    expect(Array.isArray(body.data.assessmentRecords)).toBe(true);
    expect(Array.isArray(body.data.myTasks.list)).toBe(true);
    expect(typeof body.data.myTasks.total).toBe('number');

    expect(typeof body.data.quickStats.pendingTasks).toBe('number');
    expect(typeof body.data.quickStats.approved).toBe('number');
    expect(typeof body.data.quickStats.initiated).toBe('number');

    expect(typeof body.data.monthlyWorkStats.total).toBe('number');
    expect(typeof body.data.monthlyWorkStats.pending).toBe('number');
    expect(typeof body.data.monthlyWorkStats.approved).toBe('number');
    expect(typeof body.data.monthlyWorkStats.rejected).toBe('number');
    expect(typeof body.data.monthlyWorkStats.completed).toBe('number');
  });
});
