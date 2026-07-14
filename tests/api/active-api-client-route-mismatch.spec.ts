import { expect, test } from '@playwright/test';
import { API_BASE_URL } from './support/active-api';

const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const MINI_PHONE = process.env.MINI_PHONE || '13800000001';
const MINI_PASSWORD = process.env.MINI_PASSWORD || '011234';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);

let adminHeaders: Record<string, string>;
let miniHeaders: Record<string, string>;

test.describe('Active client calls must resolve to JSON APIs', () => {
  test.beforeAll(async ({ request }) => {
    const adminLogin = await request.post(`${API_BASE_URL}/auth/login`, {
      data: { username: USERNAME, password: PASSWORD },
    });
    const adminBody = await adminLogin.json();
    const adminToken = adminBody.data?.token || adminBody.token;
    adminHeaders = {
      Authorization: `Bearer ${adminToken}`,
      'X-Auth-Token': adminToken,
      'X-Account-Set-Id': String(ACCOUNT_SET_ID),
      Accept: 'application/json',
    };

    const miniLogin = await request.post(`${API_BASE_URL}/mini/login`, {
      data: { phone: MINI_PHONE, password: MINI_PASSWORD },
    });
    const miniBody = await miniLogin.json();
    const miniToken = miniBody.data?.token || miniBody.token;
    miniHeaders = {
      Authorization: `Bearer ${miniToken}`,
      'X-Auth-Token': miniToken,
      Accept: 'application/json',
    };
  });

  test('GET /other-insurance-policies resolves to an API response', async ({ request }) => {
    const response = await request.get(`${API_BASE_URL}/other-insurance-policies`, {
      headers: adminHeaders,
      params: { current_account_set_id: ACCOUNT_SET_ID, insurance_type: '商业险' },
    });
    expect(response.status()).toBeLessThan(500);
    expect(response.headers()['content-type']).toContain('application/json');
  });

  test('GET /mini/check-documents resolves to an API response', async ({ request }) => {
    const response = await request.get(`${API_BASE_URL}/mini/check-documents`, {
      headers: miniHeaders,
    });
    expect(response.status()).toBeLessThan(500);
    expect(response.headers()['content-type']).toContain('application/json');
  });
});
