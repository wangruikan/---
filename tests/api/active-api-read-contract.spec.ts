import { expect, test } from '@playwright/test';
import { API_BASE_URL, callRoute, loadActiveApiInventory } from './support/active-api';

const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || '123456';
const MINI_PHONE = process.env.MINI_PHONE || '13800000001';
const MINI_PASSWORD = process.env.MINI_PASSWORD || '011234';
const ACCOUNT_SET_ID = Number(process.env.E2E_ACCOUNT_SET_ID || 1);
const readRoutes = loadActiveApiInventory().usedRoutes.filter((route) => route.method === 'GET');
const allowedBusinessForbidden = new Set(['mini/my-resignation-certificates']);

let adminHeaders: Record<string, string>;
let miniHeaders: Record<string, string>;

test.describe('Active API authenticated read contract', () => {
  test.setTimeout(20_000);

  test.beforeAll(async ({ request }) => {
    const adminLogin = await request.post(`${API_BASE_URL}/auth/login`, {
      data: { username: USERNAME, password: PASSWORD },
    });
    expect(adminLogin.status()).toBe(200);
    const adminBody = await adminLogin.json();
    const adminToken = adminBody.data?.token || adminBody.token;
    expect(adminToken).toBeTruthy();
    adminHeaders = {
      Authorization: `Bearer ${adminToken}`,
      'X-Auth-Token': adminToken,
      'X-Account-Set-Id': String(ACCOUNT_SET_ID),
    };

    const miniLogin = await request.post(`${API_BASE_URL}/mini/login`, {
      data: { phone: MINI_PHONE, password: MINI_PASSWORD },
    });
    expect(miniLogin.status()).toBe(200);
    const miniBody = await miniLogin.json();
    const miniToken = miniBody.data?.token || miniBody.token;
    expect(miniToken).toBeTruthy();
    miniHeaders = {
      Authorization: `Bearer ${miniToken}`,
      'X-Auth-Token': miniToken,
    };
  });

  for (const route of readRoutes) {
    test(`GET /${route.uri} does not fail at the API boundary`, async ({ request }) => {
      const isMini = route.uri.startsWith('mini/');
      const response = await callRoute(
        request,
        route,
        isMini ? miniHeaders : adminHeaders,
        isMini ? {} : { current_account_set_id: ACCOUNT_SET_ID },
      );
      const status = response.status();

      expect(status, `GET /${route.uri} returned ${status}`).toBeLessThan(500);
      expect(status, `GET /${route.uri} unexpectedly rejected a valid token`).not.toBe(401);
      if (!allowedBusinessForbidden.has(route.uri)) {
        expect(status, `GET /${route.uri} unexpectedly rejected a valid user`).not.toBe(403);
      }
      expect(status, `GET /${route.uri} has a route method mismatch`).not.toBe(405);
      if (status !== 204) expect(response.headers()['content-type']).toBeTruthy();
    });
  }
});
