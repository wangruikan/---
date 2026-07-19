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

test.describe('付款申请列表查询优化 API', () => {
  test('列表接口数据库分页并返回列表必要字段，详情接口仍返回完整附件', async ({ request }) => {
    const token = await login(request);
    const headers = authHeaders(token);

    const startedAt = Date.now();
    const listResponse = await request.get(`${BASE_URL}/payment-applications`, {
      headers,
      params: {
        current_account_set_id: ACCOUNT_SET_ID,
        page: 1,
        per_page: 3,
      },
    });
    const elapsed = Date.now() - startedAt;

    expect(listResponse.status()).toBe(200);
    expect(elapsed).toBeLessThan(RESPONSE_TIME_LIMIT);
    assertJsonResponse(listResponse);

    const listBody = await listResponse.json();
    expect(listBody.success).toBe(true);
    expect(listBody.data.current_page).toBe(1);
    expect(listBody.data.per_page).toBe(3);
    expect(Array.isArray(listBody.data.data)).toBe(true);
    expect(listBody.data.data.length).toBeLessThanOrEqual(3);

    if (listBody.data.data.length === 0) {
      test.skip();
      return;
    }

    const item = listBody.data.data[0];
    expect(item).toHaveProperty('id');
    expect(item).toHaveProperty('payment_type');
    expect(item).toHaveProperty('type_name');
    expect(item).toHaveProperty('attachments_count');
    expect(item).toHaveProperty('invoice_attachments_count');
    expect(item).toHaveProperty('approval_instance');
    expect(typeof item.attachments_count).toBe('number');
    expect(typeof item.invoice_attachments_count).toBe('number');
    expect(Array.isArray(item.attachments)).toBe(true);

    if (item.approval_instance) {
      expect(item.approval_instance).toHaveProperty('records');
      expect(Array.isArray(item.approval_instance.records)).toBe(true);
      for (const record of item.approval_instance.records) {
        expect(record.status).toBe('pending');
        expect(record).toHaveProperty('approver_name');
      }
    }

    const detailResponse = await request.get(`${BASE_URL}/payment-applications/${item.id}`, {
      headers,
    });
    expect(detailResponse.status()).toBe(200);
    assertJsonResponse(detailResponse);

    const detailBody = await detailResponse.json();
    expect(detailBody.success).toBe(true);
    expect(detailBody.data.id).toBe(item.id);
    expect(Array.isArray(detailBody.data.attachments)).toBe(true);
  });
});
