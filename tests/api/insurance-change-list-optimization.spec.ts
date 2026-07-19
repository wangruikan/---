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

test.describe('参保增减列表查询优化 API', () => {
  test('列表接口返回前端列表所需字段，并保持详情接口完整可查', async ({ request }) => {
    const token = await login(request);
    const headers = authHeaders(token);

    const startedAt = Date.now();
    const listResponse = await request.get(`${BASE_URL}/insurance-changes`, {
      headers,
      params: {
        account_set_id: ACCOUNT_SET_ID,
      },
    });
    const elapsed = Date.now() - startedAt;

    expect(listResponse.status()).toBe(200);
    expect(elapsed).toBeLessThan(RESPONSE_TIME_LIMIT);
    assertJsonResponse(listResponse);

    const listBody = await listResponse.json();
    expect(listBody.success).toBe(true);
    expect(Array.isArray(listBody.data)).toBe(true);

    if (listBody.data.length === 0) {
      test.skip();
      return;
    }

    const item = listBody.data[0];
    expect(item).toHaveProperty('id');
    expect(item).toHaveProperty('employee');
    expect(item).toHaveProperty('project');
    expect(item).toHaveProperty('attachments_count');
    expect(item).toHaveProperty('attachments');
    expect(item).toHaveProperty('change_items');
    expect(Array.isArray(item.attachments)).toBe(true);
    expect(Array.isArray(item.change_items)).toBe(true);

    expect(item.employee).toHaveProperty('id');
    expect(item.employee).toHaveProperty('name');
    expect(item.employee).toHaveProperty('id_number');
    expect(item.employee).toHaveProperty('phone');
    expect(item.employee).toHaveProperty('entry_date');

    if (item.employee.social_security_region) {
      expect(item.employee.social_security_region).toHaveProperty('id');
      expect(item.employee.social_security_region).toHaveProperty('name');
      expect(item.employee.social_security_region).not.toHaveProperty('social_security_types');
    }

    if (item.employee.medical_insurance_region) {
      expect(item.employee.medical_insurance_region).toHaveProperty('id');
      expect(item.employee.medical_insurance_region).toHaveProperty('name');
      expect(item.employee.medical_insurance_region).not.toHaveProperty('medical_insurance_types');
    }

    if (item.change_items.length > 0) {
      const changeItem = item.change_items[0];
      expect(changeItem).toHaveProperty('id');
      expect(changeItem).toHaveProperty('category');
      expect(changeItem).toHaveProperty('status');
      expect(changeItem).toHaveProperty('attachments_count');
      expect(changeItem).not.toHaveProperty('attachments');
      expect(changeItem).not.toHaveProperty('processor');
    }

    const detailResponse = await request.get(`${BASE_URL}/insurance-changes/${item.id}`, {
      headers,
    });
    expect(detailResponse.status()).toBe(200);
    assertJsonResponse(detailResponse);

    const detailBody = await detailResponse.json();
    expect(detailBody.success).toBe(true);
    expect(detailBody.data.id).toBe(item.id);
    expect(detailBody.data).toHaveProperty('change_items');
  });
});
